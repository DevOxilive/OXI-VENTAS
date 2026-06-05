<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
 public function up(): void
{
    Schema::table('physical_count_entries', function (Blueprint $table) {
        $table->foreignId('product_batch_id')
            ->nullable()
            ->after('branch_product_id')
            ->constrained('product_batches')
            ->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('physical_count_entries', function (Blueprint $table) {
        $table->dropForeign(['product_batch_id']);
        $table->dropColumn('product_batch_id');
    });
}
};
