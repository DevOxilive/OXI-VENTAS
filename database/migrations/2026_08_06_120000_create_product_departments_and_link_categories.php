<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_departments')) {
            Schema::create('product_departments', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('icon', 16)->nullable();
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('active')->default(true);
                $table->timestamps();

                $table->unique('name', 'product_departments_name_unique');
                $table->index(['active', 'sort_order'], 'product_departments_active_sort_index');
            });
        }

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (!Schema::hasColumn('categories', 'product_department_id')) {
                    $table->foreignId('product_department_id')
                        ->nullable()
                        ->after('id')
                        ->constrained('product_departments')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('categories', 'sort_order')) {
                    $table->unsignedSmallInteger('sort_order')
                        ->default(0)
                        ->after('name');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (Schema::hasColumn('categories', 'product_department_id')) {
                    $table->dropConstrainedForeignId('product_department_id');
                }

                if (Schema::hasColumn('categories', 'sort_order')) {
                    $table->dropColumn('sort_order');
                }
            });
        }

        Schema::dropIfExists('product_departments');
    }
};
