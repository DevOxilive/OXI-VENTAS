<?php

namespace App\Exports;

use App\Support\QuantityFormatter;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InventoryReportExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        protected Collection $rows,
        protected string $title = 'Inventario',
        protected string $reportType = 'inventory'
    ) {}

    public function headings(): array
    {
        if ($this->reportType === 'movements') {
            return [
                'Código',
                'Nombre del producto',
                'Categoría',
                'Lote',
                'Usuario',
                'Tipo de movimiento',
                'Motivo',
                'Cantidad',
                'Fecha',
                'Notas',
            ];
        }

        return [
            'Código',
            'Nombre del producto',
            'Categoría',
            'Estado',
            'Lote',
            'Fecha de ingreso',
            'Fecha de caducidad',
            'Impacto de pérdida',
        ];
    }

    public function array(): array
    {
        if ($this->reportType === 'movements') {
            return $this->rows
                ->map(fn ($row) => [
                    $row->code ?? '-',
                    $row->product ?? '-',
                    $row->category ?? '-',
                    $row->lot_number ?? '-',
                    $row->user ?? '-',
                    $row->status_label ?? '-',
                    $row->movement_reason_label ?? $row->movement_reason ?? '-',
                    QuantityFormatter::format($row->quantity ?? 0, $row->inventory_unit ?? $row->unit ?? 'pza'),
                    $row->movement_date ?? '-',
                    $row->notes ?? '-',
                ])
                ->toArray();
        }

        return $this->rows
            ->map(fn ($row) => [
                $row->code ?? '-',
                $row->product ?? '-',
                $row->category ?? '-',
                $row->status_label ?? '-',
                $row->lot_number ?? '-',
                $row->received_at ?? '-',
                $row->expiration_date ?? '-',
                (float) ($row->estimated_loss ?? 0),
            ])
            ->toArray();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '111827'],
                ],
            ],
        ];
    }

    public function title(): string
    {
        return $this->title;
    }
}
