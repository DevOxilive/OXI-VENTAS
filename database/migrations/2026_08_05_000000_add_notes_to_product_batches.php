<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_batches', 'notes')) {
            Schema::table('product_batches', function (Blueprint $table) {
                $table->text('notes')->nullable()->after('supplier');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_batches', 'notes')) {
            Schema::table('product_batches', function (Blueprint $table) {
                $table->dropColumn('notes');
            });
        }
    }
};
