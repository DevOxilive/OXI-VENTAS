<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();

            $table->decimal('cost', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->string('unit')->default('pieza');

            $table->foreignId('category_id')
                ->constrained('categories')
                ->cascadeOnDelete();

            $table->foreignId('subcategory_id')
                ->nullable()
                ->constrained('subcategories')
                ->nullOnDelete();

            $table->boolean('active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};