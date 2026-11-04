<?php

namespace App\Http\Middleware;

use App\Support\RoleSessionPolicy;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceRoleSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user() ?? Auth::guard('web')->user();

        if (! $user || ! $request->hasSession()) {
            return $next($request);
        }

        $policy = RoleSessionPolicy::forUser($user);

        if ($policy['keep_alive']) {
            RoleSessionPolicy::forget($request);

            return $next($request);
        }

        $expiresAt = RoleSessionPolicy::expiresAt($request, $policy);

        if ($expiresAt !== null && now()->timestamp >= $expiresAt) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw new AuthenticationException('Tu sesión venció por seguridad.');
        }

        return $next($request);
    }
}
