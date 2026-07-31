<?php

namespace Tests\Unit;

use Database\Seeders\PermissionSeeder;
use Tests\TestCase;

class RoutePermissionIntegrityTest extends TestCase
{
    public function test_every_permission_used_by_a_route_exists_in_the_permission_catalog(): void
    {
        $definedPermissions = collect(PermissionSeeder::catalog())->flip();
        $missing = collect(app('router')->getRoutes())
            ->flatMap(fn ($route) => collect($route->gatherMiddleware())
                ->filter(fn ($middleware) => str_starts_with($middleware, 'permission:'))
                ->flatMap(fn ($middleware) => explode(',', substr($middleware, strlen('permission:')))))
            ->map(fn ($permission) => trim($permission))
            ->filter()
            ->unique()
            ->reject(fn ($permission) => $definedPermissions->has($permission))
            ->values();

        $this->assertSame([], $missing->all(), 'Rutas con permisos inexistentes: '.$missing->implode(', '));
    }
}
