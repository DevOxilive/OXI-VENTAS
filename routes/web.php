<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\Inventory\ProductController;
use App\Http\Controllers\Inventory\StockMovementController;
use App\Http\Controllers\Inventory\BranchInventoryController;



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
    | SISTEMAS
    |--------------------------------------------------------------------------
    */

    Route::prefix('sistemas')->group(function () {

        Route::get('/Empleados', function () {
            $user = \App\Models\User::find(Auth::id());

            abort_unless(
                Auth::check() && $user && $user->hasPermission('usuarios.ver'),
                403
            );

            return Inertia::render('Sistemas/Empleados', [
                'empleados' => \App\Models\Employee::doesntHave('user')->get(),
                'usuarios' => \App\Models\User::with(['role',    'permissions',    'branches',])->select('id',        'employee_id',        'name',        'email',        'role_id')->get(),
                'roles' => \App\Models\Role::all(),
                'permissions' => \App\Models\Permission::all(),
                'branches' => \App\Models\Branch::where('active', true)->get(),
            ]);
        })->name('sistemas.empleados');

        Route::post('/Empleados', [UserController::class, 'store'])
            ->name('sistemas.empleados.store');

        Route::put('/Empleados/{id}', [UserController::class, 'update'])
            ->name('sistemas.empleados.update');

        Route::delete('/Empleados/{id}', [UserController::class, 'destroy'])
            ->name('sistemas.empleados.destroy');

        Route::get(
            '/usuarios',
            fn() =>
            Inertia::render('Sistemas/Usuarios')
        )->name('sistemas.usuarios');
    });

    /*
    |--------------------------------------------------------------------------
    | CAPITAL HUMANO
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth', 'role:Recursos Humanos,Administrador'])
        ->group(function () {

            Route::get(
                '/home',
                fn() => Inertia::render('Recursos-humanos/Home')
            )->name('rh.home');

            Route::get(
                '/roles',
                fn() => Inertia::render('Recursos-humanos/Roles')
            )->name('rh.roles');

            Route::get(
                '/usuarios',
                fn() => Inertia::render('Recursos-humanos/Usuarios')
            )->name('rh.usuarios');

            Route::get('/empleados', [EmployeeController::class, 'index'])->name('rh.empleados');

            Route::post('/empleados', [EmployeeController::class, 'store'])->name('rh.empleados.store');

            Route::post('/empleados/store', [EmployeeController::class, 'store'])->name('rh.empleados.store.alt');

            Route::put('/empleados/{id}', [EmployeeController::class, 'update'])->name('rh.empleados.update');

            Route::delete('/empleados/{id}', [EmployeeController::class, 'destroy'])->name('rh.empleados.destroy');

            Route::get('/empleados/exportar-excel', [EmployeeController::class, 'exportExcel'])->name('rh.empleados.exportarExcel');
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

            Route::get(
                '/',
                fn() => Inertia::render('Ventas/Home')
            )->name('home');
        });

    Route::middleware(['auth', 'role:Ventas,Administrador'])
        ->prefix('ventas')
        ->name('ventas.')
        ->group(function () {

            Route::get(
                '/',
                fn() => Inertia::render('Ventas/Home')
            )->name('home');
        });

    /*
    |--------------------------------------------------------------------------
    | INVENTORY - RUTAS ACTUALES EN INGLÉS
    |--------------------------------------------------------------------------
    */

  /*
|--------------------------------------------------------------------------
| INVENTORY
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:Inventario,Administrador'])
    ->prefix('inventory')
    ->name('inventory.')
    ->group(function () {

        Route::get('/dashboard', fn() =>
            Inertia::render('Inventory/Dashboard')
        )->name('dashboard');

        /*
        |--------------------------------------------------------------------------
        | PRODUCTS BY BRANCH
        |--------------------------------------------------------------------------
        */

        Route::get('/branches/{branch:slug}/products', [ProductController::class, 'index'])
            ->name('branches.products.index');

        Route::post('/branches/{branch:slug}/products', [ProductController::class, 'store'])
            ->name('branches.products.store');

Route::put('/branches/{branch:slug}/products/{product}', [ProductController::class, 'update'])
    ->name('branches.products.update');

        Route::delete('/branches/{branch:slug}/products/{product}', [ProductController::class, 'destroy'])
            ->name('branches.products.destroy');

        /*
        |--------------------------------------------------------------------------
        | GENERAL INVENTORY
        |--------------------------------------------------------------------------
        */

        Route::get('/expirations', fn() =>
            Inertia::render('Inventory/Expirations')
        )->name('expirations');

        Route::get('/transfers', fn() =>
            Inertia::render('Inventory/Transfers')
        )->name('transfers');

        Route::get('/adjustments', fn() =>
            Inertia::render('Inventory/Adjustments')
        )->name('adjustments');
Route::get('/reports', fn() =>
    Inertia::render('Inventory/Reports')
)->name('reports');

        Route::get('/stock-movements', [StockMovementController::class, 'index'])
            ->name('stock-movements.index');

        Route::post('/stock-movements', [StockMovementController::class, 'store'])
            ->name('stock-movements.store');

        Route::get('/movements', [StockMovementController::class, 'index'])
            ->name('movements');
            
    Route::get('/reports', fn() =>
    Inertia::render('Inventory/Reports')
)->name('reports');

            Route::get('/stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index');

            Route::post('/stock-movements', [StockMovementController::class, 'store'])->name('stock-movements.store');
            
                 Route::get(
                '/movements',
                fn() => Inertia::render('Inventory/Movements')
            )->name('movements');
            Route::get(
                '/reports',
                fn() => Inertia::render('Inventory/Reports')
            )->name('reports');
    });

    /*
    |--------------------------------------------------------------------------
    | INVENTARIO - TUS RUTAS EN ESPAÑOL
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth', 'role:Inventario,Administrador'])
        ->prefix('inventario')
        ->name('inventario.')
        ->group(function () {

            Route::get(
                '/dashboard',
                fn() => Inertia::render('Inventory/Dashboard')
            )->name('dashboard');

            Route::get('/productos', [ProductController::class, 'index'])->name('productos');

            Route::post('/productos', [ProductController::class, 'store'])->name('products.store');

            Route::put('/productos/{product}', [ProductController::class, 'update'])->name('products.update');

            Route::delete('/productos/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

            Route::get(
                '/caducidades',
                fn() => Inertia::render('Inventory/Expirations')
            )->name('caducidades');

            Route::get(
                '/transferencias',
                fn() => Inertia::render('Inventory/Transfers')
            )->name('transferencias');

            Route::get(
                '/ajustes',
                fn() => Inertia::render('Inventory/Adjustments')
            )->name('ajustes');

            Route::get(
                '/reportes',
                fn() => Inertia::render('Inventory/Reports')
            )->name('reportes');

            Route::get('/stock-movements', [StockMovementController::class, 'index'])->name('stock-movements.index');

            Route::post('/stock-movements', [StockMovementController::class, 'store'])->name('stock-movements.store');

            Route::get('/inventario-sucursales', [BranchInventoryController::class, 'index'])->name('sucursales');

            Route::post('/inventario-sucursales', [BranchInventoryController::class, 'store'])->name('branch-inventory.store');

            Route::get('/movimientos', [StockMovementController::class, 'index'])->name('movimientos');
        });
});
