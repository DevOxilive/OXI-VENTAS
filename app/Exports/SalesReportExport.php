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

class SalesReportExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithTitle
{
    public function __construct(
        protected Collection $rows,
        protected string $title = 'Ventas',
        protected string $reportType = 'products',
    ) {}

    public function headings(): array
    {
        return match ($this->reportType) {
            'sales' => [
                'Folio',
                'Fecha',
                'Sucursal',
                'Vendedor',
                'Total de productos vendidos',
                'Total pagado',
            ],
            'sale-detail' => [
                'Folio',
                'Fecha',
                'Producto',
                'Código',
                'Presentación visual',
                'Cantidad visual',
                'Cantidad base descontada',
                'Precio unitario',
                'Descuento',
                'Subtotal',
            ],
            default => [
                'Producto',
                'Código',
                'Sucursal',
                'Stock actual',
                'Piezas/kg vendidas en el periodo',
                'Promedio mensual vendido',
                'Última venta',
            ],
        };
    }

    public function array(): array
    {
        if ($this->reportType === 'sales') {
            return $this->rows
                ->map(fn ($row) => [
                    $row['folio'] ?? '-',
                    $row['date_display'] ?? '-',
                    $row['branch'] ?? '-',
                    $row['seller'] ?? '-',
                    $row['total_products_sold_display'] ?? '0',
                    (float) ($row['total'] ?? 0),
                ])
                ->toArray();
        }

        if ($this->reportType === 'sale-detail') {
            return $this->rows
                ->flatMap(fn ($sale) => collect($sale['details'] ?? [])->map(fn ($detail) => [
                    $sale['folio'] ?? '-',
                    $sale['date_display'] ?? '-',
                    $detail['product'] ?? '-',
                    $detail['code'] ?? '-',
                    $detail['presentation'] ?? '-',
                    $detail['quantity_display'] ?? '0',
                    $detail['base_quantity_display'] ?? '0',
                    (float) ($detail['unit_price'] ?? 0),
                    (float) ($detail['discount_amount'] ?? 0),
                    (float) ($detail['subtotal'] ?? 0),
                ]))
                ->values()
                ->toArray();
        }

        return $this->rows
            ->map(fn ($row) => [
                $row['product'] ?? '-',
                $row['code'] ?? '-',
                $row['branch'] ?? '-',
                $row['current_stock_display'] ?? '0',
                $row['sold_quantity_display'] ?? '0',
                $row['monthly_average_display'] ?? '0',
                $row['last_sale_display'] ?? '-',
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
