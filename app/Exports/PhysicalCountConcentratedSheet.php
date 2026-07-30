<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
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

class PhysicalCountConcentratedSheet implements FromArray, WithColumnFormatting, WithEvents, WithStyles, WithTitle
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
            'Sucursal',
            'Auditoría',
            'Folio',
            'Fecha',
            'Código de barras',
            'Descripción del producto',
            'Categoría',
            'Stock inicial',
            'Stock nuevo',
        ];

        foreach ($this->users as $user) {
            $headings[] = 'Conteo físico ' . $user->name;
            $headings[] = 'Dañado ' . $user->name;
            $headings[] = 'Caducado ' . $user->name;
        }

        return [
            ...$headings,
            'Total conteo físico',
            'Total dañado',
            'Total caducado',
            'Diferencia',
            'Diferencia %',
            'Resultado',
            'Participantes',
            'Última captura',
        ];
    }

    protected function rows(): array
    {
        $entriesByAuditProductUser = $this->entries
            ->groupBy(fn ($entry) => $entry->physical_count_id . ':' . $entry->branch_product_id . ':' . $entry->user_id)
            ->map(fn ($group) => [
                'counted' => (float) $group->sum('counted_quantity'),
                'damaged' => (float) $group->sum('damaged_quantity'),
                'expired' => (float) $group->sum('expired_quantity'),
            ]);

        return $this->filteredRows()
            ->values()
            ->map(function (array $row, int $index) use ($entriesByAuditProductUser) {
                $sheetRow = $index + 2;
                $countCells = $this->userColumnCells($sheetRow, 10);
                $damagedCells = $this->userColumnCells($sheetRow, 11);
                $expiredCells = $this->userColumnCells($sheetRow, 12);
                $userGroups = $this->users->mapWithKeys(function ($user) use ($row, $entriesByAuditProductUser) {
                    $key = ($row['physical_count_id'] ?? 0) . ':' . ($row['branch_product_id'] ?? 0) . ':' . $user->id;
                    return [$user->id => $entriesByAuditProductUser->get($key, ['counted' => 0, 'damaged' => 0, 'expired' => 0])];
                });
                $totalCounted = (float) $userGroups->sum('counted');
                $totalDamaged = (float) $userGroups->sum('damaged');
                $totalExpired = (float) $userGroups->sum('expired');
                $hasCount = $userGroups->contains(fn ($values) => ($values['counted'] ?? 0) > 0 || ($values['damaged'] ?? 0) > 0 || ($values['expired'] ?? 0) > 0);
                $newStock = $hasCount ? max(0, $totalCounted - $totalDamaged - $totalExpired) : null;
                $systemStock = (float) ($row['system_stock'] ?? 0);
                $difference = $newStock === null ? null : $newStock - $systemStock;

                $line = [
                    $row['branch_name'] ?? 'Sin sucursal',
                    $row['audit_name'] ?? 'Sin auditoría',
                    $row['folio'] ?? 'Sin folio',
                    $row['audit_date'] ?? null,
                    $row['scanned_code'] ?? '-',
                    $row['product_name'] ?? 'Sin producto',
                    $row['category_name'] ?? 'Sin categoría',
                    (float) ($row['system_stock'] ?? 0),
                    $newStock ?? 'Sin datos de conteo',
                ];

                foreach ($this->users as $user) {
                    $key = ($row['physical_count_id'] ?? 0) . ':' . ($row['branch_product_id'] ?? 0) . ':' . $user->id;
                    $values = $entriesByAuditProductUser->get($key, ['counted' => '', 'damaged' => '', 'expired' => '']);
                    $line[] = $values['counted'] === 0.0 ? '' : $values['counted'];
                    $line[] = $values['damaged'] === 0.0 ? '' : $values['damaged'];
                    $line[] = $values['expired'] === 0.0 ? '' : $values['expired'];
                }

                return [
                    ...$line,
                    $hasCount ? $totalCounted : 'Sin datos de conteo',
                    $hasCount ? $totalDamaged : 'Sin datos de conteo',
                    $hasCount ? $totalExpired : 'Sin datos de conteo',
                    $difference ?? 'Sin diferencia calculable',
                    ($difference !== null && $systemStock != 0) ? $difference / abs($systemStock) : 0,
                    $row['status_label'] ?? 'Pendiente',
                    implode(', ', $row['participants'] ?? []),
                    $row['last_entry_at'] ?? null,
                ];
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

    protected function userColumnCells(int $row, int $firstColumn): array
    {
        return $this->users
            ->values()
            ->map(fn ($user, int $index) => Coordinate::stringFromColumnIndex($firstColumn + ($index * 3)) . $row)
            ->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => $this->headerFontColor()]], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->headerColor()]]]];
    }

    public function columnFormats(): array
    {
        $differencePercentColumn = Coordinate::stringFromColumnIndex(15 + ($this->users->count() * 3));

        return [
            'D' => 'yyyy-mm-dd',
            'E' => NumberFormat::FORMAT_TEXT,
            'H:'.$differencePercentColumn => '#,##0.00',
            $differencePercentColumn => '0.00%',
        ];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            $lastColumnIndex = Coordinate::columnIndexFromString($highestColumn);
            $resultColumn = Coordinate::stringFromColumnIndex($lastColumnIndex - 2);

            $sheet->setAutoFilter("A1:{$highestColumn}{$highestRow}");
            $sheet->freezePane('F2');
            $sheet->setShowGridlines(false);
            $sheet->getTabColor()->setRGB($this->headerColor());
            $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A1:{$highestColumn}1")->getAlignment()->setWrapText(true);
            $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            for ($row = 2; $row <= $highestRow; $row++) {
                $sheet->getCell("E{$row}")->setValueExplicit((string) $sheet->getCell("E{$row}")->getValue(), DataType::TYPE_STRING);
            }
            $sheet->getColumnDimension('A')->setWidth(24);
            $sheet->getColumnDimension('B')->setWidth(30);
            $sheet->getColumnDimension('C')->setWidth(18);
            $sheet->getColumnDimension('D')->setWidth(14);
            $sheet->getColumnDimension('E')->setWidth(24);
            $sheet->getColumnDimension('F')->setWidth(44);
            $sheet->getColumnDimension('G')->setWidth(24);
            for ($column = 9; $column <= $lastColumnIndex; $column++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth($column >= $lastColumnIndex - 2 ? 24 : 15);
            }
            $sheet->getStyle("{$resultColumn}2:{$resultColumn}{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
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
                'missing' => 'FFEDD5',
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
            'matched' => '16A34A', 'missing' => 'FB923C', 'surplus' => 'FDE047', 'not_found' => '2563EB', default => '5B3F86',
        };
    }

    protected function headerFontColor(): string
    {
        return $this->statusFilter === 'surplus' ? '111827' : 'FFFFFF';
    }
}
