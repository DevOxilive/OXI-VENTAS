<?php

namespace Tests\Unit;

use App\Exports\PhysicalCountConcentratedSheet;
use App\Exports\PhysicalCountDashboardSheet;
use App\Exports\PhysicalCountAuditWorkbookExport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PhysicalCountAuditWorkbookTest extends TestCase
{
    public function test_concentrated_sheet_includes_counted_and_not_found_products_without_a_status_filter(): void
    {
        $sheet = new PhysicalCountConcentratedSheet(
            $this->payload(),
            collect([(object) ['id' => 7, 'name' => 'Auditor']])
        );

        $rows = $sheet->array();

        $this->assertCount(3, $rows);
        $this->assertSame('Producto contado', $rows[1][5]);
        $this->assertSame('Producto no encontrado', $rows[2][5]);
    }

    public function test_not_found_filter_exports_pending_products_instead_of_an_empty_sheet(): void
    {
        $sheet = new PhysicalCountConcentratedSheet(
            $this->payload(),
            new Collection(),
            'No encontrado',
            'not_found'
        );

        $rows = $sheet->array();

        $this->assertCount(2, $rows);
        $this->assertSame('Producto no encontrado', $rows[1][5]);
    }

    public function test_dashboard_prints_all_filter_labels(): void
    {
        $sheet = new PhysicalCountDashboardSheet(
            $this->payload(),
            [
                'audit' => 'Auditoria 1 - AUD-001',
                'user' => 'Auditor',
                'category' => 'Bebidas',
                'status' => 'Todos',
                'report_date' => '2026-07-29',
                'search' => 'refresco',
            ],
            'Sucursal Centro',
            'Concentrado',
            new Collection()
        );

        $rows = collect($sheet->array())->keyBy(fn (array $row) => $row[0] ?? null);

        $this->assertSame('Bebidas', $rows->get('Categoria')[1]);
        $this->assertSame('2026-07-29', $rows->get('Fecha de auditoría')[1]);
        $this->assertSame('refresco', $rows->get('Busqueda')[1]);
    }

    public function test_complete_workbook_can_be_generated_with_all_core_sheets(): void
    {
        $binary = Excel::raw(
            new PhysicalCountAuditWorkbookExport(
                $this->payload(),
                ['user_ids' => [], 'status' => ''],
                [
                    'audit' => 'Todas',
                    'user' => 'Todos',
                    'category' => 'Todas',
                    'status' => 'Todos',
                    'report_date' => 'Sin fecha',
                    'search' => 'Sin filtro',
                    'generated_by' => 'Pruebas',
                ],
                'Todas las sucursales'
            ),
            \Maatwebsite\Excel\Excel::XLSX
        );

        $path = tempnam(sys_get_temp_dir(), 'audit-workbook-') . '.xlsx';
        file_put_contents($path, $binary);

        try {
            $workbook = IOFactory::load($path);

            $this->assertSame([
                'Dashboard',
                'Control y filtros',
                'Concentrado',
                'Conteos consolidados',
                'No encontrados',
                'Diferencias',
                'Resumen auditorias',
                'Resumen sucursales',
                'Resumen categorias',
            ], $workbook->getSheetNames());
            $this->assertSame('Producto no encontrado', $workbook->getSheetByName('No encontrados')->getCell('F2')->getValue());
            $this->assertSame('Usuario que contó', $workbook->getSheetByName('Conteos consolidados')->getCell('E1')->getValue());
            $this->assertFalse(in_array('Subcategoría', $workbook->getSheetByName('Concentrado')->toArray()[0], true));
            $this->assertSame('Categoría', $workbook->getSheetByName('Control y filtros')->getCell('A9')->getValue());
        } finally {
            @unlink($path);
        }
    }

    private function payload(): array
    {
        return [
            'entries' => [],
            'reportRows' => collect([
                [
                    'physical_count_id' => 10,
                    'branch_product_id' => 100,
                    'row_type' => 'counted',
                    'status' => 'matched',
                    'scanned_code' => '750000000001',
                    'product_name' => 'Producto contado',
                    'system_stock' => 5,
                ],
                [
                    'physical_count_id' => 10,
                    'branch_product_id' => 101,
                    'row_type' => 'pending',
                    'status' => 'pending',
                    'scanned_code' => '750000000002',
                    'product_name' => 'Producto no encontrado',
                    'system_stock' => 3,
                ],
            ]),
        ];
    }
}
