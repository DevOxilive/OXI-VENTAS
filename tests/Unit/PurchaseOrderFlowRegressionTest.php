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

    public function test_purchase_list_generation_creates_real_order_and_deletes_draft(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Inventory/PurchaseReportController.php'));

        $this->assertStringContainsString('public function generate(', $controller);
        $this->assertStringContainsString('PurchaseOrder $purchaseReport', $controller);
        $this->assertStringContainsString('$lockedOrder = $this->lockCurrentVersion($request, $purchaseReport);', $controller);
        $this->assertStringContainsString('$generatedOrder = $lockedOrder->replicate', $controller);
        $this->assertStringContainsString("'folio' => \$this->makeGeneratedFolio()", $controller);
        $this->assertStringContainsString("'status' => PurchaseOrder::STATUS_GENERATED", $controller);
        $this->assertStringContainsString('$lockedOrder->delete();', $controller);
        $this->assertStringNotContainsString('registerOrder($generatedOrder', $controller);
        $this->assertStringContainsString("->where('status', PurchaseOrder::STATUS_DRAFT)", $controller);
    }

    public function test_purchase_order_folio_uses_shared_numeric_sequence(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Inventory/PurchaseReportController.php'));

        $this->assertStringContainsString('private function makeGeneratedFolio(): string', $controller);
        $this->assertStringContainsString("\$prefix = 'SC';", $controller);
        $this->assertStringContainsString("where('status', '!=', PurchaseOrder::STATUS_DRAFT)", $controller);
        $this->assertStringContainsString("->where('folio', 'like', \"{\$prefix}-%\")", $controller);
        $this->assertStringContainsString("return sprintf('%s-%d', \$prefix, max(1, \$nextSequence + 1));", $controller);
    }

    public function test_general_purchase_orders_use_the_requested_folio_and_show_its_creator_to_sales(): void
    {
        $service = file_get_contents(app_path('Services/PurchaseCycleService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Inventory/PurchaseReportController.php'));
        $modal = file_get_contents(resource_path('js/Components/Inventory/PurchaseReports/BranchPurchaseOrderModal.vue'));

        $this->assertStringContainsString("\$prefix = 'OC-General';", $service);
        $this->assertStringContainsString("return sprintf('%s-%d', \$prefix, max(1, \$lastSequence + 1));", $service);
        $this->assertStringContainsString("'generalOrder.creator:id,name'", $controller);
        $this->assertStringContainsString("'general_order' => \$purchaseReport->generalOrder", $controller);
        $this->assertStringContainsString('props.order.general_order.created_by?.name', $modal);
    }

    public function test_source_order_table_omits_product_and_piece_totals_and_notes_are_multiline(): void
    {
        $table = file_get_contents(resource_path('js/config/TableConfigs/generalPurchaseSourceOrdersTableConfig.js'));
        $modal = file_get_contents(resource_path('js/Components/Inventory/PurchaseReports/BranchPurchaseOrderModal.vue'));

        $this->assertStringNotContainsString("key: 'items_count'", $table);
        $this->assertStringNotContainsString("key: 'requested_quantity'", $table);
        $this->assertStringContainsString(':rows="3"', $modal);
        $this->assertStringContainsString('min-h-20 resize-y', $modal);
    }

    public function test_purchase_order_notifications_cover_inventory_and_sales_follow_up(): void
    {
        $purchaseReportController = file_get_contents(app_path('Http/Controllers/Inventory/PurchaseReportController.php'));
        $generalController = file_get_contents(app_path('Http/Controllers/Inventory/GeneralPurchaseOrderController.php'));
        $salesOrdersPage = file_get_contents(resource_path('js/Pages/Inventory/BranchPurchaseOrders.vue'));
        $inventoryOrdersPage = file_get_contents(resource_path('js/Pages/Inventory/PurchaseOrders.vue'));
        $notificationModal = file_get_contents(resource_path('js/Components/Notifications/FloatingNotificationModal.vue'));
        $seenState = file_get_contents(resource_path('js/Composables/useNotificationSeenState.js'));

        $this->assertStringContainsString('Solicitud enviada a Compras correctamente.', $purchaseReportController);
        $this->assertStringNotContainsString("'purchase_order_assigned'", $purchaseReportController);
        $this->assertStringContainsString('purchaseOrderNotificationSummary', $generalController);
        $this->assertStringContainsString('refreshPurchaseWorkflow()', $inventoryOrdersPage);
        $this->assertStringContainsString("refreshRealtimeProps(page, ['generation']", $inventoryOrdersPage);
        $this->assertStringContainsString('purchaseWorkflowChanged', $inventoryOrdersPage);
        $this->assertStringContainsString('purchase_order_reviewed', $generalController);
        $this->assertStringContainsString('purchase_order_edited', $generalController);
        $this->assertStringContainsString('salesPurchaseOrderNotificationSummary', $purchaseReportController);
        $this->assertStringContainsString("'purchase_order_reviewed'", $salesOrdersPage);
        $this->assertStringContainsString("'purchase_order_edited'", $salesOrdersPage);
        $this->assertStringContainsString("refreshRealtimeProps(page, ['ordersDB', 'notificationSummary'])", $salesOrdersPage);
        $this->assertStringContainsString('markNotificationsSeen()', $salesOrdersPage);
        $this->assertStringContainsString('FloatingNotificationModal', $salesOrdersPage);
        $this->assertStringContainsString('GlobalModal', $notificationModal);
        $this->assertStringContainsString('unreadCount', $seenState);
        $this->assertStringContainsString("defineEmits(['close', 'select', 'dismiss'])", $notificationModal);
        $this->assertStringContainsString('Borrar notificación', $notificationModal);
        $this->assertStringContainsString('dismissNotification', $seenState);
        $this->assertStringContainsString('visibleItems', $seenState);
    }

    public function test_purchase_list_delete_sends_record_version(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Inventory/PurchaseReport.vue'));

        $this->assertStringContainsString('router.delete(', $page);
        $this->assertStringContainsString('record_version: draft.record_version || draft.updated_at || null', $page);
    }

    public function test_general_purchase_capture_supports_current_product_presentations(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Inventory/PurchaseOrderCapture.vue'));
        $controller = file_get_contents(app_path('Http/Controllers/Inventory/GeneralPurchaseOrderController.php'));
        $service = file_get_contents(app_path('Services/PurchaseCycleService.php'));

        foreach (['Pieza', 'Paquete', 'Caja', 'Kilo', 'Gramo'] as $presentation) {
            $this->assertStringContainsString("value: '{$presentation}'", $page);
        }
        $this->assertStringContainsString('in:Pieza,Paquete,Caja,Kilo,Gramo', $controller);
        $this->assertStringContainsString("\$presentation === 'Gramo'", $service);
        $this->assertStringContainsString("'Paquete' => (int) \$product->pieces_per_package", $service);
        $this->assertStringContainsString("\$packageQuantity / 1000", $service);
        $this->assertStringContainsString('QuantityStepper', $page);
        $this->assertStringContainsString('purchaseSummary', $page);
        $this->assertStringContainsString('title="Almacén central"', $page);
        $this->assertStringContainsString("lot.lot_number || 'Sin lote'", $page);
        $this->assertStringContainsString('general_purchase_order_item_batches', file_get_contents(database_path('migrations/2026_11_25_130000_create_general_purchase_order_item_batches_table.php')));
        $this->assertStringContainsString("'received_quantity' => null", $service);
        $this->assertStringContainsString('syncPlannedBatches', $service);
    }
}
