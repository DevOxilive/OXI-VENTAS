<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\QzTrayController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\TicketTemplateController;
use App\Http\Controllers\Audits\PhysicalCountController;
use App\Http\Controllers\Audits\PhysicalCountReportController;
use App\Http\Controllers\Inventory\ReportController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\ProductBatchController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Inventory\PurchaseReportController;
use App\Http\Controllers\Inventory\BranchInventoryController;
use App\Http\Controllers\Inventory\CategoryController;
use App\Http\Controllers\Ventas\SalesController;

/*
|--------------------------------------------------------------------------
| PÚBLICO
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/register', function () {
    return Inertia::render('Auth/Register', [
        'roles' => DB::table('roles')
            ->where('name', '!=', 'Administrador')
            ->orderBy('name')
            ->get(),

        'branches' => \App\Models\Branch::where('active', true)
            ->orderBy('name')
            ->get(),
    ]);
})->name('register');

/*
|--------------------------------------------------------------------------
| RUTAS PROTEGIDAS
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD
    |--------------------------------------------------------------------------
    */

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/qz/certificate', [QzTrayController::class, 'certificate'])
        ->name('qz.certificate');

    Route::post('/qz/sign', [QzTrayController::class, 'sign'])
        ->name('qz.sign');

    /*
    |--------------------------------------------------------------------------
    | SISTEMAS - USUARIOS
    |--------------------------------------------------------------------------
    */

    Route::prefix('systems')->name('systems.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])
            ->middleware('permission:users.view,users.create,users.update,users.delete')
            ->name('users.index');

        Route::post('/users', [UserController::class, 'store'])
            ->middleware('permission:users.create')
            ->name('users.store');

        Route::put('/users/{user}', [UserController::class, 'update'])
            ->middleware('permission:users.update')
            ->name('users.update');

        Route::delete('/users/{user}', [UserController::class, 'destroy'])
            ->middleware('permission:users.delete')
            ->name('users.destroy');

        Route::get('/tickets', function () {
            return redirect()->route('printers.tickets.index');
        })
            ->middleware('permission:systems.tickets.view,systems.tickets.update')
            ->name('tickets.index');

        Route::put('/tickets/{ticketTemplate}', [TicketTemplateController::class, 'update'])
            ->middleware('permission:systems.tickets.update')
            ->name('tickets.update');

        Route::get('/labels', function () {
            return redirect()->route('printers.labels.index');
        })
            ->middleware('permission:systems.labels.view,systems.labels.update')
            ->name('labels.index');
    });

    /*
    |--------------------------------------------------------------------------
    | SISTEMAS - SUCURSALES
    |--------------------------------------------------------------------------
    */

    Route::get('/branches', [BranchController::class, 'index'])
        ->middleware('permission:branches.view,branches.create,branches.update,branches.delete')
        ->name('branches.index');

    Route::post('/branches', [BranchController::class, 'store'])
        ->middleware('permission:branches.create')
        ->name('branches.store');

    Route::put('/branches/{branch}', [BranchController::class, 'update'])
        ->middleware('permission:branches.update')
        ->name('branches.update');

    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])
        ->middleware('permission:branches.delete')
        ->name('branches.destroy');

    /*
    |--------------------------------------------------------------------------
    | CAPITAL HUMANO - EMPLEADOS
    |--------------------------------------------------------------------------
    */

    Route::prefix('human-resources')->name('human-resources.')->group(function () {
        Route::get('/employees', [EmployeeController::class, 'index'])
            ->middleware('permission:employees.view,employees.create,employees.update,employees.delete,files.export')
            ->name('employees.index');

        Route::post('/employees', [EmployeeController::class, 'store'])
            ->middleware('permission:employees.create')
            ->name('employees.store');

        Route::put('/employees/{id}', [EmployeeController::class, 'update'])
            ->middleware('permission:employees.update')
            ->name('employees.update');

        Route::delete('/employees/{id}', [EmployeeController::class, 'destroy'])
            ->middleware('permission:employees.delete')
            ->name('employees.destroy');

        Route::get('/employees/export-excel', [EmployeeController::class, 'exportExcel'])
            ->middleware('permission:files.export')
            ->name('employees.export-excel');
    });

    /*
    |--------------------------------------------------------------------------
    | VENTAS
    |--------------------------------------------------------------------------
    */

    Route::prefix('ventas')
        ->name('ventas.')
        ->group(function () {
            Route::get('/', [SalesController::class, 'index'])
                ->middleware('permission:sales.view,sales.create,sales.update,sales.delete,sales.reports')
                ->name('home');

            Route::post('/', [SalesController::class, 'store'])
                ->middleware('permission:sales.create')
                ->name('store');
        });

    /*
    |--------------------------------------------------------------------------
    | IMPRESORAS - TICKETS
    |--------------------------------------------------------------------------
    */

    Route::prefix('printers')->name('printers.')->group(function () {
        Route::get('/tickets', [TicketTemplateController::class, 'index'])
            ->middleware('permission:systems.tickets.view,systems.tickets.update')
            ->name('tickets.index');

        Route::put('/tickets/{ticketTemplate}', [TicketTemplateController::class, 'update'])
            ->middleware('permission:systems.tickets.update')
            ->name('tickets.update');

        Route::get('/labels', [TicketTemplateController::class, 'labels'])
            ->middleware('permission:systems.labels.view,systems.labels.update')
            ->name('labels.index');

        Route::put('/labels/{ticketTemplate}', [TicketTemplateController::class, 'updateLabel'])
            ->middleware('permission:systems.labels.update')
            ->name('labels.update');
    });

    /*
    |--------------------------------------------------------------------------
    | INVENTARIO - PRODUCTOS / CATEGORÍAS / MOVIMIENTOS
    |--------------------------------------------------------------------------
    */

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/dashboard', fn() => Inertia::render('Inventory/Dashboard'))
            ->middleware('permission:inventory.view,inventory.products.view,inventory.products.create,inventory.products.update,inventory.products.delete,inventory.branches.view,inventory.branches.create,inventory.branches.update,inventory.branches.delete,inventory.purchase-reports.view,inventory.purchase-reports.create,inventory.purchase-reports.update,inventory.purchase-reports.delete')
            ->name('dashboard');

        Route::get('/branches/{branch:slug}/products', [ProductController::class, 'index'])
            ->middleware(
                'permission:inventory.products.view,inventory.products.create,inventory.products.update,inventory.products.delete'
            )
            ->name('branches.products.index');

        Route::get('/branches/{branch:slug}/products/snapshots/{productId}', [ProductController::class, 'snapshot'])
            ->middleware(
                'permission:inventory.products.view,inventory.products.create,inventory.products.update,inventory.products.delete'
            )
            ->name('branches.products.snapshot');

        Route::get('/products/{product}/image', [ProductController::class, 'image'])
            ->middleware(
                'permission:inventory.products.view,inventory.products.create,inventory.products.update,inventory.products.delete'
            )
            ->name('products.image');

        Route::post('/branches/{branch:slug}/products', [ProductController::class, 'store'])
            ->middleware('permission:inventory.products.create')
            ->name('branches.products.store');

        Route::put('/branches/{branch:slug}/products/{product}', [ProductController::class, 'update'])
            ->middleware('permission:inventory.products.update')
            ->name('branches.products.update');

        Route::delete('/branches/{branch:slug}/products/{product}', [ProductController::class, 'destroy'])
            ->middleware('permission:inventory.products.delete')
            ->name('branches.products.destroy');

        Route::post('/categories', [CategoryController::class, 'store'])
            ->middleware('permission:inventory.products.create')
            ->name('categories.store');

        /*
        |--------------------------------------------------------------------------
        | INVENTARIO - INVENTARIO POR SUCURSAL
        |--------------------------------------------------------------------------
        */

        Route::get('/branches/{branch}/inventory', [BranchInventoryController::class, 'show'])
            ->middleware('permission:inventory.branches.view,inventory.branches.create,inventory.branches.update,inventory.branches.delete')
            ->name('branches.inventory');

        Route::get('/branches/{branch}/inventory/realtime-snapshot', [BranchInventoryController::class, 'realtimeSnapshot'])
            ->middleware('permission:inventory.branches.view,inventory.branches.update')
            ->name('branches.inventory.realtime-snapshot');

        Route::post('/branch-inventory', [BranchInventoryController::class, 'store'])
            ->middleware('permission:inventory.branches.create')
            ->name('branch-inventory.store');

        Route::patch('/branch-inventory/{branchProduct}/config', [BranchInventoryController::class, 'updateConfig'])
            ->middleware('permission:inventory.branches.update')
            ->name('branch-inventory.update-config');

        Route::get('/branch-inventory/{branchProduct}/details', [BranchInventoryController::class, 'details'])
            ->middleware('permission:inventory.branches.view,inventory.branches.update')
            ->name('branch-inventory.details');

        Route::put('/product-batches/{productBatch}', [ProductBatchController::class, 'update'])
            ->middleware('permission:inventory.branches.update')
            ->name('product-batches.update');

        Route::get('/stock-movements', [StockMovementController::class, 'index'])
            ->middleware('permission:inventory.branches.view,inventory.branches.update')
            ->name('stock-movements.index');

        Route::post('/stock-movements', [StockMovementController::class, 'store'])
            ->middleware('permission:inventory.branches.create,inventory.branches.update')
            ->name('stock-movements.store');

        Route::get('/movements', [StockMovementController::class, 'index'])
            ->middleware('permission:inventory.branches.view,inventory.branches.update')
            ->name('movements');

        Route::get('/expirations', fn() => Inertia::render('Inventory/Expirations'))
            ->middleware('permission:inventory.view,inventory.branches.view')
            ->name('expirations');

        Route::get('/transfers', fn() => Inertia::render('Inventory/Transfers'))
            ->middleware('permission:inventory.update,inventory.branches.update')
            ->name('transfers');

        Route::get('/adjustments', fn() => Inertia::render('Inventory/Adjustments'))
            ->middleware('permission:inventory.update,inventory.branches.update')
            ->name('adjustments');

        /*
        |--------------------------------------------------------------------------
        | INVENTARIO - REPORTES DE COMPRA
        |--------------------------------------------------------------------------
        */

        Route::get('/branches/{branch}/purchase-reports', [PurchaseReportController::class, 'index'])
            ->middleware('permission:inventory.purchase-reports.view,inventory.purchase-reports.create,inventory.purchase-reports.update,inventory.purchase-reports.delete')
            ->name('branches.purchase-reports.index');

        Route::get('/branches/{branch}/purchase-reports/create', [PurchaseReportController::class, 'create'])
            ->middleware('permission:inventory.purchase-reports.create')
            ->name('branches.purchase-reports.create');

        Route::post('/branches/{branch}/purchase-reports', [PurchaseReportController::class, 'store'])
            ->middleware('permission:inventory.purchase-reports.create')
            ->name('branches.purchase-reports.store');

        Route::get('/branches/{branch}/purchase-reports/{purchaseReport}', [PurchaseReportController::class, 'show'])
            ->middleware('permission:inventory.purchase-reports.view,inventory.purchase-reports.update')
            ->name('branches.purchase-reports.show');

        Route::put('/branches/{branch}/purchase-reports/{purchaseReport}', [PurchaseReportController::class, 'update'])
            ->middleware('permission:inventory.purchase-reports.update')
            ->name('branches.purchase-reports.update');

        Route::post('/branches/{branch}/purchase-reports/{purchaseReport}/generate', [PurchaseReportController::class, 'generate'])
            ->middleware('permission:inventory.purchase-reports.update')
            ->name('branches.purchase-reports.generate');

        /*
        |--------------------------------------------------------------------------
        | INVENTARIO - REPORTES / CADUCIDADES / TRANSFERENCIAS / AJUSTES
        |--------------------------------------------------------------------------
        */

        Route::get('/branches/{branch}/reports', [ReportController::class, 'index'])
            ->middleware('permission:inventory.view,inventory.branches.view')
            ->name('branches.reports');

        Route::get('/branches/{branch}/reports/audits', function (\App\Models\Branch $branch) {
            return redirect()->route('audits.physical-counts.reports', [
                'branch' => $branch->slug,
            ]);
        })
            ->middleware('permission:audits.physical-counts.reports')
            ->name('branches.reports.audits');

        Route::get('/reports', fn() => Inertia::render('Inventory/Reports/Index'))
            ->middleware('permission:inventory.view')
            ->name('reports');

        Route::get('/branches/{branch}/reports/inventory', [ReportController::class, 'inventory'])
            ->middleware('permission:inventory.view,inventory.branches.view')
            ->name('branches.reports.inventory');

        Route::get('/branches/{branch}/reports/inventory/excel', [ReportController::class, 'exportExcel'])
            ->middleware('permission:inventory.view,inventory.branches.view')
            ->name('branches.reports.inventory.excel');

        Route::get('/branches/{branch}/reports/inventory/pdf', [ReportController::class, 'exportPdf'])
            ->middleware('permission:inventory.view,inventory.branches.view')
            ->name('branches.reports.inventory.pdf');

        Route::get('/branches/{branch}/reports/movements', [ReportController::class, 'movements'])
            ->middleware('permission:inventory.view,inventory.branches.view')
            ->name('branches.reports.movements');

        Route::get('/branches/{branch}/reports/movements/excel', [ReportController::class, 'exportMovementsExcel'])
            ->middleware('permission:inventory.view,inventory.branches.view')
            ->name('branches.reports.movements.excel');

        Route::get('/branches/{branch}/reports/movements/pdf', [ReportController::class, 'exportMovementsPdf'])
            ->middleware('permission:inventory.view,inventory.branches.view')
            ->name('branches.reports.movements.pdf');
    });

    /*
    |--------------------------------------------------------------------------
    | INVENTARIO - INVENTARIO POR SUCURSAL
    |--------------------------------------------------------------------------
    */

    /*
    Route::prefix('inventario')->name('inventario.')->group(function () {
        // Bloque anterior conservado como referencia.
        // Las rutas activas ya fueron movidas a Route::prefix('inventory')->name('inventory.').
    });
    */

    /*
    |--------------------------------------------------------------------------
    | AUDITORÍAS - CONTEO FÍSICO
    |--------------------------------------------------------------------------
    */

    Route::prefix('audits')->name('audits.')->group(function () {
        Route::get('/physical-counts', [PhysicalCountController::class, 'index'])
            ->middleware('permission:audits.physical-counts.view,audits.physical-counts.count,audits.physical-counts.create,audits.physical-counts.update,audits.physical-counts.delete')
            ->name('physical-counts.index');

        Route::get('/physical-counts/reports', [PhysicalCountReportController::class, 'index'])
            ->middleware('permission:audits.physical-counts.reports')
            ->name('physical-counts.reports');

        Route::get('/physical-counts/reports/export-excel', [PhysicalCountReportController::class, 'exportExcel'])
            ->middleware(['permission:audits.physical-counts.reports', 'permission:files.export'])
            ->name('physical-counts.reports.export-excel');

        Route::get('/physical-counts/reports/export-pdf', [PhysicalCountReportController::class, 'exportPdf'])
            ->middleware(['permission:audits.physical-counts.reports', 'permission:files.export'])
            ->name('physical-counts.reports.export-pdf');


        Route::get('/physical-count-entries/{entry}', [PhysicalCountController::class, 'showEntry'])
            ->name('physical-count-entries.show');

        Route::patch('/physical-count-entries/{entry}', [PhysicalCountController::class, 'updateEntry'])
            ->name('physical-count-entries.update');

        Route::delete('/physical-count-entries/{entry}', [PhysicalCountController::class, 'destroyEntry'])
            ->name('physical-count-entries.destroy');

        Route::get('/physical-counts/{physicalCount}/search-products', [PhysicalCountController::class, 'searchProducts'])
            ->middleware('permission:audits.physical-counts.count,audits.physical-counts.update')
            ->name('physical-counts.search-products');

        Route::get('/physical-counts/{physicalCount}', [PhysicalCountController::class, 'show'])
            ->middleware('permission:audits.physical-counts.view,audits.physical-counts.count,audits.physical-counts.update')
            ->name('physical-counts.show');

        Route::post('/physical-counts', [PhysicalCountController::class, 'store'])
            ->middleware('permission:audits.physical-counts.create')
            ->name('physical-counts.store');

        Route::put('/physical-counts/{physicalCount}', [PhysicalCountController::class, 'update'])
            ->middleware('permission:audits.physical-counts.update')
            ->name('physical-counts.update');

        Route::delete('/physical-counts/{physicalCount}', [PhysicalCountController::class, 'destroy'])
            ->middleware('permission:audits.physical-counts.delete')
            ->name('physical-counts.destroy');

        Route::post('/physical-counts/{physicalCount}/scan', [PhysicalCountController::class, 'scan'])
            ->middleware('permission:audits.physical-counts.count,audits.physical-counts.update')
            ->name('physical-counts.scan');

        Route::post('/physical-counts/{physicalCount}/entries', [PhysicalCountController::class, 'storeEntry'])
            ->middleware('permission:audits.physical-counts.count,audits.physical-counts.update')
            ->name('physical-counts.entries.store');

        Route::post('/physical-counts/{physicalCount}/batches', [PhysicalCountController::class, 'storeBatch'])
            ->middleware('permission:audits.physical-counts.count,audits.physical-counts.update')
            ->name('physical-counts.batches.store');

        Route::patch('/physical-counts/{physicalCount}/close', [PhysicalCountController::class, 'close'])
            ->middleware('permission:audits.physical-counts.update')
            ->name('physical-counts.close');

        Route::patch('/physical-counts/{physicalCount}/reopen', [PhysicalCountController::class, 'reopen'])
            ->middleware('permission:audits.physical-counts.update')
            ->name('physical-counts.reopen');

        Route::patch('/physical-counts/{physicalCount}/apply-adjustments', [PhysicalCountController::class, 'applyAdjustments'])
            ->middleware('permission:audits.physical-counts.update')
            ->name('physical-counts.apply-adjustments');

        Route::get('/physical-counts/{physicalCount}/export-excel', [PhysicalCountController::class, 'exportExcel'])
            ->middleware('permission:files.export')
            ->name('physical-counts.export-excel');

        Route::get('/physical-counts/{physicalCount}/export-pdf', [PhysicalCountController::class, 'exportPdf'])
            ->middleware('permission:files.export')
            ->name('physical-counts.export-pdf');
    });
});
