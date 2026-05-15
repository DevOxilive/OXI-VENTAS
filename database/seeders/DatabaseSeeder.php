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
            EmpleadoSeeder::class,
            UsersSeeder::class,
            SucursalSeeder::class,
              AreaSeeder::class,
        DepartmentSeeder::class,
        PositionSeeder::class,
        CategorySeeder::class,
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
