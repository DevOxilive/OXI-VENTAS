<?php

namespace App\Exports;

use App\Exports\Sheets\SalesReportSheet;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class CashClosureReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly Collection $rows,
        private readonly string $branchName,
        private readonly array $filters,
        private readonly array $totals,
    ) {}

    public function sheets(): array
    {
        $rows = [
            ['Reporte General de Cortes', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', ''],
            ['Sucursal:', $this->branchName, '', '', '', '', 'Periodo:', $this->periodLabel(), '', '', '', '', '', '', '', '', '', ''],
            ['Folio:', $this->filters['folio'] ?: 'Todos', '', '', '', '', 'Usuario:', $this->filters['user_id'] ?: 'Todos', '', '', '', '', '', '', '', '', '', ''],
            ['Estado:', $this->statusLabel(), '', '', '', '', 'Orden:', 'Fecha de corte', '', '', '', '', '', '', '', '', '', ''],
            [
                'Fecha', 'Desglose', 'Pagos en efectivo', 'Pagos con tarjeta', 'Abonos en efectivo',
                'Abonos con tarjeta', 'Otros cobros', 'Devol. efectivo', 'Devol. tarjeta',
                'Efectivo esperado', 'Tarjeta esperada', 'Efectivo contado', 'Tarjeta capturada',
                'Se deja en caja', 'Retiro de efectivo', 'Dif. efectivo', 'Dif. tarjeta', 'Total del corte',
            ],
        ];
        $sectionRows = [];
        $ticketRows = [];

        foreach ($this->rows as $row) {
            $sectionRows[] = count($rows) + 1;
            $rows[] = [$row['date'], "Fecha de corte: {$row['date']}", 'Responsable:', $row['user']];

            $ticketRows[] = count($rows) + 1;
            $rows[] = [
                $row['date'], 'Folio de corte:', $row['folio'], 'Sucursal:', $this->branchName,
                'Caja:', $row['cash_box'], 'Importe total:', $row['collected_total'],
            ];

            $rows[] = [
                $row['date'], 'Desglose del corte',
                $row['cash_payments'], $row['card_payments'], $row['cash_credit_payments'], $row['card_credit_payments'],
                $row['other_payments'], $row['refund_cash'], $row['refund_card'], $row['expected_cash'], $row['expected_card'],
                $row['counted_cash'], $row['counted_card'], $row['cash_left'], $row['cash_withdrawal'],
                $row['cash_difference'], $row['card_difference'], $row['collected_total'],
            ];
        }

        $rows[] = [
            'Totales', '',
            $this->totals['cash_payments'], $this->totals['card_payments'], $this->totals['cash_credit_payments'], $this->totals['card_credit_payments'],
            $this->totals['other_payments'], $this->totals['refund_cash'], $this->totals['refund_card'], $this->totals['expected_cash'],
            $this->totals['expected_card'], $this->totals['counted_cash'], $this->totals['counted_card'], $this->totals['cash_left'],
            $this->totals['cash_withdrawal'], $this->totals['cash_difference'], $this->totals['card_difference'], $this->totals['collected_total'],
        ];

        return [new SalesReportSheet(
            $rows,
            'Cortes',
            headerRow: 5,
            currencyColumns: ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R'],
            columnWidths: [
                'A' => 13, 'B' => 24, 'C' => 17, 'D' => 17, 'E' => 17, 'F' => 17, 'G' => 15,
                'H' => 16, 'I' => 16, 'J' => 18, 'K' => 18, 'L' => 18, 'M' => 18, 'N' => 16,
                'O' => 19, 'P' => 15, 'Q' => 15, 'R' => 17,
            ],
            currencyStartRow: 7,
            highlightRows: [count($rows)],
            sectionRows: $sectionRows,
            ticketRows: $ticketRows,
            hasTitleRow: true,
            metadataLabelCells: ['A2', 'G2', 'A3', 'G3', 'A4', 'G4'],
        )];
    }

    private function periodLabel(): string
    {
        $from = $this->filters['date_from'] ?: '-';
        $to = $this->filters['date_to'] ?: '-';

        return "{$from} a {$to}";
    }

    private function statusLabel(): string
    {
        return match ($this->filters['status']) {
            'balanced' => 'Cuadrado',
            'difference' => 'Con diferencia',
            default => 'Todos',
        };
    }
}
