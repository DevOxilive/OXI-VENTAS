<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('branch_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->decimal('stock', 12, 3)->default(0);
            $table->decimal('min_stock', 12, 3)->default(0);

            $table->boolean('tracks_batches')->default(false);
            $table->boolean('tracks_expiration')->default(false);

            $table->enum('status', [
                'active',
                'inactive',
                'seasonal',
            ])->default('active');

            $table->date('season_start_date')->nullable();
            $table->date('season_end_date')->nullable();

            $table->timestamp('last_restocked_at')->nullable();

            $table->unsignedInteger('inactive_candidate_after_days')
                ->default(90);

            $table->timestamps();

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