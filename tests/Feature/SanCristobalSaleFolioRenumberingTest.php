<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Employee;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SanCristobalSaleFolioRenumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renumbers_the_exact_historical_san_cristobal_sequence(): void
    {
        $branch = Branch::create(['name' => 'San Cristobal', 'slug' => 'san-cristobal', 'active' => true]);
        DB::table('branches')->where('id', $branch->id)->update(['next_sale_folio' => 321]);
        $employee = Employee::create([
            'first_name' => 'Prueba',
            'last_name' => 'Folios',
            'email' => 'folios@example.test',
        ]);
        $paymentMethod = PaymentMethod::create(['name' => 'Efectivo', 'active' => true]);

        $saleIds = [298, ...range(302, 320)];
        foreach ($saleIds as $position => $saleId) {
            DB::table('sales')->insert([
                'id' => $saleId,
                'folio' => $position === 0 ? 'V-000001' : 'V-'.str_pad((string) $saleId, 6, '0', STR_PAD_LEFT),
                'date' => now()->addMinutes($position),
                'employee_id' => $employee->id,
                'branch_id' => $branch->id,
                'payment_method_id' => $paymentMethod->id,
                'total' => 10,
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration = require database_path('migrations/2026_11_01_170000_renumber_san_cristobal_sale_folios.php');
        $migration->up();

        $this->assertSame(
            collect(range(1, 20))->map(fn (int $number) => 'V-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT))->all(),
            DB::table('sales')->where('branch_id', $branch->id)->orderBy('date')->pluck('folio')->all(),
        );
        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'next_sale_folio' => 21]);
    }

    public function test_it_stops_without_writing_if_an_unreviewed_sale_exists(): void
    {
        $branch = Branch::create(['name' => 'San Cristobal', 'slug' => 'san-cristobal', 'active' => true]);

        $migration = require database_path('migrations/2026_11_01_170000_renumber_san_cristobal_sale_folios.php');

        $this->expectException(\RuntimeException::class);
        $migration->up();
    }
}
