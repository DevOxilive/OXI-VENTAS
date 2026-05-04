<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\EmpleadoController;

Route::get('/', function () {
    return redirect('/login');
});


Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/dashboard', function () {

        return Inertia::render('Dashboard');
    })->name('dashboard');
    Route::get('/register', function () {
        return Inertia::render('Auth/Register', [
            'roles' => DB::table('roles')
                ->where('name', '!=', 'Administrador') // opcional (seguridad)
                ->orderBy('name')
                ->get()
        ]);
    })->name('register');
});
Route::get('/register', [RegisterController::class, 'create']);

Route::prefix('Sistemas')->group(function () {

    Route::get('/empleados', [UserController::class, 'index'])
        ->name('sistemas.empleados');
 

// Mostrar formulario
Route::get('/register', [RegisterController::class, 'create'])->name('register');



});


// Rutas de acceso directo al dashboard de Recursos Humanos
Route::prefix('Recursos-humanos')->group(function () {

    Route::get('/home', fn() => Inertia::render('Recursos-humanos/Home'))->name('rh.home');

    Route::get('/roles', fn() => Inertia::render('Recursos-humanos/Roles'))->name('rh.roles');

    Route::get('/usuarios', fn() => Inertia::render('Recursos-humanos/Usuarios'))->name('rh.usuarios');
    Route::get('/empleados', [EmpleadoController::class, 'index'])->name('rh.empleados');
    Route::post('/empleados', [EmpleadoController::class, 'store'])->name('rh.empleados.store');
});
