<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('general_purchase_order_item_batches', function (Blueprint $table) { $table->json('allocations')->nullable(); $table->string('supplier', 80)->nullable(); }); }
    public function down(): void { Schema::table('general_purchase_order_item_batches', fn (Blueprint $table) => $table->dropColumn(['allocations', 'supplier'])); }
};
