<?php

namespace Tests\Unit;

use App\Exports\CashClosureReportExport;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CashClosureReportExportTest extends TestCase
{
    public function test_it_creates_a_horizontal_cash_closure_sheet_with_the_requested_payment_columns(): void
    {
        $export = new CashClosureReportExport(
            new Collection([[
                'date' => '12/11/2026', 'folio' => '25-2026', 'cash_box' => '1', 'user' => 'Cajero',
                'cash_payments' => 10, 'card_payments' => 20, 'cash_credit_payments' => 30, 'card_credit_payments' => 40,
                'other_payments' => 0, 'refund_cash' => 0, 'refund_card' => 0, 'expected_cash' => 40, 'expected_card' => 60,
                'counted_cash' => 40, 'counted_card' => 60, 'cash_left' => 10, 'cash_withdrawal' => 30,
                'cash_difference' => 0, 'card_difference' => 0, 'collected_total' => 100,
            ]]),
            'Matriz',
            ['folio' => '', 'user_id' => '', 'status' => '', 'date_from' => '', 'date_to' => ''],
            array_fill_keys([
                'cash_payments', 'card_payments', 'cash_credit_payments', 'card_credit_payments', 'other_payments',
                'refund_cash', 'refund_card', 'expected_cash', 'expected_card', 'counted_cash', 'counted_card',
                'cash_left', 'cash_withdrawal', 'cash_difference', 'card_difference', 'collected_total',
            ], 0),
        );

        $sheet = $export->sheets()[0];
        $rows = $sheet->array();

        $this->assertSame('Fecha', $rows[4][0]);
        $this->assertSame('Total del corte', $rows[4][17]);
        $this->assertSame('Fecha de corte: 12/11/2026', $rows[5][1]);
        $this->assertSame('Responsable:', $rows[5][2]);
        $this->assertSame('Cajero', $rows[5][3]);
        $this->assertSame('Folio de corte:', $rows[6][1]);
        $this->assertSame('25-2026', $rows[6][2]);
        $this->assertSame('Desglose del corte', $rows[7][1]);
    }
}
