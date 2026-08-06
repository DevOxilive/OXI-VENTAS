<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhysicalCountConcentratedSheet implements FromArray, WithColumnFormatting, WithEvents, WithStrictNullComparison, WithStyles, WithTitle
{
    protected Collection $entries;

    public function __construct(
        protected array $payload,
        protected Collection $users,
        protected string $sheetTitle = 'Concentrado',
        protected ?string $statusFilter = null
    ) {
        $this->entries = collect($payload['entries'] ?? []);
    }

    public function array(): array
    {
        return [$this->headings(), ...$this->rows()];
    }

    public function headings(): array
    {
        $headings = [
            'Código(s) de barras',
            'Categoría',
            'Nombre del producto',
            'Stock actual',
            'Stock nuevo',
        ];

        foreach ($this->users as $user) {
            $headings[] = 'Conteo físico ' . $user->name;
            $headings[] = 'Caducado ' . $user->name;
            $headings[] = 'Dañado ' . $user->name;
        }

        return $headings;
    }

    protected function rows(): array
    {
        $entriesByAuditProductUser = $this->entries
            ->groupBy(fn ($entry) => $entry->physical_count_id . ':' . $entry->branch_product_id . ':' . $entry->user_id)
            ->map(fn ($group) => [
                'captured' => true,
                'counted' => (float) $group->sum('counted_quantity'),
                'damaged' => (float) $group->sum('damaged_quantity'),
                'expired' => (float) $group->sum('expired_quantity'),
            ]);

        return $this->filteredRows()
            ->values()
            ->map(function (array $row, int $index) use ($entriesByAuditProductUser) {
                $userGroups = $this->users->mapWithKeys(function ($user) use ($row, $entriesByAuditProductUser) {
                    $key = ($row['physical_count_id'] ?? 0) . ':' . ($row['branch_product_id'] ?? 0) . ':' . $user->id;
                    return [$user->id => $entriesByAuditProductUser->get($key, ['captured' => false, 'counted' => 0, 'damaged' => 0, 'expired' => 0])];
                });
                $totalCounted = (float) $userGroups->sum('counted');
                $totalDamaged = (float) $userGroups->sum('damaged');
                $totalExpired = (float) $userGroups->sum('expired');
                $hasCount = $userGroups->contains(fn ($values) => (bool) ($values['captured'] ?? false));
                $newStock = $hasCount ? max(0, $totalCounted - $totalDamaged - $totalExpired) : null;

                $line = [
                    PhysicalCountBarcodeList::fromRow($row),
                    $row['category_name'] ?? 'Sin categoría',
                    $row['product_name'] ?? 'Sin producto',
                    (float) ($row['system_stock'] ?? 0),
                    $newStock ?? 'Sin datos de conteo',
                ];

                foreach ($this->users as $user) {
                    $key = ($row['physical_count_id'] ?? 0) . ':' . ($row['branch_product_id'] ?? 0) . ':' . $user->id;
                    $values = $entriesByAuditProductUser->get($key, ['captured' => false, 'counted' => 0, 'damaged' => 0, 'expired' => 0]);
                    $line[] = $values['captured'] ? $values['counted'] : '';
                    $line[] = (float) $values['expired'] > 0 ? $values['expired'] : '';
                    $line[] = (float) $values['damaged'] > 0 ? $values['damaged'] : '';
                }

                return $line;
            })
            ->all();
    }

    protected function filteredRows(): Collection
    {
        $rows = collect($this->payload['reportRows'] ?? []);

        if ($this->statusFilter === 'not_found') {
            return $rows->where('row_type', 'pending')->values();
        }

        if (! $this->statusFilter) {
            return $rows->values();
        }

        return $rows->filter(fn (array $row) => ($row['row_type'] ?? null) === 'counted'
            && ($row['status'] ?? null) === $this->statusFilter)->values();
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => $this->headerFontColor()]], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->headerColor()]]]];
    }

    public function columnFormats(): array
    {
        $lastColumn = Coordinate::stringFromColumnIndex(5 + ($this->users->count() * 3));

        return [
            'A' => NumberFormat::FORMAT_TEXT,
            'D:'.$lastColumn => '#,##0.###',
        ];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            $lastColumnIndex = Coordinate::columnIndexFromString($highestColumn);

            $sheet->setAutoFilter("A1:{$highestColumn}{$highestRow}");
            $sheet->freezePane('F2');
            $sheet->setShowGridlines(false);
            $sheet->getTabColor()->setRGB($this->headerColor());
            $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A1:{$highestColumn}1")->getAlignment()->setWrapText(true);
            $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("A2:A{$highestRow}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            for ($row = 2; $row <= $highestRow; $row++) {
                $sheet->getCell("A{$row}")->setValueExplicit((string) $sheet->getCell("A{$row}")->getValue(), DataType::TYPE_STRING);
            }
            $sheet->getColumnDimension('A')->setWidth(24);
            $sheet->getColumnDimension('B')->setWidth(24);
            $sheet->getColumnDimension('C')->setWidth(44);
            $sheet->getColumnDimension('D')->setWidth(15);
            $sheet->getColumnDimension('E')->setWidth(15);
            for ($column = 6; $column <= $lastColumnIndex; $column++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(18);
            }
            $this->applyResultColors($sheet, $highestColumn);
            $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
        }];
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    protected function applyResultColors(Worksheet $sheet, string $highestColumn): void
    {
        $this->filteredRows()->values()->each(function (array $row, int $index) use ($sheet, $highestColumn) {
            $color = match (($row['row_type'] ?? null) === 'pending' ? 'not_found' : ($row['status'] ?? null)) {
                'matched' => 'DCFCE7',
                'missing' => 'FEE2E2',
                'surplus' => 'FEF9C3',
                'not_found' => 'DBEAFE',
                default => null,
            };
            if ($color) {
                $sheet->getStyle('A' . ($index + 2) . ':' . $highestColumn . ($index + 2))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
            }
        });
    }

    protected function headerColor(): string
    {
        return match ($this->statusFilter) {
            'matched' => '16A34A', 'missing' => 'DC2626', 'surplus' => 'FACC15', 'not_found' => '60A5FA', default => '5B3F86',
        };
    }

    protected function headerFontColor(): string
    {
        return $this->statusFilter === 'surplus' ? '111827' : 'FFFFFF';
    }
}
