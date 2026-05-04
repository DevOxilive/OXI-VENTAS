<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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
});

// Rutas de acceso directo al dashboard de Recursos Humanos
Route::prefix('Recursos-humanos')->group(function () {

    Route::get('/home', fn() => Inertia::render('Recursos-humanos/Home'))->name('rh.home');

    Route::get('/roles', fn() => Inertia::render('Recursos-humanos/Roles'))->name('rh.roles');

    Route::get('/usuarios', fn() => Inertia::render('Recursos-humanos/Usuarios'))->name('rh.usuarios');

    Route::get('/empleados', fn() => Inertia::render('Recursos-humanos/Empleados'))->name('rh.empleados');
});
