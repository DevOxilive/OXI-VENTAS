<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->nullable()->unique();
            $table->string('status')->default('OPEN');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('consolidated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'opened_at']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('purchase_cycle_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('purchase_cycles')
                ->nullOnDelete();
        });

        Schema::create('purchase_cycle_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_cycle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('submitted_without_items')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['purchase_cycle_id', 'branch_id'], 'purchase_cycle_branch_unique');
            $table->unique('purchase_order_id');
        });

        Schema::create('general_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_cycle_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('folio')->nullable()->unique();
            $table->string('status')->default('DRAFT');
            $table->decimal('estimated_total', 14, 2)->default(0);
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('actual_total', 14, 2)->default(0);
            $table->date('purchased_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('purchase_cycle_id', 'general_purchase_orders_cycle_index');
            $table->index(['status', 'created_at']);
        });

        Schema::create('general_purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('general_purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->text('product_description')->nullable();
            $table->string('product_code')->nullable();
            $table->string('base_unit')->nullable();
            $table->decimal('requested_quantity', 12, 2)->default(0);
            $table->decimal('estimated_unit_price', 12, 2)->default(0);
            $table->decimal('estimated_total', 14, 2)->default(0);
            $table->string('purchase_presentation')->default('Paquete');
            $table->decimal('package_quantity', 10, 2)->nullable();
            $table->decimal('units_per_package', 10, 2)->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->decimal('purchased_quantity', 12, 2)->default(0);
            $table->decimal('gross_total', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('actual_total', 14, 2)->default(0);
            $table->decimal('net_unit_cost', 12, 4)->default(0);
            $table->boolean('unavailable')->default(false);
            $table->text('purchase_notes')->nullable();
            $table->timestamps();

            $table->unique(
                ['general_purchase_order_id', 'product_id'],
                'general_purchase_order_product_unique'
            );
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('general_purchase_order_id')
                ->nullable()
                ->after('purchase_cycle_id')
                ->constrained('general_purchase_orders')
                ->nullOnDelete();

            $table->index(['purchase_cycle_id', 'status']);
            $table->index(['general_purchase_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['general_purchase_order_id']);
            $table->dropForeign(['purchase_cycle_id']);
            $table->dropIndex(['purchase_cycle_id', 'status']);
            $table->dropIndex(['general_purchase_order_id', 'status']);
            $table->dropColumn(['general_purchase_order_id', 'purchase_cycle_id']);
        });

        Schema::dropIfExists('general_purchase_order_items');
        Schema::dropIfExists('general_purchase_orders');
        Schema::dropIfExists('purchase_cycle_branches');
        Schema::dropIfExists('purchase_cycles');
    }
};
