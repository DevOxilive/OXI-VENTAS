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

            CategorySeeder::class,
            SubcategorySeeder::class,

            SupplierSeeder::class,
            CustomerSeeder::class,
            PaymentMethodSeeder::class,

            ProductSeeder::class,
            BarcodeSeeder::class,

            InventoryLotSeeder::class,
            BranchInventorySeeder::class,

            PurchaseSeeder::class,
            PurchaseDetailSeeder::class,

            InventoryMovementSeeder::class,

            SaleSeeder::class,
            SaleDetailSeeder::class,

            VehicleSeeder::class,
            TripSeeder::class,
            TripDetailSeeder::class,
            IncidentSeeder::class,
        ]);
    }
}