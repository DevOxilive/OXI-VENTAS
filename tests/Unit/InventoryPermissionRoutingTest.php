<?php

namespace Tests\Unit;

use App\Http\Controllers\Inventory\BranchInventoryController;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InventoryPermissionRoutingTest extends TestCase
{
    #[DataProvider('inventoryRouteProvider')]
    public function test_inventory_actions_require_their_specific_permission(
        string $routeName,
        string $permissionMiddleware
    ): void {
        $route = app('router')->getRoutes()->getByName($routeName);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertContains($permissionMiddleware, $route->gatherMiddleware());
    }

    public static function inventoryRouteProvider(): array
    {
        return [
            'configurar stock' => [
                'inventory.branch-inventory.update-config',
                'permission:inventory.branches.config.update',
            ],
            'editar lote' => [
                'inventory.product-batches.update',
                'permission:inventory.branches.batches.update',
            ],
            'registrar movimiento' => [
                'inventory.stock-movements.store',
                'permission:inventory.branches.stock-in,inventory.branches.stock-out,inventory.branches.stock-adjust',
            ],
            'abrir transferencias' => [
                'inventory.transfers',
                'permission:inventory.branches.stock-out',
            ],
            'abrir ajustes' => [
                'inventory.adjustments',
                'permission:inventory.branches.stock-adjust',
            ],
        ];
    }

    public function test_legacy_inventory_action_pages_redirect_to_the_real_branch_inventory(): void
    {
        foreach (['inventory.transfers', 'inventory.adjustments'] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);

            $this->assertSame(
                BranchInventoryController::class.'@landing',
                $route->getActionName()
            );
        }
    }
}
