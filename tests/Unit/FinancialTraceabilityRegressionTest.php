<?php

namespace Tests\Unit;

use App\Exports\SalesReportExport;
use Tests\TestCase;

class FinancialTraceabilityRegressionTest extends TestCase
{
    public function test_settled_credit_accounts_remain_available_in_the_history(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Ventas/EmployeeCreditAccountController.php'));

        $this->assertStringContainsString("'status' => ['nullable', 'in:pending,settled']", $controller);
        $this->assertStringContainsString("->when(\$status !== ''", $controller);
        $this->assertStringContainsString("'status_label' => \$charge->status === 'paid' ? 'Pagada' : 'Pendiente'", $controller);
        $this->assertStringContainsString("'applied_sales' => \$payment->allocations", $controller);
    }

    public function test_credit_payments_keep_the_real_cash_box_and_branch(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Ventas/EmployeeCreditAccountController.php'));
        $model = file_get_contents(app_path('Models/EmployeeCreditPayment.php'));

        $this->assertStringContainsString("'cash_box_number' => ['required', 'string', 'max:10']", $controller);
        $this->assertStringContainsString("'cash_box_number' => (string) \$data['cash_box_number']", $controller);
        $this->assertStringContainsString('public function branch()', $model);
    }

    public function test_sales_report_uses_one_row_per_payment_and_the_same_rows_on_screen_and_export(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Ventas/SalesReportController.php'));

        $this->assertStringContainsString('paginateReportRows($request, $this->salesReportRows($filters)', $controller);
        $this->assertStringContainsString('->map(fn (EmployeeCreditPayment $payment) => $this->mapEmployeeCreditPaymentRow($payment))', $controller);
        $this->assertStringNotContainsString('->flatMap(function (EmployeeCreditPayment $payment)', $controller);
        $this->assertStringContainsString("'related_folios' => \$relatedFolios->all()", $controller);
    }

    public function test_cash_closure_exposes_payment_reconciliation_in_current_and_historical_views(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Ventas/CashRegisterClosureController.php'));

        $this->assertStringContainsString("'credit_payments_total' => round((float) \$creditPayments->sum('amount'), 2)", $controller);
        $this->assertGreaterThanOrEqual(2, substr_count($controller, "'payment_breakdown' =>"));
        $this->assertStringContainsString("'credit_payments' => \$creditPayments->map", $controller);
    }

    public function test_excel_summary_writes_numeric_totals_instead_of_uncalculated_formulas(): void
    {
        $export = new SalesReportExport(collect(), 'Ventas registradas', 'sales', [
            'cash' => 763.43,
            'card' => 0,
            'credit' => 146.37,
            'credit_payments' => 161.97,
            'sold_total' => 909.80,
            'collected_total' => 925.40,
        ]);

        $summaryRows = $export->sheets()[0]->array();
        $totals = array_column(array_slice($summaryRows, 5, 6), 1);

        $this->assertSame([763.43, 0.0, 146.37, 161.97, 909.80, 925.40], $totals);
        $this->assertNotContains('=SUM', $totals);
    }
}
