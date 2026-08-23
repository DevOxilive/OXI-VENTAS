<?php

namespace App\Exports;

use App\Exports\Sheets\SalesReportSheet;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class SalesReportExport implements WithMultipleSheets
{
    public function __construct(
        protected Collection $rows,
        protected string $title = 'Ventas',
        protected string $reportType = 'products',
    ) {}

    public function sheets(): array
    {
        if ($this->reportType === 'sales') {
            return [$this->summarySheet(), $this->detailsSheet()];
        }

        return [new SalesReportSheet(
            $this->singleSheetRows(),
            $this->title,
            currencyColumns: $this->reportType === 'sale-detail' ? ['H', 'I', 'J'] : [],
        )];
    }

    private function summarySheet(): SalesReportSheet
    {
        $rows = $this->sicarHeaderRows();
        $rows[] = ['Concepto', 'Importe'];
        $lastRow = max($this->detailLastRow(), 6);
        $detailSheet = "'Detalle de ventas'";
        $rows = [...$rows,
            ['Ventas en efectivo', "=SUM({$detailSheet}!H6:H{$lastRow})"],
            ['Ventas con tarjeta', "=SUM({$detailSheet}!I6:I{$lastRow})"],
            ['Ventas a crédito', "=SUM({$detailSheet}!J6:J{$lastRow})"],
            ['Abonos a crédito', "=SUM({$detailSheet}!K6:K{$lastRow})"],
            ['Total vendido', '=B6+B7+B8'],
            ['Total cobrado', '=B6+B7+B9'],
        ];

        return new SalesReportSheet(
            $rows,
            'Resumen',
            headerRow: 5,
            currencyColumns: ['B'],
            columnWidths: ['A' => 31, 'B' => 19, 'C' => 14, 'D' => 16, 'E' => 16, 'F' => 14, 'G' => 14, 'H' => 17, 'I' => 14, 'J' => 19],
            hasFilters: false,
            currencyStartRow: 6,
            highlightRows: [10, 11],
            hasTitleRow: true,
            metadataLabelCells: $this->metadataLabelCells(),
        );
    }

    private function detailsSheet(): SalesReportSheet
    {
        $rows = $this->sicarHeaderRows();
        $rows[] = ['Fecha', 'Usuario', 'Producto', 'Pza/Kg', 'Precio', 'Importe', 'Estado', 'Efectivo', 'Tarjeta', 'Crédito', 'Abono'];
        $sectionRows = [];
        $ticketRows = [];
        $rowsByDate = $this->rows
            ->sortBy(fn (array $row) => ($row['date_sort'] ?? '').'|'.($row['operation_sort'] ?? ''))
            ->groupBy('date_only');

        foreach ($rowsByDate as $date => $operations) {
            $excelDate = $this->excelDate($operations->first()['date_sort'] ?? null);
            $sectionRows[] = count($rows) + 1;
            $rows[] = [$excelDate, "Fecha de operación: {$date}"];

            foreach ($operations as $operation) {
                $ticketRows[] = count($rows) + 1;
                $rows[] = [
                    $excelDate,
                    'Número de ticket:',
                    $operation['folio'] ?? 'Sin folio',
                    $this->operationLabel($operation),
                    $operation['payment_folio'] ?? '',
                    'Sucursal:',
                    $operation['branch'] ?? '-',
                    'Caja:',
                    $operation['cash_box'] ?? '-',
                    'Importe total:',
                    (float) ($operation['total'] ?? 0),
                ];

                foreach ($operation['details'] ?? [] as $detail) {
                    $row = [
                        $excelDate,
                        $operation['seller'] ?? 'Sin usuario',
                        $detail['product'] ?? 'Producto sin nombre',
                        $detail['quantity_display'] ?? '0',
                        (float) ($detail['unit_price'] ?? 0),
                        (float) ($detail['report_amount'] ?? $detail['subtotal'] ?? 0),
                        $operation['status_label'] ?? 'Completada',
                        0.0,
                        0.0,
                        0.0,
                        0.0,
                    ];
                    $row[$this->paymentColumnIndex($operation)] = (float) ($detail['report_amount'] ?? $detail['subtotal'] ?? 0);
                    $rows[] = $row;
                }
            }
        }

        return new SalesReportSheet(
            $rows,
            'Detalle de ventas',
            headerRow: 5,
            currencyColumns: ['E', 'F', 'H', 'I', 'J', 'K'],
            dateColumns: ['A'],
            columnWidths: ['A' => 13, 'B' => 24, 'C' => 40, 'D' => 13, 'E' => 14, 'F' => 14, 'G' => 18, 'H' => 14, 'I' => 14, 'J' => 14, 'K' => 14],
            currencyStartRow: 6,
            sectionRows: $sectionRows,
            ticketRows: $ticketRows,
            hasTitleRow: true,
            metadataLabelCells: $this->metadataLabelCells(),
        );
    }

    private function sicarHeaderRows(): array
    {
        [$from, $to] = $this->dateRangeLabels();

        return [
            ['Reporte General de Ventas', '', '', '', '', '', 'Periodo:', $from.' 00:00', '-', $to.' 23:59'],
            ['Documento:', 'Todos', '', '', '', '', 'Detalle:', 'Sí'],
            ['Cliente:', 'Todos', '', 'Estado:', 'Vigente', '', 'Orden:', 'Fecha'],
            ['Vendedor:', 'Todos', '', 'Usuario:', 'Todos', '', 'Caja:', 'Todas'],
        ];
    }

    private function metadataLabelCells(): array
    {
        return ['G1', 'A2', 'G2', 'A3', 'D3', 'G3', 'A4', 'D4', 'G4'];
    }

    private function operationLabel(array $operation): string
    {
        $method = $operation['payment_method'] ?? 'Sin método';

        return ($operation['operation_type'] ?? 'sale') === 'payment'
            ? 'Abono '.$method
            : 'Venta '.$method;
    }

    private function paymentColumnIndex(array $operation): int
    {
        if (($operation['operation_type'] ?? 'sale') === 'payment') {
            return 10;
        }

        $method = str($operation['payment_method'] ?? '')->lower()->ascii()->toString();

        return match (true) {
            str_contains($method, 'efectivo') => 7,
            str_contains($method, 'tarjeta') || str_contains($method, 'debito') || str_contains($method, 'credito') && ! str_contains($method, 'empleado') => 8,
            default => 9,
        };
    }

    private function excelDate(?string $date): ?float
    {
        return filled($date) ? ExcelDate::PHPToExcel(new \DateTime($date)) : null;
    }

    private function detailLastRow(): int
    {
        $rows = 5;
        $groups = $this->rows->groupBy('date_only');

        foreach ($groups as $operations) {
            $rows++;
            foreach ($operations as $operation) {
                $rows += 1 + count($operation['details'] ?? []);
            }
        }

        return $rows;
    }

    private function singleSheetRows(): array
    {
        if ($this->reportType === 'sale-detail') {
            return collect([[
                'Folio', 'Fecha', 'Producto', 'Código', 'Presentación', 'Cantidad visual',
                'Cantidad base descontada', 'Precio unitario', 'Descuento', 'Subtotal',
            ]])
                ->merge($this->rows->flatMap(fn ($sale) => collect($sale['details'] ?? [])->map(fn ($detail) => [
                    $sale['folio'] ?? '-', $sale['date_display'] ?? '-', $detail['product'] ?? '-',
                    $detail['code'] ?? '-', $detail['presentation'] ?? '-', $detail['quantity_display'] ?? '0',
                    $detail['base_quantity_display'] ?? '0', (float) ($detail['unit_price'] ?? 0),
                    (float) ($detail['discount_amount'] ?? 0), (float) ($detail['subtotal'] ?? 0),
                ])))
                ->values()
                ->toArray();
        }

        return collect([[
            'Producto', 'Código', 'Sucursal', 'Stock actual', 'Piezas/kg vendidas en el periodo',
            'Promedio mensual vendido', 'Última venta',
        ]])
            ->merge($this->rows->map(fn ($row) => [
                $row['product'] ?? '-', $row['code'] ?? '-', $row['branch'] ?? '-',
                $row['current_stock_display'] ?? '0', $row['sold_quantity_display'] ?? '0',
                $row['monthly_average_display'] ?? '0', $row['last_sale_display'] ?? '-',
            ]))
            ->toArray();
    }

    private function dateRangeLabels(): array
    {
        $dates = $this->rows->pluck('date_only')->filter()->sort()->values();

        return [$dates->first() ?: '-', $dates->last() ?: '-'];
    }
}
