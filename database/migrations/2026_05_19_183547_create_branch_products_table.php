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
            |
            | stock:
            | stock total vivo del producto en la sucursal.
            |
            | min_stock:
            | mínimo permitido antes de alertar.
            |
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
            |
            | tracks_batches:
            | indica si este producto maneja lotes.
            |
            | tracks_expiration:
            | indica si maneja control de caducidad.
            |
            | IMPORTANTE:
            | no todos los productos necesitan lotes/caducidad.
            |
            */

            $table->boolean('tracks_batches')
                ->default(false);

            $table->boolean('tracks_expiration')
                ->default(false);

            /*
            |--------------------------------------------------------------------------
            | Estado
            |--------------------------------------------------------------------------
            */

            $table->boolean('active')
                ->default(true);

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_products');
    }
};
