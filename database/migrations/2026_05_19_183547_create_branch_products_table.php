<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('branch_products', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | Relaciones
            |--------------------------------------------------------------------------
            */

            $table->foreignId('branch_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            /*
            |--------------------------------------------------------------------------
            | Inventario general
            |--------------------------------------------------------------------------
            */

            $table->decimal('price', 10, 2);

            $table->integer('stock')
                ->default(0);

            $table->integer('min_stock')
                ->default(0);

            /*
            |--------------------------------------------------------------------------
            | Configuración operativa
            |--------------------------------------------------------------------------
            */

            $table->boolean('tracks_batches')
                ->default(false);

            $table->boolean('tracks_expiration')
                ->default(false);

            /*
            |--------------------------------------------------------------------------
            | Estado administrativo y control de surtido
            |--------------------------------------------------------------------------
            */

            $table->enum('status', [
                'active',
                'inactive',
                'seasonal',
            ])->default('active');

            $table->timestamp('last_restocked_at')
                ->nullable();

            $table->unsignedInteger('inactive_candidate_after_days')
                ->default(90);

            $table->timestamps();

            /*
            |--------------------------------------------------------------------------
            | Un producto solo puede existir una vez por sucursal
            |--------------------------------------------------------------------------
            */

            $table->unique([
                'branch_id',
                'product_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_products');
    }
};