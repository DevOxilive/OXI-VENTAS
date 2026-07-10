<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,

            EmployeeSeeder::class,
            UsersSeeder::class,

            BranchSeeder::class,
            AreaSeeder::class,
            DepartmentSeeder::class,
            PositionSeeder::class,

            SupplierSeeder::class,
            CustomerSeeder::class,
            PaymentMethodSeeder::class,

            VehicleSeeder::class,
            TripSeeder::class,
            TripDetailSeeder::class,
            IncidentSeeder::class,
            InventoryReportSeeder::class,
            PurchaseSeeder::class,
            ExecutiveDashboardSeeder::class,

        ]);
    }
}
