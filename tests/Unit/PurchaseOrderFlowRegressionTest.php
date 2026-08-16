<?php

namespace Tests\Unit;

use Tests\TestCase;

class PurchaseOrderFlowRegressionTest extends TestCase
{
    public function test_source_order_actions_use_the_source_branch(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Inventory/PurchaseOrders.vue'));
        $controller = file_get_contents(app_path('Http/Controllers/Inventory/GeneralPurchaseOrderController.php'));

        $this->assertStringContainsString('branch: order.branch_id', $page);
        $this->assertStringContainsString(':branch-id="editingSourceOrder.branch.id"', $page);
        $this->assertStringContainsString('$purchaseOrder->loadMissing(\'branch\');', $controller);
        $this->assertStringContainsString('$purchaseOrder->branch,', $controller);
    }

    public function test_completing_an_already_completed_general_order_redirects_cleanly(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Inventory/GeneralPurchaseOrderController.php'));
        $capturePage = file_get_contents(resource_path('js/Pages/Inventory/PurchaseOrderCapture.vue'));

        $this->assertStringContainsString(
            'if ($generalPurchaseOrder->status === GeneralPurchaseOrder::STATUS_COMPLETED)',
            $controller
        );
        $this->assertStringContainsString('completedOrdersRedirect($branch)', $controller);
        $this->assertStringContainsString('if (completing.value) return', $capturePage);
        $this->assertStringContainsString(':disabled="completing"', $capturePage);
    }

    public function test_purchase_order_back_action_returns_to_branch_inventory(): void
    {
        $composable = file_get_contents(resource_path('js/Composables/Inventory/usePurchaseOrders.js'));
        $toolbar = file_get_contents(resource_path('js/config/ToolbarConfigs/purchaseOrdersToolbarConfig.js'));

        $this->assertStringContainsString("route('inventory.branches.inventory'", $composable);
        $this->assertStringNotContainsString("route('inventory.branches.reports'", $composable);
        $this->assertStringContainsString("backLabel: 'Inventario'", $toolbar);
    }

    public function test_legacy_purchase_list_detail_does_not_render_a_missing_page(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Inventory/PurchaseReportController.php'));

        $this->assertStringNotContainsString("Inertia::render('Inventory/PurchaseReportShow'", $controller);
        $this->assertStringContainsString(
            "redirect()->route('inventory.branches.purchase-reports.index'",
            $controller
        );
    }
}
