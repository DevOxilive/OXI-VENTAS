<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

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

        'sucursales' => \App\Models\Sucursal::where('activa', true)
            ->orderBy('nombre')
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

                'empleados' => \App\Models\Empleado::doesntHave('user')->get(),

                'usuarios' => \App\Models\User::with([
                    'role',
                    'permissions',
                    'sucursales'
                ])
                    ->select(
                        'id',
                        'empleado_id',
                        'name',
                        'email',
                        'role_id',

                    )
                    ->get(),

                'roles' => \App\Models\Role::all(),

                'permissions' => \App\Models\Permission::all(),

                'sucursales' => \App\Models\Sucursal::where('activa', true)->get(),

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
        Route::get('/empleados', [EmpleadoController::class, 'index'])->name('rh.empleados');
        Route::post('/empleados', [EmpleadoController::class, 'store'])->name('rh.empleados.store');
        Route::post('/empleados/store', [EmpleadoController::class, 'store'])->name('rh.empleados.store');
        Route::put('/empleados/{id}', [EmpleadoController::class, 'update'])->name('rh.empleados.update');
        Route::delete('/empleados/{id}', [EmpleadoController::class, 'destroy'])->name('rh.empleados.destroy');

        Route::get('/empleados/exportar-excel', [EmpleadoController::class, 'exportarExcel'])
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
    | 📦 INVENTARIO
    |--------------------------------------------------------------------------
    */
    // Solo Inventario y Administradores pueden acceder a estas rutas
    Route::middleware(['auth', 'role:Inventario, Administrador'])->group(function () {

        Route::prefix('inventario')->group(function () {

            Route::get(
                '/',
                fn() =>
                Inertia::render('Inventario/Home')
            )->name('inventario.home');
        });
    });
});
