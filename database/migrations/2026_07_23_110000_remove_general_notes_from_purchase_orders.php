<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => 'inventory.purchase-orders.costs'],
            ['created_at' => now(), 'updated_at' => now()],
        );

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['notes', 'adjustment_notes']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('purchased_at');
            $table->text('adjustment_notes')->nullable()->after('notes');
        });

        DB::table('permissions')->where('name', 'inventory.purchase-orders.costs')->delete();
    }
};
