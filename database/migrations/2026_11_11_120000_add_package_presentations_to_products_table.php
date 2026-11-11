<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Una caja puede contener muchos paquetes; la unidad interna sigue
            // siendo pieza y el total puede superar el límite anterior de 65,535.
            $table->unsignedInteger('pieces_per_box')->nullable()->change();
            $table->boolean('has_package_presentation')->default(false)->after('has_box_presentation');
            $table->unsignedSmallInteger('pieces_per_package')->nullable()->after('pieces_per_box');
            $table->unsignedSmallInteger('packages_per_box')->nullable()->after('pieces_per_package');
            $table->decimal('cost_per_package', 12, 4)->nullable()->after('cost_per_box');
            $table->decimal('sale_price_per_package', 12, 4)->nullable()->after('sale_price_per_box');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'has_package_presentation', 'pieces_per_package', 'packages_per_box',
                'cost_per_package', 'sale_price_per_package',
            ]);
            $table->unsignedSmallInteger('pieces_per_box')->nullable()->change();
        });
    }
};
