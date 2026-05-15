<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        $branchId = DB::table('sucursales')->value('id') ?? 1;
        $employeeId = DB::table('empleados')->value('id') ?? 1;

        DB::table('sales')->updateOrInsert(
            ['id' => 1],
            [
                'date' => now(),
                'employee_id' => $employeeId,
                'customer_id' => 1,
                'branch_id' => $branchId,
                'payment_method_id' => 1,
                'total' => 18.00,
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}