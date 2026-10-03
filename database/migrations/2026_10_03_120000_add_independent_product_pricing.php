<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Existing prices stay fixed until an administrator chooses automatic pricing.
            $table->string('piece_pricing_mode', 20)->default('manual');
            $table->string('box_pricing_mode', 20)->default('manual');
            $table->decimal('piece_margin_percentage', 12, 4)->nullable();
            $table->decimal('box_margin_percentage', 12, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn([
            'piece_pricing_mode', 'box_pricing_mode',
            'piece_margin_percentage', 'box_margin_percentage',
        ]));
    }
};
