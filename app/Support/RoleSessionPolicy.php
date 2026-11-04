<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;

class RoleSessionPolicy
{
    private const POLICY_ROLE_KEY = 'role_session_policy_role';
    private const EXPIRES_AT_KEY = 'role_session_expires_at';

    public static function forUser(?User $user): array
    {
        $roleName = $user?->role?->name;
        $rolePolicy = config("role_session.roles.{$roleName}", []);
        $keepAlive = (bool) ($rolePolicy['keep_alive'] ?? false);

        return [
            'role' => $roleName,
            'keep_alive' => $keepAlive,
            'timeout_minutes' => $keepAlive
                ? null
                : (int) ($rolePolicy['timeout_minutes'] ?? config('role_session.default_timeout_minutes')),
            'warning_minutes' => (int) config('role_session.warning_minutes'),
        ];
    }

    public static function expiresAt(Request $request, array $policy): ?int
    {
        if ($policy['keep_alive'] || ! $request->hasSession()) {
            return null;
        }

        $session = $request->session();
        $storedRole = $session->get(self::POLICY_ROLE_KEY);
        $expiresAt = (int) $session->get(self::EXPIRES_AT_KEY, 0);

        if ($storedRole !== $policy['role'] || $expiresAt <= 0) {
            return self::renew($request, $policy);
        }

        return $expiresAt;
    }

    public static function renew(Request $request, array $policy): ?int
    {
        if ($policy['keep_alive'] || ! $request->hasSession()) {
            return null;
        }

        $expiresAt = now()->addMinutes($policy['timeout_minutes'])->timestamp;

        $request->session()->put([
            self::POLICY_ROLE_KEY => $policy['role'],
            self::EXPIRES_AT_KEY => $expiresAt,
        ]);

        return $expiresAt;
    }

    public static function forget(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $request->session()->forget([
            self::POLICY_ROLE_KEY,
            self::EXPIRES_AT_KEY,
        ]);
    }
}
