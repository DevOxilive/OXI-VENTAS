<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('branches', 'is_central_warehouse')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->boolean('is_central_warehouse')->default(false)->after('active');
            });
        }

        $warehouse = DB::table('branches')
            ->where('slug', 'almacen-central')
            ->first();

        if (! $warehouse) {
            $warehouseId = DB::table('branches')->insertGetId([
                'name' => 'Almacén central',
                'slug' => 'almacen-central',
                'active' => true,
                'is_central_warehouse' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $warehouseId = $warehouse->id;
            DB::table('branches')->where('id', $warehouseId)->update([
                'is_central_warehouse' => true,
                'updated_at' => now(),
            ]);
        }

        $sourceBranchId = DB::table('branches')
            ->where('name', 'San Cristobal')
            ->value('id');

        if (! $sourceBranchId) return;

        DB::table('branch_inventory')
            ->where('branch_id', $sourceBranchId)
            ->orderBy('id')
            ->each(function (object $inventory) use ($warehouseId) {
                DB::table('branch_inventory')->updateOrInsert(
                    [
                        'branch_id' => $warehouseId,
                        'product_id' => $inventory->product_id,
                    ],
                    [
                        'current_stock' => 0,
                        'minimum_stock' => $inventory->minimum_stock,
                        'maximum_stock' => $inventory->maximum_stock,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('branches', 'is_central_warehouse')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->dropColumn('is_central_warehouse');
            });
        }
    }
};
