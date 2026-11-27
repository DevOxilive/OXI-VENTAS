<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('purchase_preparations', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('responsible_id')->nullable()->constrained('users');
            $table->json('order_ids')->nullable();
            $table->timestamps();
        });
        Schema::create('purchase_review_presence', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->primary(['purchase_order_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_review_presence');
        Schema::dropIfExists('purchase_preparations');
    }
};
