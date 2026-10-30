<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_tasks', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 40);
            $table->json('payload');
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->timestamp('locked_at')->nullable();
            $table->uuid('lock_token')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at', 'id'], 'inventory_tasks_pending_index');
            $table->index(['status', 'locked_at'], 'inventory_tasks_lease_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_tasks');
    }
};
