<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhysicalCountConcentratedSheet implements FromArray, ShouldAutoSize, WithColumnFormatting, WithEvents, WithStyles, WithTitle
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
        return [
            $this->headings(),
            ...$this->rows(),
        ];
    }

    public function headings(): array
    {
        $headings = [
            null,
            'Codigo de barras',
            'Descripcion Producto',
            'Stock Actual',
            'Stock.Nuevo',
        ];

        foreach ($this->users as $user) {
            $headings[] = 'Conteo Fisico ' . $user->name;
            $headings[] = 'Caducado detec. ' . $user->name;
        }

        return [
            ...$headings,
            'Total Conteo fisico',
            'Total Caducado',
            'Ventas del dia',
            'Diferencias Total',
            'Validado',
        ];
    }

    protected function rows(): array
    {
        $entriesByProductUser = $this->entries
            ->groupBy(fn ($entry) => $entry->branch_product_id . ':' . $entry->user_id)
            ->map(fn ($group) => [
                'counted' => (float) $group->sum('counted_quantity'),
                'expired' => (float) $group->sum('expired_quantity'),
            ]);

        return $this->filteredRows()
            ->values()
            ->map(function (array $row, int $index) use ($entriesByProductUser) {
                $sheetRow = $index + 2;
                $countCells = $this->userColumnCells($sheetRow, 6);
                $expiredCells = $this->userColumnCells($sheetRow, 7);
                $totalCountColumn = Coordinate::stringFromColumnIndex(6 + ($this->users->count() * 2));
                $totalExpiredColumn = Coordinate::stringFromColumnIndex(7 + ($this->users->count() * 2));

                $line = [
                    null,
                    $row['scanned_code'] ?? '-',
                    $row['product_name'] ?? 'Sin producto',
                    (float) ($row['system_stock'] ?? 0),
                    $this->users->isEmpty()
                        ? 'S/D'
                        : '=IF(COUNT(' . implode(',', $countCells) . ')=0,"S/D",SUM(' . implode(',', $countCells) . ')-SUM(' . implode(',', $expiredCells) . '))',
                ];

                foreach ($this->users as $user) {
                    $key = ($row['branch_product_id'] ?? 0) . ':' . $user->id;
                    $userCount = $entriesByProductUser->get($key, ['counted' => '', 'expired' => '']);

                    $line[] = $userCount['counted'] === 0.0 ? '' : $userCount['counted'];
                    $line[] = $userCount['expired'] === 0.0 ? '' : $userCount['expired'];
                }

                return [
                    ...$line,
                    $this->users->isEmpty() ? 'S/TF' : '=IF(COUNT(' . implode(',', $countCells) . ')=0,"S/TF",SUM(' . implode(',', $countCells) . '))',
                    $this->users->isEmpty() ? 'S/TC' : '=IF(COUNT(' . implode(',', $countCells) . ')=0,"S/TC",SUM(' . implode(',', $expiredCells) . '))',
                    0,
                    "=IF(D{$sheetRow}=\"\",\"----\",IFERROR({$totalCountColumn}{$sheetRow}-D{$sheetRow},\"S/DIF\"))",
                    false,
                ];
            })
            ->values()
            ->all();
    }

    protected function filteredRows(): Collection
    {
        $rows = collect($this->payload['reportRows'] ?? []);

        if (! $this->statusFilter) {
            return $rows;
        }

        return $rows
            ->filter(function (array $row) {
                return match ($this->statusFilter) {
                    'matched' => ($row['row_type'] ?? null) === 'counted' && ($row['status'] ?? null) === 'matched',
                    'missing' => ($row['row_type'] ?? null) === 'counted' && ($row['status'] ?? null) === 'missing',
                    'surplus' => ($row['row_type'] ?? null) === 'counted' && ($row['status'] ?? null) === 'surplus',
                    'not_found' => ($row['row_type'] ?? null) === 'pending',
                    default => true,
                };
            })
            ->values();
    }

    protected function userColumnCells(int $row, int $firstColumn): array
    {
        return $this->users
            ->values()
            ->map(fn ($user, int $index) => Coordinate::stringFromColumnIndex($firstColumn + ($index * 2)) . $row)
            ->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => $this->headerFontColor()]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->headerColor()]],
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'B' => NumberFormat::FORMAT_TEXT,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();
                $highestColumn = $sheet->getHighestColumn();

                $sheet->setAutoFilter("A1:{$highestColumn}{$highestRow}");
                $sheet->freezePane('A2');
                $sheet->getTabColor()->setRGB($this->headerColor());
                $sheet->getStyle("A1:{$highestColumn}1")->getAlignment()->setWrapText(true);
                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $lastNumericColumn = Coordinate::stringFromColumnIndex(max(4, Coordinate::columnIndexFromString($highestColumn) - 1));
                $sheet->getStyle("D2:{$lastNumericColumn}{$highestRow}")->getNumberFormat()->setFormatCode('#,##0.00');

                $this->applyResultColors($sheet, $highestColumn);

                for ($column = 1; $column <= Coordinate::columnIndexFromString($highestColumn); $column++) {
                    $letter = Coordinate::stringFromColumnIndex($column);
                    $sheet->getColumnDimension($letter)->setWidth(match ($column) {
                        1 => 20,
                        2 => 36,
                        3 => 44,
                        default => 16,
                    });
                }
            },
        ];
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }

    protected function applyResultColors(Worksheet $sheet, string $highestColumn): void
    {
        $this->filteredRows()
            ->values()
            ->each(function (array $row, int $index) use ($sheet, $highestColumn) {
                $sheetRow = $index + 2;
                $color = $this->resultFillColor($this->resultKey($row));

                if (! $color) {
                    return;
                }

                $sheet->getStyle("A{$sheetRow}:{$highestColumn}{$sheetRow}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB($color);
            });
    }

    protected function resultKey(array $row): ?string
    {
        if (($row['row_type'] ?? null) === 'pending') {
            return 'not_found';
        }

        return $row['status'] ?? $this->statusFilter;
    }

    protected function headerColor(): string
    {
        return $this->resultHeaderColor($this->statusFilter) ?? '5B3F86';
    }

    protected function headerFontColor(): string
    {
        return $this->statusFilter === 'surplus' ? '111827' : 'FFFFFF';
    }

    protected function resultHeaderColor(?string $result): ?string
    {
        return match ($result) {
            'matched' => '16A34A',
            'missing' => 'FB923C',
            'surplus' => 'FDE047',
            'not_found' => '2563EB',
            default => null,
        };
    }

    protected function resultFillColor(?string $result): ?string
    {
        return match ($result) {
            'matched' => 'DCFCE7',
            'missing' => 'FFEDD5',
            'surplus' => 'FEF9C3',
            'not_found' => 'DBEAFE',
            default => null,
        };
    }
}
