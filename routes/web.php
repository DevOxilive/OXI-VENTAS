<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\EmpleadoController;

/*
|--------------------------------------------------------------------------
| PUBLIC (sin login)
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect('/login');
});

// Registro personalizado con roles
Route::get('/register', function () {
    return Inertia::render('Auth/Register', [
        'roles' => DB::table('roles')
            ->where('name', '!=', 'Administrador') // seguridad básica
            ->orderBy('name')
            ->get()
    ]);
})->name('register');


/*
|--------------------------------------------------------------------------
| AUTH (requiere login)
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    // Dashboard
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');


    /*
    |--------------------------------------------------------------------------
    | 🔐 SISTEMAS
    |--------------------------------------------------------------------------
    */
    Route::prefix('sistemas')->group(function () {

        Route::get(
            '/roles',
            fn() =>
            Inertia::render('Sistemas/Roles')
        )->name('sistemas.roles');

        Route::get(
            '/usuarios',
            fn() =>
            Inertia::render('Sistemas/Usuarios')
        )->name('sistemas.usuarios');
    });


    /*
    |--------------------------------------------------------------------------
    | 👨‍💼 CAPITAL HUMANO (RH)
    |--------------------------------------------------------------------------
    */
    Route::prefix('capital-humano')->group(function () {

        Route::get(
            '/home',
            fn() =>
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

        Route::get(
            '/',
            fn() =>
            Inertia::render('Ventas/Home')
        )->name('ventas.home');
    });


    /*
    |--------------------------------------------------------------------------
    | 📦 INVENTARIO
    |--------------------------------------------------------------------------
    */
    Route::prefix('inventario')->group(function () {

        Route::get(
            '/',
            fn() =>
            Inertia::render('Inventario/Home')
        )->name('inventario.home');
    });
});
