<?php

use Inertia\Inertia;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\Inventory\ProductController;
/*
|--------------------------------------------------------------------------
| PUBLIC (sin login)
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect('/login');
});

// Registro con roles
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
| 🔐 TODO PROTEGIDO (LOGIN)
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    // 📊 Dashboard
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');


    /*
|--------------------------------------------------------------------------
| 🧠 SISTEMAS (Usuarios / Permisos)
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

                'usuarios' => \App\Models\User::with([
                    'role',
                    'permissions',
                    'branches'
                ])
                    ->select(
                        'id',
                        'employee_id',
                        'name',
                        'email',
                        'role_id',

                    )
                    ->get(),

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

        Route::get('/usuarios', fn() => Inertia::render('Sistemas/Usuarios'))
            ->name('sistemas.usuarios');
    });
    /*
    |--------------------------------------------------------------------------
    | 👨‍💼 CAPITAL HUMANO
    |--------------------------------------------------------------------------
    */


    // Solo Recursos Humanos y Administradores pueden acceder a estas rutas
    Route::middleware(['auth', 'role:Recursos Humanos,Administrador'])->group(function () {

        Route::get('/home', fn() => Inertia::render('Recursos-humanos/Home'))->name('rh.home');

        Route::get('/roles', fn() => Inertia::render('Recursos-humanos/Roles'))->name('rh.roles');

        Route::get('/usuarios', fn() => Inertia::render('Recursos-humanos/Usuarios'))->name('rh.usuarios');
        Route::get('/empleados', [EmployeeController::class, 'index'])->name('rh.empleados');
        Route::post('/empleados', [EmployeeController::class, 'store'])->name('rh.empleados.store');
        Route::post('/empleados/store', [EmployeeController::class, 'store'])->name('rh.empleados.store');
        Route::put('/empleados/{id}', [EmployeeController::class, 'update'])->name('rh.empleados.update');
        Route::delete('/empleados/{id}', [EmployeeController::class, 'destroy'])->name('rh.empleados.destroy');

        Route::get('/empleados/exportar-excel', [EmployeeController::class, 'exportExcel'])
            ->name('rh.empleados.exportarExcel');
    });


    /*
    |--------------------------------------------------------------------------
    | 💰 VENTAS
    |--------------------------------------------------------------------------
    */

    // Solo Ventas, Sistemas y Administradores pueden acceder a estas rutas
    Route::middleware(['auth', 'role:Ventas, Administrador'])->group(function () {

        Route::prefix('ventas')->group(function () {

            Route::get(
                '/',
                fn() =>
                Inertia::render('Ventas/Home')
            )->name('ventas.home');
        });
    });

    /*
    |--------------------------------------------------------------------------
    | 💰 Inventario
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth', 'role:Inventario,Administrador'])
        ->prefix('inventario')
        ->name('inventario.')
        ->group(function () {

            /*
        |----------------------------------------------------------------------
        | DASHBOARD
        |----------------------------------------------------------------------
        */

            Route::get(
                '/dashboard',
                fn() =>
                Inertia::render('Inventory/Dashboard')
            )->name('dashboard');

            /*
        |----------------------------------------------------------------------
        | PRODUCTOS
        |----------------------------------------------------------------------
        */
            Route::get('/productos', [ProductController::class, 'index'])
                ->name('productos');

            Route::post('/productos', [ProductController::class, 'store'])
                ->name('products.store');

            Route::put('/productos/{product}', [ProductController::class, 'update'])
                ->name('products.update');

            Route::delete('/productos/{product}', [ProductController::class, 'destroy'])
                ->name('products.destroy');

            /*
        |----------------------------------------------------------------------
        | MOVIMIENTOS
        |----------------------------------------------------------------------
        */
            Route::get(
                '/movimientos',
                fn() =>
                Inertia::render('Inventory/Movements')
            )->name('movimientos');

            /*
        |----------------------------------------------------------------------
        | CADUCIDADES
        |----------------------------------------------------------------------
        */
            Route::get(
                '/caducidades',
                fn() =>
                Inertia::render('Inventory/Expirations')
            )->name('caducidades');

            /*
        |----------------------------------------------------------------------
        | TRANSFERENCIAS
        |----------------------------------------------------------------------
        */
            Route::get(
                '/transferencias',
                fn() =>
                Inertia::render('Inventory/Transfers')
            )->name('transferencias');

            /*
        |----------------------------------------------------------------------
        | AJUSTES
        |----------------------------------------------------------------------
        */
            Route::get(
                '/ajustes',
                fn() =>
                Inertia::render('Inventory/Adjustments')
            )->name('ajustes');

            /*
        |----------------------------------------------------------------------
        | REPORTES
        |----------------------------------------------------------------------
        */
            Route::get(
                '/reportes',
                fn() =>
                Inertia::render('Inventory/Reports')
            )->name('reportes');
        });
});
