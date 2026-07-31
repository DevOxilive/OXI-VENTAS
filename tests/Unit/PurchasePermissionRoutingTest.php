<?php

namespace Tests\Unit;

use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PurchasePermissionRoutingTest extends TestCase
{
    #[DataProvider('purchaseRouteProvider')]
    public function test_purchase_routes_require_their_specific_permission(
        string $routeName,
        string $permissionMiddleware
    ): void {
        $route = app('router')->getRoutes()->getByName($routeName);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertContains($permissionMiddleware, $route->gatherMiddleware());
    }

    public static function purchaseRouteProvider(): array
    {
        return [
            'crear lista' => [
                'inventory.branches.purchase-reports.store',
                'permission:sales.purchase-lists.create',
            ],
            'editar lista' => [
                'inventory.branches.purchase-reports.update',
                'permission:sales.purchase-lists.update',
            ],
            'eliminar lista' => [
                'inventory.branches.purchase-reports.destroy',
                'permission:sales.purchase-lists.delete',
            ],
            'confirmar recepcion' => [
                'inventory.branches.purchase-reports.complete',
                'permission:sales.purchase-orders.receive',
            ],
            'ver orden de sucursal' => [
                'inventory.branches.reports.purchase-orders.source-orders.show',
                'permission:inventory.purchase-orders.source.view',
            ],
            'editar orden de sucursal' => [
                'inventory.branches.reports.purchase-orders.source-orders.update',
                'permission:inventory.purchase-orders.source.update',
            ],
            'revisar orden de sucursal' => [
                'inventory.branches.reports.purchase-orders.source-orders.review',
                'permission:inventory.purchase-orders.source.review',
            ],
            'transferir orden de sucursal' => [
                'inventory.branches.reports.purchase-orders.source-orders.transfer',
                'permission:inventory.purchase-orders.source.transfer',
            ],
            'crear orden general' => [
                'inventory.branches.reports.purchase-orders.consolidate',
                'permission:inventory.purchase-orders.general.create',
            ],
            'editar orden general' => [
                'inventory.branches.reports.purchase-orders.update',
                'permission:inventory.purchase-orders.general.update',
            ],
            'aplicar orden general' => [
                'inventory.branches.reports.purchase-orders.complete',
                'permission:inventory.purchase-orders.general.complete',
            ],
        ];
    }

    public function test_administrators_receive_sales_purchase_order_tracking_permissions(): void
    {
        $migration = file_get_contents(database_path(
            'migrations/2026_07_31_092000_grant_sales_purchase_order_tracking_to_administrators.php'
        ));

        $this->assertStringContainsString("'Administrador'", $migration);
        $this->assertStringContainsString("'Super Administrador'", $migration);
        $this->assertStringContainsString("'sales.purchase-orders.view'", $migration);
        $this->assertStringContainsString("'sales.purchase-orders.receive'", $migration);
    }
}
