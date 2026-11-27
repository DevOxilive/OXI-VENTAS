<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            $table->decimal('calculated_subtotal', 12, 2)->nullable()->after('subtotal');
            $table->decimal('rounding_adjustment', 12, 2)->default(0)->after('calculated_subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropColumn(['calculated_subtotal', 'rounding_adjustment']);
        });
    }
};
