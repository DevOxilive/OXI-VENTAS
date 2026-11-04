<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BranchInventoryProductViewInterfaceTest extends TestCase
{
    public function test_inventory_exposes_all_catalog_actions(): void
    {
        $tableConfig = file_get_contents(resource_path('js/config/TableConfigs/inventoryTableConfig.js'));
        $toolbarConfig = file_get_contents(resource_path('js/config/ToolbarConfigs/inventoryToolbarConfig.js'));
        $menuConfig = file_get_contents(resource_path('js/config/menuConfig.js'));
        $page = file_get_contents(resource_path('js/Pages/Inventory/BranchInventory.vue'));
        $inventory = file_get_contents(resource_path('js/Composables/Inventory/useBranchInventory.js'));
        $controller = file_get_contents(app_path('Http/Controllers/Inventory/BranchInventoryController.php'));

        $this->assertStringContainsString('id: "view"', $tableConfig);
        $this->assertStringContainsString('permission: "inventory.products.view"', $tableConfig);
        $this->assertStringContainsString('permission: "inventory.products.delete"', $tableConfig);
        $this->assertStringContainsString('permission: "inventory.products.update"', $tableConfig);
        $this->assertStringContainsString('ProductModal', $page);
        $this->assertStringContainsString("action === 'view' && can('inventory.products.view')", $page);
        $this->assertStringContainsString("openProductSnapshot(row, 'view')", $page);
        $this->assertStringContainsString("route('inventory.branches.products.snapshot'", $page);
        $this->assertStringContainsString("if (mode === 'view') showProductViewModal.value = true", $page);
        $this->assertStringContainsString(':categoriesDB="catalogCategoriesDB"', $page);
        $this->assertStringContainsString(':productDepartmentsDB="catalogProductDepartmentsDB"', $page);
        $this->assertStringContainsString("'catalogCategoriesDB' => fn () => \$this->catalogCategoryOptions()", $controller);
        $this->assertStringContainsString("'catalogProductDepartmentsDB' => fn () => \$this->catalogProductDepartmentOptions()", $controller);
        $this->assertStringContainsString('orderByRelevance: true', $controller);
        $this->assertStringContainsString('resetExistingOrder: true', $controller);
        $this->assertStringNotContainsString('Caducidad', $toolbarConfig);
        $this->assertStringNotContainsString('Sin rotación', $toolbarConfig);
        $this->assertStringNotContainsString('label: "Estado"', $toolbarConfig);
        $this->assertStringNotContainsString('clearSearchForFilter', $page);
        $this->assertStringNotContainsString('clearSearchForFilter', $inventory);
        $this->assertStringNotContainsString('skipNextSearchReload', $inventory);
        $this->assertStringContainsString('nextTick(focusInventorySearch)', $page);
        $this->assertStringContainsString('onMounted(focusInventorySearch)', $page);
        $this->assertStringContainsString('focusSearch()', $page);
        $this->assertStringNotContainsString('select-search-on-enter', $page);
        $this->assertStringContainsString('onReloaded: focusInventorySearch', $page);
        $this->assertStringContainsString('onFinish: () => onReloaded?.()', $inventory);
        $this->assertStringContainsString('product_id: item.product_id', $inventory);
        $this->assertStringContainsString('id: "create"', $toolbarConfig);
        $this->assertStringContainsString("can('inventory.products.create')", $page);
        $this->assertStringContainsString('mode="create"', $page);
        $this->assertStringContainsString("action === 'delete' && can('inventory.products.delete')", $page);
        $this->assertStringContainsString('deleteInventoryProduct(row)', $page);
        $this->assertStringContainsString("action === 'edit' && can('inventory.products.update')", $page);
        $this->assertStringContainsString("route('inventory.branches.products.snapshot'", $page);
        $this->assertStringContainsString('mode="edit"', $page);
        $this->assertStringContainsString('Inventario-Producto', $toolbarConfig);
        $this->assertStringContainsString('text: "Inventario-Producto"', $menuConfig);
        $this->assertStringNotContainsString('text: "Productos"', $menuConfig);
        $this->assertFalse(Route::has('inventory.branches.products.index'));
        $this->assertFileDoesNotExist(resource_path('js/Pages/Inventory/Home.vue'));
    }
}
