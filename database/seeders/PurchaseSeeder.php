<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchaseSeeder extends Seeder
{
    public function run(): void
    {
        $branchId = DB::table('branches')->value('id') ?? 1;
        $employeeId = DB::table('employees')->value('id') ?? 1;

        DB::table('purchases')->updateOrInsert(
            ['id' => 1],
            [
                'supplier_id' => 1,
                'branch_id' => $branchId,
                'employee_id' => $employeeId,
                'date' => now(),
                'total' => 600.00,
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}