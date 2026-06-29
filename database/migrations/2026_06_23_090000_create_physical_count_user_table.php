<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('physical_count_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physical_count_id')
                ->constrained('physical_counts')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['physical_count_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physical_count_user');
    }
};
