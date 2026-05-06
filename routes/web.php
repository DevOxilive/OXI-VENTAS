<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\UserController;

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
            ->get()
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

        // LISTAR
        Route::get('/empleados', [UserController::class, 'index'])
            ->name('sistemas.empleados');

        // CREAR
        Route::post('/empleados', [UserController::class, 'store'])
            ->name('sistemas.empleados.store');

        // ACTUALIZAR
        Route::put('/empleados/{id}', [UserController::class, 'update'])
            ->name('sistemas.empleados.update');

        // ELIMINAR (soft delete si ya lo implementaste)
        Route::delete('/empleados/{id}', [UserController::class, 'destroy'])
            ->name('sistemas.empleados.destroy');

        // ACTUALIZAR PERMISOS
        Route::post('/usuarios/{user}/permisos', [UserController::class, 'updatePermissions'])
            ->name('usuarios.permisos');
    });


    /*
    |--------------------------------------------------------------------------
    | 👨‍💼 CAPITAL HUMANO
    |--------------------------------------------------------------------------
    */
    Route::prefix('capital-humano')->group(function () {

        Route::get('/home', fn() =>
            Inertia::render('CapitalHumano/Home')
        )->name('rh.home');

        Route::get('/empleados', [EmpleadoController::class, 'index'])
            ->name('rh.empleados');

        Route::post('/empleados', [EmpleadoController::class, 'store'])
            ->name('rh.empleados.store');
    });


    /*
    |--------------------------------------------------------------------------
    | 💰 VENTAS
    |--------------------------------------------------------------------------
    */
    Route::prefix('ventas')->group(function () {

        Route::get('/', fn() =>
            Inertia::render('Ventas/Home')
        )->name('ventas.home');
    });


    /*
    |--------------------------------------------------------------------------
    | 📦 INVENTARIO
    |--------------------------------------------------------------------------
    */
    Route::prefix('inventario')->group(function () {

        Route::get('/', fn() =>
            Inertia::render('Inventario/Home')
        )->name('inventario.home');
    });

});