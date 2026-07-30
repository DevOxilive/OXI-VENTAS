<?php

namespace App\Exports;

use App\Models\PhysicalCountRound;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhysicalCountRoundSheet implements FromArray, WithEvents, WithStyles, WithTitle
{
    public function __construct(
        protected PhysicalCountRound $round,
        protected array $payload,
        protected string $sheetTitle
    ) {}

    public function array(): array
    {
        $audit = collect($this->payload['audits'] ?? [])->firstWhere('id', $this->round->physical_count_id);
        $snapshotRows = collect($this->payload['reportRows'] ?? [])
            ->keyBy(fn (array $row) => ($row['physical_count_id'] ?? 0).':'.($row['branch_product_id'] ?? 0));

        $rows = collect($this->payload['allEntries'] ?? [])
            ->where('physical_count_round_id', $this->round->id)
            ->groupBy(fn ($entry) => $entry->branch_product_id.':'.$entry->user_id)
            ->map(function (Collection $entries) use ($audit, $snapshotRows) {
                $first = $entries->first();
                $snapshot = $snapshotRows->get($first->physical_count_id.':'.$first->branch_product_id, []);
                $counted = (float) $entries->sum('counted_quantity');
                $damaged = (float) $entries->sum('damaged_quantity');
                $expired = (float) $entries->sum('expired_quantity');
                $newStock = max(0, $counted - $damaged - $expired);
                $systemStock = (float) ($snapshot['system_stock'] ?? 0);
                $difference = $newStock - $systemStock;

                return [
                    $audit?->branch?->name ?? 'Sin sucursal',
                    $audit?->name ?? 'Sin auditoría',
                    $audit?->folio ?? 'Sin folio',
                    $this->round->round_number,
                    $this->round->type === 'original' ? 'Original' : 'Reapertura',
                    $this->round->scope === 'zero_stock' ? 'Solo stock cero' : 'Todos',
                    $first->user?->name ?? 'Sin usuario',
                    $snapshot['scanned_code'] ?? $first->branchProduct?->barcode ?? '-',
                    $snapshot['product_name'] ?? $first->branchProduct?->product?->name ?? 'Sin producto',
                    $snapshot['category_name'] ?? $first->branchProduct?->product?->category?->name ?? 'Sin categoría',
                    $systemStock,
                    $counted,
                    $damaged,
                    $expired,
                    $newStock,
                    $difference,
                    $difference < 0 ? 'Faltante' : ($difference > 0 ? 'Sobrante' : 'Coincidente'),
                    $entries->pluck('notes')->filter()->unique()->implode(' | '),
                    optional($entries->sortByDesc('created_at')->first()?->created_at)->toDateTimeString(),
                ];
            })
            ->values()
            ->all();

        return [
            ['Sucursal', 'Auditoría', 'Folio', 'Ronda', 'Tipo', 'Alcance', 'Usuario', 'Código', 'Producto', 'Categoría', 'Stock original', 'Conteo', 'Dañado', 'Caducado', 'Stock nuevo', 'Diferencia', 'Resultado', 'Observaciones', 'Fecha de captura'],
            ...$rows,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $this->round->type === 'original' ? '2563EB' : '7C3AED']],
        ]];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $highestRow = $sheet->getHighestRow();
            $sheet->setAutoFilter("A1:S{$highestRow}");
            $sheet->freezePane('H2');
            $sheet->setShowGridlines(false);
            $sheet->getStyle("A1:S{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A1:S{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("A1:S{$highestRow}")->getBorders()->getAllBorders()->getColor()->setRGB('D1D5DB');
            foreach (['A' => 18, 'B' => 28, 'C' => 19, 'D:F' => 14, 'G' => 20, 'H' => 18, 'I' => 38, 'J' => 20, 'K:P' => 14, 'Q' => 16, 'R' => 32, 'S' => 20] as $columns => $width) {
                if (str_contains($columns, ':')) {
                    [$from, $to] = explode(':', $columns);
                    foreach (range(ord($from), ord($to)) as $column) {
                        $sheet->getColumnDimension(chr($column))->setWidth($width);
                    }
                } else {
                    $sheet->getColumnDimension($columns)->setWidth($width);
                }
            }
            $sheet->getStyle("K2:P{$highestRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("R2:S{$highestRow}")->getAlignment()->setWrapText(true);
            $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
        }];
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }
}
