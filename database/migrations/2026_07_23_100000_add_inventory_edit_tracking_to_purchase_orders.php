<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('inventory_edited_by')
                ->nullable()
                ->after('completed_by')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('inventory_edited_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_edited_by');
            $table->dropColumn('inventory_edited_at');
        });
    }
};
