<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_closures', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->unsignedInteger('sales_count')->default(0);
            $table->decimal('sales_total', 12, 2)->default(0);
            $table->decimal('expected_cash', 12, 2)->default(0);
            $table->decimal('counted_cash', 12, 2)->default(0);
            $table->decimal('cash_difference', 12, 2)->default(0);
            $table->json('payment_breakdown')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_closures');
    }
};
