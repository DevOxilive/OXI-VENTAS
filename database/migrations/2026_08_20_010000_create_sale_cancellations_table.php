<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->unique()->constrained('sales')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cash_box_number', 10)->default('1');
            $table->decimal('amount', 12, 2)->default(0);
            $table->text('reason');
            $table->timestamp('cancelled_at');
            $table->timestamps();

            $table->index(['branch_id', 'cash_box_number', 'cancelled_at'], 'sale_cancellations_cut_index');
        });

        Schema::create('sale_cancellation_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_cancellation_id')->constrained('sale_cancellations')->cascadeOnDelete();
            $table->foreignId('sale_detail_id')->constrained('sale_details')->cascadeOnDelete();
            $table->foreignId('branch_product_id')->nullable()->constrained('branch_products')->nullOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('barcode_id')->nullable()->constrained('barcodes')->nullOnDelete();
            $table->foreignId('return_stock_movement_id')->nullable()->constrained('stock_movements')->nullOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->string('sale_unit', 20)->nullable();
            $table->decimal('base_quantity', 12, 3);
            $table->unsignedInteger('pieces_per_box')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();

            $table->unique('sale_detail_id', 'sale_cancellation_details_sale_detail_unique');
            $table->index('product_id', 'sale_cancellation_details_product_index');
        });

        Schema::table('sales', function (Blueprint $table) {
            if (! Schema::hasColumn('sales', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('status');
            }
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_movements', 'sale_id')) {
                $table->foreignId('sale_id')->nullable()->after('branch_product_id')->constrained('sales')->nullOnDelete();
            }

            if (! Schema::hasColumn('stock_movements', 'sale_detail_id')) {
                $table->foreignId('sale_detail_id')->nullable()->after('sale_id')->constrained('sale_details')->nullOnDelete();
            }

            if (! Schema::hasColumn('stock_movements', 'sale_cancellation_id')) {
                $table->foreignId('sale_cancellation_id')->nullable()->after('sale_detail_id')->constrained('sale_cancellations')->nullOnDelete();
            }

            if (! Schema::hasColumn('stock_movements', 'sale_cancellation_detail_id')) {
                $table->foreignId('sale_cancellation_detail_id')->nullable()->after('sale_cancellation_id')->constrained('sale_cancellation_details')->nullOnDelete();
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_movements MODIFY reason ENUM('PURCHASE', 'SALE', 'RETURN', 'DAMAGED', 'EXPIRED', 'OTHER', 'INVENTORY_DIFFERENCE') NOT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_movements')) {
            Schema::table('stock_movements', function (Blueprint $table) {
                foreach ([
                    'sale_cancellation_detail_id',
                    'sale_cancellation_id',
                    'sale_detail_id',
                    'sale_id',
                ] as $column) {
                    if (Schema::hasColumn('stock_movements', $column)) {
                        $table->dropConstrainedForeignId($column);
                    }
                }
            });
        }

        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'cancelled_at')) {
            Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('cancelled_at'));
        }

        Schema::dropIfExists('sale_cancellation_details');
        Schema::dropIfExists('sale_cancellations');

        if (Schema::hasTable('stock_movements') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE stock_movements MODIFY reason ENUM('PURCHASE', 'SALE', 'DAMAGED', 'EXPIRED', 'OTHER', 'INVENTORY_DIFFERENCE') NOT NULL");
        }
    }
};
