<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('completed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('folio')->nullable()->unique();
            $table->string('source')->default('CENTRAL');

            $table->enum('status', [
                'DRAFT',
                'GENERATED',
                'COMPLETED',
                'CANCELLED',
            ])->default('DRAFT');

            $table->decimal('estimated_total', 12, 2)->default(0);
            $table->decimal('actual_total', 12, 2)->default(0);

            $table->date('purchased_at')->nullable();
            $table->text('notes')->nullable();
            $table->text('adjustment_notes')->nullable();

            $table->timestamp('generated_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['source', 'status']);
            $table->index('purchased_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
