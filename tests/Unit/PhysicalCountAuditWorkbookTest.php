<?php

namespace Tests\Unit;

use App\Exports\PhysicalCountConcentratedSheet;
use App\Exports\PhysicalCountDashboardSheet;
use App\Exports\PhysicalCountAuditWorkbookExport;
use App\Exports\PhysicalCountLotsSheet;
use App\Exports\PhysicalCountRoundsComparisonSheet;
use App\Exports\PhysicalCountUserSheet;
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
        $this->assertSame('Producto contado', $rows[1][2]);
        $this->assertSame('Producto no encontrado', $rows[2][2]);
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
        $this->assertSame('Producto no encontrado', $rows[1][2]);
    }

    public function test_concentrated_sheet_uses_the_requested_product_and_participant_columns(): void
    {
        $user = (object) ['id' => 7, 'name' => 'Ana'];
        $payload = $this->payload();
        $payload['reportRows'] = collect([$payload['reportRows']->first()]);
        $payload['entries'] = collect([(object) [
            'physical_count_id' => 10,
            'branch_product_id' => 100,
            'user_id' => 7,
            'counted_quantity' => 12,
            'expired_quantity' => 2,
            'damaged_quantity' => 1,
        ]]);

        $rows = (new PhysicalCountConcentratedSheet($payload, collect([$user])))->array();

        $this->assertSame([
            'Código(s) de barras',
            'Categoría',
            'Nombre del producto',
            'Stock actual',
            'Stock nuevo',
            'Conteo físico Ana',
            'Caducado Ana',
            'Dañado Ana',
        ], $rows[0]);
        $this->assertSame(9.0, $rows[1][4]);
        $this->assertSame([12.0, 2.0, 1.0], array_slice($rows[1], 5, 3));
    }

    public function test_concentrated_sheet_distinguishes_a_zero_count_from_a_product_without_capture(): void
    {
        $user = (object) ['id' => 7, 'name' => 'Ana'];
        $payload = $this->payload();
        $payload['reportRows'] = collect([$payload['reportRows']->first()]);
        $payload['entries'] = collect([(object) [
            'physical_count_id' => 10,
            'branch_product_id' => 100,
            'user_id' => 7,
            'counted_quantity' => 0,
            'expired_quantity' => 0,
            'damaged_quantity' => 0,
        ]]);

        $rows = (new PhysicalCountConcentratedSheet($payload, collect([$user])))->array();

        $this->assertEquals(0.0, $rows[1][4]);
        $this->assertEquals(0.0, $rows[1][5]);
    }

    public function test_concentrated_sheet_keeps_alternate_barcodes_on_one_product_row(): void
    {
        $payload = $this->payload();
        $first = $payload['reportRows']->first();
        $first['product_codes'] = ['750000000001', '750000000099', '750000000123'];
        $payload['reportRows'] = collect([$first]);

        $rows = (new PhysicalCountConcentratedSheet($payload, collect()))->array();

        $this->assertSame(
            '750000000001, 750000000099, 750000000123',
            $rows[1][0]
        );
        $this->assertCount(2, $rows);
    }

    public function test_user_sheet_only_contains_products_counted_by_that_user_without_checkboxes(): void
    {
        $user = (object) ['id' => 7, 'name' => 'Ana'];
        $payload = $this->payload();
        $payload['entries'] = collect([
            (object) [
                'physical_count_id' => 10,
                'branch_product_id' => 100,
                'user_id' => 7,
                'counted_quantity' => 8,
                'expired_quantity' => 1,
                'damaged_quantity' => 2,
                'notes' => 'Producto en exhibidor principal.',
            ],
            (object) [
                'physical_count_id' => 10,
                'branch_product_id' => 101,
                'user_id' => 8,
                'counted_quantity' => 3,
                'expired_quantity' => 0,
                'damaged_quantity' => 0,
                'notes' => null,
            ],
        ]);

        $rows = (new PhysicalCountUserSheet($payload, $user, 'Ana'))->array();

        $this->assertSame([
            'Código(s) de barras',
            'Categoría',
            'Nombre del producto',
            'Stock actual',
            'Conteo físico',
            'Caducado',
            'Dañado',
            'Exhibido',
            'Observaciones',
        ], $rows[0]);
        $this->assertCount(2, $rows);
        $this->assertSame([8.0, 1.0, 2.0, 'Sí'], array_slice($rows[1], 4, 4));
        $this->assertSame('Producto en exhibidor principal.', $rows[1][8]);
    }

    public function test_dashboard_prints_active_filter_labels(): void
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
        $this->assertSame('Auditor', $rows->get('Usuario(s)')[1]);
        $this->assertSame('2026-07-29', $rows->get('Fecha de auditoría')[1]);
        $this->assertSame('refresco', $rows->get('Busqueda')[1]);
        $this->assertNull($rows->get('Resumen por usuario'));
        $this->assertNull($rows->get('Indicadores de supervisión'));
    }

    public function test_complete_workbook_can_be_generated_with_all_core_sheets(): void
    {
        $binary = Excel::raw(
            new PhysicalCountAuditWorkbookExport(
                $this->payload(),
                ['audit_filters' => [], 'selected_results' => []],
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
                'Concentrado',
            ], $workbook->getSheetNames());
            $this->assertFalse(in_array('Subcategoría', $workbook->getSheetByName('Concentrado')->toArray()[0], true));
        } finally {
            @unlink($path);
        }
    }

    public function test_lot_sheet_consolidates_each_product_and_lot_into_one_row(): void
    {
        $audit = (object) ['id' => 10, 'name' => 'Auditoría', 'folio' => 'AUD-001'];
        $batch = (object) ['lot_number' => 'LOT-001', 'expiration_date' => now()->addMonth()];
        $round = (object) ['round_number' => 1, 'type' => 'original'];
        $payload = $this->payload();
        $payload['audits'] = collect([$audit]);
        $payload['reportRows'] = collect([$payload['reportRows']->first()]);
        $payload['entries'] = collect([
            (object) [
                'physical_count_id' => 10,
                'branch_product_id' => 100,
                'product_batch_id' => 501,
                'user_id' => 7,
                'user' => (object) ['name' => 'Ana'],
                'productBatch' => $batch,
                'round' => $round,
                'counted_quantity' => 8,
                'expired_quantity' => 1,
                'damaged_quantity' => 0,
                'notes' => 'Primera captura',
            ],
            (object) [
                'physical_count_id' => 10,
                'branch_product_id' => 100,
                'product_batch_id' => 501,
                'user_id' => 8,
                'user' => (object) ['name' => 'Blanca'],
                'productBatch' => $batch,
                'round' => $round,
                'counted_quantity' => 4,
                'expired_quantity' => 0,
                'damaged_quantity' => 1,
                'notes' => null,
            ],
        ]);

        $rows = (new PhysicalCountLotsSheet($payload, collect([10])))->array();

        $this->assertCount(2, $rows);
        $this->assertSame('Código(s) de barras', $rows[0][0]);
        $this->assertNotContains('Auditoría', $rows[0]);
        $this->assertSame('LOT-001', $rows[1][3]);
        $this->assertSame(12.0, $rows[1][6]);
        $this->assertSame(10.0, $rows[1][9]);
        $this->assertSame('Ana (8.00), Blanca (4.00)', $rows[1][11]);
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
                    'category_name' => 'Categoría',
                    'system_stock' => 5,
                ],
                [
                    'physical_count_id' => 10,
                    'branch_product_id' => 101,
                    'row_type' => 'pending',
                    'status' => 'pending',
                    'scanned_code' => '750000000002',
                    'product_name' => 'Producto no encontrado',
                    'category_name' => 'Categoría',
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
