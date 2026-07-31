<?php

namespace Tests\Unit;

use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportPermissionRoutingTest extends TestCase
{
    #[DataProvider('reportRouteProvider')]
    public function test_each_report_route_requires_its_report_permission(
        string $routeName,
        string $permissionMiddleware
    ): void {
        $route = app('router')->getRoutes()->getByName($routeName);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertContains($permissionMiddleware, $route->gatherMiddleware());
    }

    public static function reportRouteProvider(): array
    {
        return [
            'centro de reportes' => [
                'inventory.reports',
                'permission:reports.audits.view,reports.cash-closures.view,reports.inventory.view,reports.movements.view',
            ],
            'selector de reporte' => [
                'inventory.reports.select',
                'permission:reports.audits.view,reports.cash-closures.view,reports.inventory.view,reports.movements.view',
            ],
            'reporte de auditoría' => [
                'audits.physical-counts.reports',
                'permission:reports.audits.view',
            ],
            'reporte de cortes' => [
                'inventory.branches.reports.cash-closures',
                'permission:reports.cash-closures.view',
            ],
            'reporte de inventario' => [
                'inventory.branches.reports.inventory',
                'permission:reports.inventory.view',
            ],
            'reporte de movimientos' => [
                'inventory.branches.reports.movements',
                'permission:reports.movements.view',
            ],
        ];
    }
}
