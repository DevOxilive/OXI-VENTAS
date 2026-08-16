<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PhysicalCountConsolidatedCountsSheet implements FromArray, WithColumnFormatting, WithEvents, WithStyles, WithTitle
{
    public function __construct(protected array $payload) {}

    public function array(): array
    {
        $audits = collect($this->payload['audits'] ?? [])->keyBy('id');
        $snapshotRows = collect($this->payload['reportRows'] ?? [])
            ->keyBy(fn (array $row) => ($row['physical_count_id'] ?? 0) . ':' . ($row['branch_product_id'] ?? 0));
        $visibleKeys = $snapshotRows->where('row_type', 'counted')->keys();
        $groups = collect($this->payload['entries'] ?? [])
            ->groupBy(fn ($entry) => $entry->physical_count_id . ':' . $entry->branch_product_id . ':' . $entry->user_id)
            ->filter(fn (Collection $entries) => $visibleKeys->contains(data_get($entries->first(), 'physical_count_id') . ':' . data_get($entries->first(), 'branch_product_id')));

        $rows = $groups->map(function (Collection $entries) use ($audits, $snapshotRows) {
            $first = $entries->first();
            $audit = $audits->get(data_get($first, 'physical_count_id'));
            $snapshot = $snapshotRows->get(data_get($first, 'physical_count_id') . ':' . data_get($first, 'branch_product_id'), []);
            $systemStock = (float) ($snapshot['system_stock'] ?? data_get($first, 'branchProduct.stock', 0));
            $counted = (float) $entries->sum('counted_quantity');
            $damaged = (float) $entries->sum('damaged_quantity');
            $expired = (float) $entries->sum('expired_quantity');
            $stockNew = max(0, $counted - $damaged - $expired);
            $difference = $stockNew - $systemStock;

            return [
                'sort' => optional(data_get($first, 'created_at'))->timestamp ?? 0,
                'branch' => $audit?->branch?->name ?? 'Sin sucursal',
                'audit' => $audit?->name ?? 'Sin auditoría',
                'folio' => $audit?->folio ?? 'Sin folio',
                'date' => optional($audit?->started_at)->toDateString(),
                'user' => data_get($first, 'user.name', 'Sin usuario'),
                'code' => data_get($first, 'scanned_code') ?: ($snapshot['scanned_code'] ?? data_get($first, 'branchProduct.barcode', '-')),
                'product' => $snapshot['product_name'] ?? data_get($first, 'branchProduct.product.name', 'Sin producto'),
                'category' => $snapshot['category_name'] ?? data_get($first, 'branchProduct.product.category.name', 'Sin categoría'),
                'system' => $systemStock,
                'counted' => $counted,
                'damaged' => $damaged,
                'expired' => $expired,
                'new' => $stockNew,
                'difference' => $difference,
                'result' => $difference < 0 ? 'Faltante' : ($difference > 0 ? 'Sobrante' : 'Coincidente'),
                'notes' => $entries->pluck('notes')->filter()->unique()->implode(' | '),
                'captured' => optional(data_get($entries->sortByDesc('created_at')->first(), 'created_at'))->toDateTimeString(),
            ];
        })->sortByDesc('sort')->values();

        return [
            ['Sucursal', 'Auditoría', 'Folio', 'Fecha', 'Usuario que contó', 'Código de barras', 'Producto', 'Categoría', 'Stock del sistema', 'Conteo físico', 'Dañado', 'Caducado', 'Stock nuevo', 'Diferencia', 'Resultado', 'Observaciones', 'Fecha de captura'],
            ...$rows->map(fn (array $row) => [
                $row['branch'], $row['audit'], $row['folio'], $row['date'], $row['user'], $row['code'], $row['product'], $row['category'],
                $row['system'], $row['counted'], $row['damaged'], $row['expired'], $row['new'], $row['difference'], $row['result'], $row['notes'], $row['captured'],
            ])->all(),
        ];
    }

    public function columnFormats(): array
    {
        return ['D' => 'yyyy-mm-dd', 'F' => '@', 'I:N' => '#,##0.###'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '7C3AED']]]];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            $sheet = $event->sheet->getDelegate();
            $highestRow = $sheet->getHighestRow();
            $sheet->setAutoFilter("A1:Q{$highestRow}");
            $sheet->freezePane('F2');
            $sheet->setShowGridlines(false);
            $sheet->getStyle("A1:Q{$highestRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle("A1:Q{$highestRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle("A1:Q{$highestRow}")->getBorders()->getAllBorders()->getColor()->setRGB('D1D5DB');
            $sheet->getStyle('A1:Q1')->getAlignment()->setWrapText(true);
            $sheet->getStyle("G2:H{$highestRow}")->getAlignment()->setWrapText(true);
            $sheet->getStyle("P2:Q{$highestRow}")->getAlignment()->setWrapText(true);
            foreach (['A' => 18, 'B' => 24, 'C' => 18, 'D' => 13, 'E' => 22, 'F' => 18, 'G' => 38, 'H' => 22, 'I' => 16, 'J:N' => 14, 'O' => 16, 'P' => 28, 'Q' => 20] as $column => $width) {
                if (str_contains($column, ':')) {
                    [$from, $to] = explode(':', $column);
                    foreach (range(ord($from), ord($to)) as $index) {
                        $sheet->getColumnDimension(chr($index))->setWidth($width);
                    }
                } else {
                    $sheet->getColumnDimension($column)->setWidth($width);
                }
            }
            for ($row = 2; $row <= $highestRow; $row++) {
                $result = (string) $sheet->getCell("O{$row}")->getValue();
                $color = match ($result) {
                    'Coincidente' => 'DCFCE7',
                    'Faltante' => 'FEE2E2',
                    'Sobrante' => 'DBEAFE',
                    default => 'FEF3C7',
                };
                $sheet->getStyle("O{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
                $sheet->getStyle("O{$row}")->getFont()->setBold(true);
            }
            for ($row = 2; $row <= $highestRow; $row++) {
                $sheet->getCell("F{$row}")->setValueExplicit((string) $sheet->getCell("F{$row}")->getValue(), DataType::TYPE_STRING);
            }
            $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1);
            $sheet->getHeaderFooter()->setOddFooter('&LSuper Kay&CConteos consolidados&RGenerado: &D &T');
            $sheet->getTabColor()->setRGB('7C3AED');
        }];
    }

    public function title(): string
    {
        return 'Conteos consolidados';
    }
}
