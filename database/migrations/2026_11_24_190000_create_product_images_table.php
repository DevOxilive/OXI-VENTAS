<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('position');
            $table->timestamps();

            // Cada posición pertenece a una sola imagen del producto. La capa
            // de captura validará el límite de ocho posiciones en el siguiente paso.
            $table->unique(['product_id', 'position']);
        });

        // Conserva las fotografías que ya existen: la imagen histórica pasa a
        // ser la primera de la nueva colección, sin cambiar su ruta ni borrar
        // la columna products.image que todavía usan pantallas existentes.
        DB::table('products')
            ->select(['id', 'image', 'created_at', 'updated_at'])
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->orderBy('id')
            ->eachById(function (object $product): void {
                DB::table('product_images')->insert([
                    'product_id' => $product->id,
                    'path' => $product->image,
                    'position' => 1,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
