<?php

namespace App\Exports;

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
        protected string $title = 'Inventario'
    ) {}

    public function headings(): array
    {
        if ($this->title === 'Movimientos') {
            return [
                'Fecha',
                'Producto',
                'Categoria',
                'Lote',
                'Tipo',
                'Motivo',
                'Cantidad',
                'Stock anterior',
                'Stock nuevo',
                'Stock actual',
                'Caducidad lote',
                'Usuario',
                'Notas',
            ];
        }

        return [
            'Producto',
            'Categoria',
            'Lote',
            'Estado',
            'Cantidad',
            'Cantidad inicial',
            'Caducidad',
            'Dias',
            'Ingreso lote',
            'Ultima entrada',
            'Stock actual',
            'Stock minimo',
            'Impacto estimado',
        ];
    }

    public function array(): array
    {
        if ($this->title === 'Movimientos') {
            return $this->rows
                ->map(fn ($row) => [
                    $row->movement_date ?? '-',
                    $row->product ?? '-',
                    $row->category ?? '-',
                    $row->lot_number ?? '-',
                    $row->status_label ?? '-',
                    $row->movement_reason_label ?? $row->movement_reason ?? '-',
                    (float) ($row->quantity ?? 0),
                    $row->previous_stock ?? '-',
                    $row->new_stock ?? '-',
                    $row->current_stock ?? '-',
                    $row->expiration_date ?? '-',
                    $row->user ?? '-',
                    $row->notes ?? '-',
                ])
                ->toArray();
        }

        return $this->rows
            ->map(fn ($row) => [
                $row->product ?? '-',
                $row->category ?? '-',
                $row->lot_number ?? '-',
                $row->status_label ?? '-',
                (float) ($row->quantity ?? 0),
                $row->initial_quantity ?? '-',
                $row->expiration_date ?? '-',
                $row->days ?? '-',
                $row->received_at ?? '-',
                $row->last_entry_at ?? '-',
                $row->current_stock ?? '-',
                $row->min_stock ?? '-',
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
