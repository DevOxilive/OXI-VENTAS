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
    Schema::create('product_alternative_codes', function (Blueprint $table) {
        $table->id();

        $table->foreignId('product_id')->constrained()->cascadeOnDelete();

        $table->string('code')->index();
        $table->string('description')->nullable();

        $table->timestamps();

        $table->unique(['product_id', 'code']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_alternative_codes');
    }
};
