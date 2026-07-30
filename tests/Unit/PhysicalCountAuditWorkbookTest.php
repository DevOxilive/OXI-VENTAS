<?php

namespace Tests\Unit;

use App\Exports\PhysicalCountConcentratedSheet;
use App\Exports\PhysicalCountDashboardSheet;
use App\Exports\PhysicalCountAuditWorkbookExport;
use App\Exports\PhysicalCountRoundsComparisonSheet;
use App\Models\PhysicalCountRound;
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

    public function test_round_comparison_keeps_original_values_and_uses_latest_recapture_as_final(): void
    {
        $original = new PhysicalCountRound([
            'round_number' => 1,
            'type' => 'original',
            'scope' => 'all',
            'started_at' => now()->subDay(),
        ]);
        $original->id = 101;
        $original->physical_count_id = 10;

        $reopening = new PhysicalCountRound([
            'round_number' => 2,
            'type' => 'reopening',
            'scope' => 'all',
            'started_at' => now(),
        ]);
        $reopening->id = 102;
        $reopening->physical_count_id = 10;

        $payload = [
            'rounds' => collect([$original, $reopening]),
            'allEntries' => collect([
                $this->roundEntry($original, 8),
                $this->roundEntry($reopening, 6),
            ]),
            'reportRows' => collect([[
                'physical_count_id' => 10,
                'branch_product_id' => 100,
                'branch_name' => 'Ajusco',
                'audit_name' => 'Auditoría',
                'folio' => 'AUD-001',
                'scanned_code' => '750000000001',
                'product_name' => 'Producto',
                'category_name' => 'Categoría',
                'system_stock' => 10,
                'participants' => ['Usuario 2'],
                'last_entry_at' => now()->toDateTimeString(),
            ]]),
        ];

        $rows = (new PhysicalCountRoundsComparisonSheet($payload))->array();

        $this->assertSame('Ronda 1 - Original', $rows[0][7]);
        $this->assertSame('Ronda 2 - Reapertura', $rows[0][8]);
        $this->assertSame(8.0, $rows[1][7]);
        $this->assertSame(6.0, $rows[1][8]);
        $this->assertSame(6.0, $rows[1][9]);
        $this->assertSame('Ronda 2 - Reapertura', $rows[1][11]);
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

    private function roundEntry(PhysicalCountRound $round, float $counted): object
    {
        return (object) [
            'physical_count_id' => 10,
            'physical_count_round_id' => $round->id,
            'branch_product_id' => 100,
            'user_id' => $round->round_number,
            'counted_quantity' => $counted,
            'damaged_quantity' => 0,
            'expired_quantity' => 0,
            'round' => $round,
            'created_at' => now(),
        ];
    }
}
