<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
public function handle(Request $request, Closure $next, string ...$permissions): Response
{
    $user = $request->user();

    if (!$user) {
        abort(401);
    }

    $user->load(['permissions', 'role.permissions']);

    foreach ($permissions as $permission) {
        if ($user->hasPermission($permission)) {
            return $next($request);
        }
    }

    abort(403, 'No tienes permisos para acceder a este módulo');
}
}