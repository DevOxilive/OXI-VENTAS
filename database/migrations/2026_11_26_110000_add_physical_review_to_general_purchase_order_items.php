<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('general_purchase_order_items', fn (Blueprint $table) => $table->boolean('physically_reviewed')->default(false));
    }
    public function down(): void
    {
        Schema::table('general_purchase_order_items', fn (Blueprint $table) => $table->dropColumn('physically_reviewed'));
    }
};
