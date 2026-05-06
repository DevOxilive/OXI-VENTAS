<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Middleware de control de acceso por roles.
     *
     * Este middleware valida si el usuario autenticado tiene
     * el rol permitido para acceder a una ruta específica.
     *
     * Uso en rutas:
     * role:Recursos Humanos,Administrador, Sistemas, etc...
     **/

    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !$user->role) {
            abort(403, 'Acceso no autorizado');
        }

        $userRole = $user->role->name;

        if (!in_array($userRole, $roles)) {
            abort(403, 'No tienes permisos para acceder a este módulo');
        }

        return $next($request);
    }
}
