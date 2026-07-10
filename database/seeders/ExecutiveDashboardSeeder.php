<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ExecutiveDashboardSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ExecutiveInventoryReplenishmentSeeder::class,
            ExecutiveSalesHistorySeeder::class,
        ]);
    }
}
