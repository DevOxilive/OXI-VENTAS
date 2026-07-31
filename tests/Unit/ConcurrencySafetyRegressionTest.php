<?php

namespace Tests\Unit;

use Tests\TestCase;

class ConcurrencySafetyRegressionTest extends TestCase
{
    public function test_physical_count_applies_the_difference_from_the_snapshot(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Audits/PhysicalCountController.php'));

        $this->assertStringContainsString('$snapshotBatchQuantity', $controller);
        $this->assertStringContainsString('$difference = $countedBatchQuantity - $snapshotBatchQuantity;', $controller);
        $this->assertStringContainsString('$newBatchQuantity = $currentBatchQuantity + $difference;', $controller);
    }

    public function test_batch_edit_only_creates_one_movement_detail(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Inventory/ProductBatchController.php'));

        $this->assertSame(1, substr_count($controller, 'StockMovementBatch::create(['));
    }

    public function test_critical_write_routes_use_idempotency_middleware(): void
    {
        foreach (['ventas.store', 'ventas.cash-closures.store', 'inventory.stock-movements.store', 'audits.physical-counts.store', 'audits.physical-counts.apply-adjustments'] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);
            $this->assertNotNull($route, "No se encontro la ruta {$routeName}.");
            $this->assertContains('idempotent', $route->gatherMiddleware(), "La ruta {$routeName} no protege envios duplicados.");
        }
    }

    public function test_editable_master_records_send_and_validate_a_record_version(): void
    {
        $trait = file_get_contents(app_path('Http/Controllers/Concerns/ValidatesRecordVersion.php'));
        $frontends = [
            resource_path('js/Pages/Systems/Branches.vue'),
            resource_path('js/Pages/Systems/Users.vue'),
            resource_path('js/Composables/HumanResources/useEmployeeForm.js'),
            resource_path('js/Components/Inventory/ProductModal.vue'),
            resource_path('js/Pages/HumanResources/AttendanceSchedules.vue'),
            resource_path('js/Pages/Printers/Tickets.vue'),
            resource_path('js/Pages/Printers/Labels.vue'),
            resource_path('js/Pages/Ventas/CashRegisterClosureReports.vue'),
        ];

        $this->assertStringContainsString("'record_version' => ['required', 'date']", $trait);
        $this->assertStringContainsString('lockForUpdate()', $trait);

        foreach ($frontends as $frontend) {
            $this->assertStringContainsString('record_version', file_get_contents($frontend), basename($frontend).' no envia la version del registro.');
        }
    }
}
