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
    Schema::create('physical_count_entries', function (Blueprint $table) {
        $table->id();

        $table->foreignId('physical_count_id')->constrained()->cascadeOnDelete();

        $table->foreignId('branch_product_id')->constrained()->cascadeOnDelete();
        $table->foreignId('product_id')->constrained()->cascadeOnDelete();
        $table->foreignId('user_id')->constrained()->cascadeOnDelete();

        $table->string('scanned_code')->nullable();

        $table->decimal('counted_quantity', 10, 2)->default(0);
        $table->decimal('damaged_quantity', 10, 2)->default(0);
        $table->decimal('expired_quantity', 10, 2)->default(0);

        $table->date('expiration_date')->nullable();
        $table->text('notes')->nullable();

        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('physical_count_entries');
    }
};

