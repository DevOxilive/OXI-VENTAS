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
            'entrada de reportes' => [
                'inventory.reports',
                'permission:reports.audits.view,reports.cash-closures.view,reports.inventory.view,reports.movements.view',
            ],
            'selector de reporte' => [
                'inventory.reports.select',
                'permission:reports.audits.view,reports.cash-closures.view,reports.inventory.view,reports.movements.view',
            ],
            'reporte de auditoria' => [
                'audits.physical-counts.reports',
                'permission:reports.audits.view',
            ],
            'exportar auditoria excel' => [
                'audits.physical-counts.reports.export-excel',
                'permission:reports.audits.export.excel',
            ],
            'exportar auditoria pdf' => [
                'audits.physical-counts.reports.export-pdf',
                'permission:reports.audits.export.pdf',
            ],
            'exportar auditoria individual excel' => [
                'audits.physical-counts.export-excel',
                'permission:reports.audits.export.excel',
            ],
            'exportar auditoria individual pdf' => [
                'audits.physical-counts.export-pdf',
                'permission:reports.audits.export.pdf',
            ],
            'reporte de cortes' => [
                'inventory.branches.reports.cash-closures',
                'permission:reports.cash-closures.view',
            ],
            'crear corte desde reportes' => [
                'ventas.cash-closures.store',
                'permission:sales.cash-closures.create,reports.cash-closures.create',
            ],
            'editar corte desde reportes' => [
                'ventas.cash-closures.update',
                'permission:sales.cash-closures.update,reports.cash-closures.update',
            ],
            'eliminar corte desde reportes' => [
                'ventas.cash-closures.destroy',
                'permission:sales.cash-closures.delete,reports.cash-closures.delete',
            ],
            'reporte de inventario' => [
                'inventory.branches.reports.inventory',
                'permission:reports.inventory.view',
            ],
            'exportar inventario excel' => [
                'inventory.branches.reports.inventory.excel',
                'permission:reports.inventory.export.excel',
            ],
            'exportar inventario pdf' => [
                'inventory.branches.reports.inventory.pdf',
                'permission:reports.inventory.export.pdf',
            ],
            'reporte de movimientos' => [
                'inventory.branches.reports.movements',
                'permission:reports.movements.view',
            ],
            'exportar movimientos excel' => [
                'inventory.branches.reports.movements.excel',
                'permission:reports.movements.export.excel',
            ],
            'exportar movimientos pdf' => [
                'inventory.branches.reports.movements.pdf',
                'permission:reports.movements.export.pdf',
            ],
        ];
    }
}
