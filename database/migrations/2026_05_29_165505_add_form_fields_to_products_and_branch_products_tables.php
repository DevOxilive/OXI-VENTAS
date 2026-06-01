<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'image')) {
                $table->string('image')->nullable()->after('description');
            }
        });

        Schema::table('branch_products', function (Blueprint $table) {
            if (!Schema::hasColumn('branch_products', 'name')) {
                $table->string('name')->nullable()->after('product_id');
            }

            if (!Schema::hasColumn('branch_products', 'barcode')) {
                $table->string('barcode')->nullable()->after('name');
            }

            if (!Schema::hasColumn('branch_products', 'category_id')) {
                $table->foreignId('category_id')
                    ->nullable()
                    ->after('barcode')
                    ->constrained('categories')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('branch_products', 'unit')) {
                $table->string('unit')->nullable()->after('category_id');
            }

            if (!Schema::hasColumn('branch_products', 'cost')) {
                $table->decimal('cost', 10, 2)->default(0)->after('unit');
            }

            if (!Schema::hasColumn('branch_products', 'entry_date')) {
                $table->date('entry_date')->nullable()->after('min_stock');
            }
        });
    }

    public function down(): void
    {
        Schema::table('branch_products', function (Blueprint $table) {
            if (Schema::hasColumn('branch_products', 'entry_date')) {
                $table->dropColumn('entry_date');
            }

            if (Schema::hasColumn('branch_products', 'cost')) {
                $table->dropColumn('cost');
            }

            if (Schema::hasColumn('branch_products', 'unit')) {
                $table->dropColumn('unit');
            }

            if (Schema::hasColumn('branch_products', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }

            if (Schema::hasColumn('branch_products', 'barcode')) {
                $table->dropColumn('barcode');
            }

            if (Schema::hasColumn('branch_products', 'name')) {
                $table->dropColumn('name');
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'image')) {
                $table->dropColumn('image');
            }
        });
    }
};