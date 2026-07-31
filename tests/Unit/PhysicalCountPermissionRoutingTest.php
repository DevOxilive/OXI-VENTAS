<?php

namespace Tests\Unit;

use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PhysicalCountPermissionRoutingTest extends TestCase
{
    #[DataProvider('protectedActionProvider')]
    public function test_each_physical_count_action_requires_its_own_permission(
        string $routeName,
        string $permissionMiddleware
    ): void {
        $route = app('router')->getRoutes()->getByName($routeName);

        $this->assertInstanceOf(Route::class, $route);
        $this->assertContains($permissionMiddleware, $route->gatherMiddleware());
    }

    public static function protectedActionProvider(): array
    {
        return [
            'crear auditoría' => [
                'audits.physical-counts.store',
                'permission:audits.physical-counts.create',
            ],
            'administrar participantes' => [
                'audits.physical-counts.update',
                'permission:audits.physical-counts.participants',
            ],
            'capturar conteo' => [
                'audits.physical-counts.entries.store',
                'permission:audits.physical-counts.count',
            ],
            'buscar productos para conteo' => [
                'audits.physical-counts.search-products',
                'permission:audits.physical-counts.count',
            ],
            'escanear producto' => [
                'audits.physical-counts.scan',
                'permission:audits.physical-counts.count',
            ],
            'crear lote durante conteo' => [
                'audits.physical-counts.batches.store',
                'permission:audits.physical-counts.count',
            ],
            'editar conteo' => [
                'audits.physical-count-entries.update',
                'permission:audits.physical-counts.count',
            ],
            'eliminar conteo' => [
                'audits.physical-count-entries.destroy',
                'permission:audits.physical-counts.delete',
            ],
            'cerrar auditoría' => [
                'audits.physical-counts.close',
                'permission:audits.physical-counts.close',
            ],
            'reabrir auditoría' => [
                'audits.physical-counts.reopen',
                'permission:audits.physical-counts.reopen',
            ],
            'finalizar auditoría' => [
                'audits.physical-counts.finalize',
                'permission:audits.physical-counts.finalize',
            ],
            'aplicar ajustes' => [
                'audits.physical-counts.apply-adjustments',
                'permission:audits.physical-counts.apply',
            ],
            'eliminar auditoría' => [
                'audits.physical-counts.destroy',
                'permission:audits.physical-counts.delete',
            ],
            'ver reportes' => [
                'audits.physical-counts.reports',
                'permission:reports.audits.view',
            ],
            'exportar reporte general a Excel' => [
                'audits.physical-counts.reports.export-excel',
                'permission:files.export',
            ],
            'exportar reporte general a PDF' => [
                'audits.physical-counts.reports.export-pdf',
                'permission:files.export',
            ],
            'exportar auditoría a Excel' => [
                'audits.physical-counts.export-excel',
                'permission:files.export',
            ],
            'exportar auditoría a PDF' => [
                'audits.physical-counts.export-pdf',
                'permission:files.export',
            ],
        ];
    }
}
