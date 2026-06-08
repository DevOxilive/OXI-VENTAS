<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\Inventory\ReportController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\ProductBatchController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Inventory\PurchaseReportController;
use App\Http\Controllers\Inventory\BranchInventoryController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\Audits\PhysicalCountController;

/*
|--------------------------------------------------------------------------
| PUBLIC
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
| PROTECTED
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

    /*
    |--------------------------------------------------------------------------
    | SYSTEMS
    |--------------------------------------------------------------------------
    */

    Route::prefix('systems')->name('systems.')->group(function () {
        Route::get('/employees', function () {
            $user = \App\Models\User::find(Auth::id());

            abort_unless(
                Auth::check() && $user && $user->hasPermission('usuarios.ver'),
                403
            );

            return Inertia::render('Systems/Empleados', [
                'empleados' => \App\Models\Employee::doesntHave('user')->get(),
                'usuarios' => \App\Models\User::with([
                    'role',
                    'permissions',
                    'branches',
                ])
                    ->select(
                        'id',
                        'employee_id',
                        'name',
                        'email',
                        'role_id'
                    )
                    ->get(),
                'roles' => \App\Models\Role::all(),
                'permissions' => \App\Models\Permission::all(),
                'branches' => \App\Models\Branch::where('active', true)->get(),
            ]);
        })->name('employees');
    });

    Route::put('/employees/{id}', [UserController::class, 'update'])
        ->name('employees.update');

    Route::delete('/employees/{id}', [UserController::class, 'destroy'])
        ->name('employees.destroy');

    Route::get('/users', function () {
        return Inertia::render('Systems/Empleados');
    })->name('users');

    Route::get('/branches', [BranchController::class, 'index'])
        ->name('branches');

    Route::post('/branches', [BranchController::class, 'store'])
        ->name('branches.store');

    Route::put('/branches/{branch}', [BranchController::class, 'update'])
        ->name('branches.update');

    Route::delete('/branches/{branch}', [BranchController::class, 'destroy'])
        ->name('branches.destroy');
});

/*
|--------------------------------------------------------------------------
| AUDITS
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])
    ->prefix('audits')
    ->name('audits.')
    ->group(function () {
        Route::get('/physical-counts', [PhysicalCountController::class, 'index'])
            ->name('physical-counts.index');

        Route::get('/physical-counts/{physicalCount}', [PhysicalCountController::class, 'show'])
            ->name('physical-counts.show');

        Route::post('/physical-counts', [PhysicalCountController::class, 'store'])
            ->name('physical-counts.store');

        Route::post('/physical-counts/{physicalCount}/scan', [PhysicalCountController::class, 'scan'])
            ->name('physical-counts.scan');

        Route::post('/physical-counts/{physicalCount}/entries', [PhysicalCountController::class, 'storeEntry'])
            ->name('physical-counts.entries.store');

        Route::patch('/physical-counts/{physicalCount}/close', [PhysicalCountController::class, 'close'])
            ->name('physical-counts.close');

        Route::get('/physical-counts/{physicalCount}/export-excel', [PhysicalCountController::class, 'exportExcel'])
            ->name('physical-counts.export-excel');

        Route::patch('/physical-counts/{physicalCount}/reopen', [PhysicalCountController::class, 'reopen'])
            ->name('physical-counts.reopen');

        Route::patch('/physical-counts/{physicalCount}/apply-adjustments', [PhysicalCountController::class, 'applyAdjustments'])
            ->name('physical-counts.apply-adjustments');

        Route::get('/physical-counts/{physicalCount}/export-pdf', [PhysicalCountController::class, 'exportPdf'])
            ->name('physical-counts.export-pdf');
    });

/*
|--------------------------------------------------------------------------
| CAPITAL HUMANO
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:Recursos Humanos,Administrador'])
    ->group(function () {
        Route::get('/home', fn() => Inertia::render('Recursos-humanos/Home'))
            ->name('rh.home');

        Route::get('/roles', fn() => Inertia::render('Recursos-humanos/Roles'))
            ->name('rh.roles');

        Route::get('/usuarios', fn() => Inertia::render('Recursos-humanos/Usuarios'))
            ->name('rh.usuarios');

        Route::get('/empleados', [EmployeeController::class, 'index'])
            ->name('rh.empleados');

        Route::post('/empleados', [EmployeeController::class, 'store'])
            ->name('rh.empleados.store');

        Route::post('/empleados/store', [EmployeeController::class, 'store'])
            ->name('rh.empleados.store.alt');

        Route::put('/empleados/{id}', [EmployeeController::class, 'update'])
            ->name('rh.empleados.update');

        Route::delete('/empleados/{id}', [EmployeeController::class, 'destroy'])
            ->name('rh.empleados.destroy');

        Route::get('/empleados/exportar-excel', [EmployeeController::class, 'exportExcel'])
            ->name('rh.empleados.exportarExcel');
    });

/*
|--------------------------------------------------------------------------
| SALES / VENTAS
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:Ventas,Administrador'])
    ->prefix('sales')
    ->name('sales.')
    ->group(function () {
        Route::get('/', fn() => Inertia::render('Ventas/Home'))
            ->name('home');
    });

Route::middleware(['auth', 'role:Ventas,Administrador'])
    ->prefix('ventas')
    ->name('ventas.')
    ->group(function () {
        Route::get('/', fn() => Inertia::render('Ventas/Home'))
            ->name('home');
    });

/*
|--------------------------------------------------------------------------
| INVENTORY - RUTAS EN INGLÉS
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:Inventario,Administrador'])
    ->prefix('inventory')
    ->name('inventory.')
    ->group(function () {
        Route::get('/dashboard', fn() => Inertia::render('Inventory/Dashboard'))
            ->name('dashboard');

        Route::get('/branches/{branch:slug}/products', [ProductController::class, 'index'])
            ->name('branches.products.index');

        Route::post('/branches/{branch:slug}/products', [ProductController::class, 'store'])
            ->name('branches.products.store');

        Route::put('/branches/{branch:slug}/products/{product}', [ProductController::class, 'update'])
            ->name('branches.products.update');

        Route::delete('/branches/{branch:slug}/products/{product}', [ProductController::class, 'destroy'])
            ->name('branches.products.destroy');

        Route::get('/expirations', fn() => Inertia::render('Inventory/Expirations'))
            ->name('expirations');

        Route::get('/transfers', fn() => Inertia::render('Inventory/Transfers'))
            ->name('transfers');

        Route::get('/adjustments', fn() => Inertia::render('Inventory/Adjustments'))
            ->name('adjustments');

        Route::get('/reports', fn() => Inertia::render('Inventory/Reports'))
            ->name('reports');

        Route::get('/stock-movements', [StockMovementController::class, 'index'])
            ->name('stock-movements.index');

        Route::post('/stock-movements', [StockMovementController::class, 'store'])
            ->name('stock-movements.store');

        Route::get('/movements', [StockMovementController::class, 'index'])
            ->name('movements');
    });

/*
|--------------------------------------------------------------------------
| INVENTARIO - RUTAS EN ESPAÑOL
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:Inventario,Administrador'])
    ->prefix('inventario')
    ->name('inventario.')
    ->group(function () {
        Route::get('/dashboard', fn() => Inertia::render('Inventory/Dashboard'))
            ->name('dashboard');

        Route::get('/productos', [ProductController::class, 'index'])
            ->name('productos');

        Route::post('/productos', [ProductController::class, 'store'])
            ->name('products.store');

        Route::put('/productos/{product}', [ProductController::class, 'update'])
            ->name('products.update');

        Route::delete('/productos/{product}', [ProductController::class, 'destroy'])
            ->name('products.destroy');

        Route::get('/caducidades', fn() => Inertia::render('Inventory/Expirations'))
            ->name('caducidades');

        Route::get('/transferencias', fn() => Inertia::render('Inventory/Transfers'))
            ->name('transferencias');

        Route::get('/ajustes', fn() => Inertia::render('Inventory/Adjustments'))
            ->name('ajustes');

        Route::get('/branches/{branch}/reports', [ReportController::class, 'index'])
            ->name('branches.reports');

        Route::get('/stock-movements', [StockMovementController::class, 'index'])
            ->name('stock-movements.index');

        Route::post('/stock-movements', [StockMovementController::class, 'store'])
            ->name('stock-movements.store');

        Route::put('/product-batches/{productBatch}', [ProductBatchController::class, 'update'])
            ->name('product-batches.update');

        Route::get('/branches/{branch}/inventory', [BranchInventoryController::class, 'show'])
            ->name('branches.inventory');

        Route::post('/inventario-sucursales', [BranchInventoryController::class, 'store'])
            ->name('branch-inventory.store');

        Route::patch('/inventario-sucursales/{branchProduct}/config', [BranchInventoryController::class, 'updateConfig'])
            ->name('branch-inventory.update-config');

        Route::get('/branches/{branch}/purchase-reports/create', [PurchaseReportController::class, 'create'])
            ->name('branches.purchase-reports.create');

        Route::post('/branches/{branch}/purchase-reports', [PurchaseReportController::class, 'store'])
            ->name('branches.purchase-reports.store');

        Route::put('/branches/{branch}/purchase-reports/{purchaseReport}', [PurchaseReportController::class, 'update'])
            ->name('branches.purchase-reports.update');

        Route::post('/branches/{branch}/purchase-reports/{purchaseReport}/generate', [PurchaseReportController::class, 'generate'])
            ->name('branches.purchase-reports.generate');

        Route::get('/branches/{branch}/purchase-reports', [PurchaseReportController::class, 'index'])
            ->name('branches.purchase-reports.index');

        Route::get('/branches/{branch}/purchase-reports/{purchaseReport}', [PurchaseReportController::class, 'show'])
            ->name('branches.purchase-reports.show');

        Route::get('/movimientos', [StockMovementController::class, 'index'])
            ->name('movimientos');
    });
