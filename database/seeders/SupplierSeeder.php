<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            [
                'id' => 1,
                'name' => 'CocaColaSupplier',
                'phone' => '5512345678',
                'email' => 'coca@supplier.com',
                'address' => 'MexicoCity',
                'active' => true,
            ],
            [
                'id' => 2,
                'name' => 'SabritasSupplier',
                'phone' => '5587654321',
                'email' => 'sabritas@supplier.com',
                'address' => 'MexicoCity',
                'active' => true,
            ],
        ];

        foreach ($suppliers as $supplier) {
            DB::table('suppliers')->updateOrInsert(
                ['id' => $supplier['id']],
                array_merge($supplier, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}