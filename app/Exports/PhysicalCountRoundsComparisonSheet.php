<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhysicalCountRoundsComparisonSheet implements FromArray, WithEvents, WithStyles, WithTitle
{
    public function __construct(protected array $payload) {}

    public function array(): array
    {
        $rounds = collect($this->payload['rounds'] ?? [])->sortBy('round_number')->values();
        $roundNumbers = $rounds->pluck('round_number')->unique()->sort()->values();
        $entries = collect($this->payload['allEntries'] ?? []);

        $valuesByRoundProduct = $entries
            ->groupBy(fn ($entry) => $entry->physical_count_id.':'.$entry->branch_product_id.':'.($entry->round?->round_number ?? 1))
            ->map(fn (Collection $group) => max(
                0,
                (float) $group->sum('counted_quantity')
                    - (float) $group->sum('damaged_quantity')
                    - (float) $group->sum('expired_quantity')
            ));

        $rows = collect($this->payload['reportRows'] ?? [])
            ->map(function (array $row) use ($roundNumbers, $valuesByRoundProduct) {
                $baseKey = ($row['physical_count_id'] ?? 0).':'.($row['branch_product_id'] ?? 0);
                $roundValues = $roundNumbers->map(
                    fn ($roundNumber) => $valuesByRoundProduct->get($baseKey.':'.$roundNumber)
                );
                $lastIndex = $roundValues->search(fn ($value) => $value !== null);
                $origin = 'Sin conteo';

                foreach ($roundNumbers as $index => $roundNumber) {
                    if ($roundValues->get($index) !== null) {
                        $lastIndex = $index;
                        $origin = $roundNumber === 1 ? 'Ronda 1 - Original' : "Ronda {$roundNumber} - Reapertura";
                    }
                }

                $final = $lastIndex === false ? null : $roundValues->get($lastIndex);
                $systemStock = (float) ($row['system_stock'] ?? 0);

                return [
                    $row['branch_name'] ?? 'Sin sucursal',
                    $row['audit_name'] ?? 'Sin auditoría',
                    $row['folio'] ?? 'Sin folio',
                    $row['scanned_code'] ?? '-',
                    $row['product_name'] ?? 'Sin producto',
                    $row['category_name'] ?? 'Sin categoría',
                    $systemStock,
                    ...$roundValues->map(fn ($value) => $value ?? 'Sin recaptura')->all(),
                    $final ?? 'Pendiente',
                    $final === null ? 'Pendiente' : $final - $systemStock,
                    $origin,
                    ! empty($row['participants']) ? implode(', ', $row['participants']) : 'Sin usuario',
                    $row['last_entry_at'] ?? null,
                ];
            })
            ->values()
            ->all();

        return [
            [
                'Sucursal', 'Auditoría', 'Folio', 'Código', 'Producto', 'Categoría', 'Stock original',
                ...$roundNumbers->map(fn ($number) => $number === 1 ? 'Ronda 1 - Original' : "Ronda {$number} - Reapertura")->all(),
                'Conteo final', 'Diferencia final', 'Origen del resultado', 'Últimos participantes', 'Última captura',
            ],
            ...$rows,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F766E']],
        ]];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            $sheet->setAutoFilter("A1:{$highestColumn}{$highestRow}");
            $sheet->freezePane('H2');
            $sheet->setShowGridlines(false);
            $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->getBorders()->getAllBorders()->getColor()->setRGB('D1D5DB');
            $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A1:{$highestColumn}1")->getAlignment()->setWrapText(true);
            $lastColumn = Coordinate::columnIndexFromString($highestColumn);
            for ($column = 1; $column <= $lastColumn; $column++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(
                    in_array($column, [2, 5], true) ? 34 : 18
                );
            }
            $sheet->getStyle("G2:{$highestColumn}{$highestRow}")->getNumberFormat()->setFormatCode('#,##0.###');
            $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        }];
    }

    public function title(): string
    {
        return 'Comparativo rondas';
    }
}
