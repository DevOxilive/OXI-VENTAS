<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERMISSIONS = [
        'inventory.purchase-orders.generate.view',
        'inventory.purchase-orders.generate.create',
        'inventory.purchase-orders.generate.update',
        'inventory.purchase-orders.generate.transfer',
        'inventory.purchase-orders.purchasing.view',
        'inventory.purchase-orders.completed.view',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', self::PERMISSIONS)->delete();
    }
};
