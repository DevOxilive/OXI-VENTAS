<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function addIfMissing(Blueprint $table, string $tableName, string $column, callable $callback): void
    {
        if (!Schema::hasColumn($tableName, $column)) {
            $callback($table);
        }
    }

    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $this->addIfMissing($table, 'categories', 'name', fn ($table) => $table->string('name')->after('id'));
            $this->addIfMissing($table, 'categories', 'active', fn ($table) => $table->boolean('active')->default(true)->after('name'));
        });

        Schema::table('products', function (Blueprint $table) {
            $this->addIfMissing($table, 'products', 'name', fn ($table) => $table->string('name')->after('id'));
            $this->addIfMissing($table, 'products', 'description', fn ($table) => $table->text('description')->nullable()->after('name'));
            $this->addIfMissing($table, 'products', 'price', fn ($table) => $table->decimal('price', 10, 2)->default(0)->after('description'));
            $this->addIfMissing($table, 'products', 'category_id', fn ($table) => $table->foreignId('category_id')->after('price')->constrained('categories')->cascadeOnDelete());
            $this->addIfMissing($table, 'products', 'active', fn ($table) => $table->boolean('active')->default(true)->after('category_id'));
        });

        Schema::table('barcodes', function (Blueprint $table) {
            $this->addIfMissing($table, 'barcodes', 'product_id', fn ($table) => $table->foreignId('product_id')->after('id')->constrained('products')->cascadeOnDelete());
            $this->addIfMissing($table, 'barcodes', 'code', fn ($table) => $table->string('code')->unique()->after('product_id'));
            $this->addIfMissing($table, 'barcodes', 'type', fn ($table) => $table->string('type')->nullable()->after('code'));
            $this->addIfMissing($table, 'barcodes', 'base_quantity', fn ($table) => $table->integer('base_quantity')->default(1)->after('type'));
            $this->addIfMissing($table, 'barcodes', 'active', fn ($table) => $table->boolean('active')->default(true)->after('base_quantity'));
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $this->addIfMissing($table, 'suppliers', 'name', fn ($table) => $table->string('name')->after('id'));
            $this->addIfMissing($table, 'suppliers', 'phone', fn ($table) => $table->string('phone')->nullable()->after('name'));
            $this->addIfMissing($table, 'suppliers', 'email', fn ($table) => $table->string('email')->nullable()->after('phone'));
            $this->addIfMissing($table, 'suppliers', 'address', fn ($table) => $table->text('address')->nullable()->after('email'));
            $this->addIfMissing($table, 'suppliers', 'active', fn ($table) => $table->boolean('active')->default(true)->after('address'));
        });

        Schema::table('customers', function (Blueprint $table) {
            $this->addIfMissing($table, 'customers', 'name', fn ($table) => $table->string('name')->after('id'));
            $this->addIfMissing($table, 'customers', 'phone', fn ($table) => $table->string('phone')->nullable()->after('name'));
            $this->addIfMissing($table, 'customers', 'email', fn ($table) => $table->string('email')->nullable()->after('phone'));
            $this->addIfMissing($table, 'customers', 'active', fn ($table) => $table->boolean('active')->default(true)->after('email'));
        });

        Schema::table('payment_methods', function (Blueprint $table) {
            $this->addIfMissing($table, 'payment_methods', 'name', fn ($table) => $table->string('name')->after('id'));
            $this->addIfMissing($table, 'payment_methods', 'active', fn ($table) => $table->boolean('active')->default(true)->after('name'));
        });

        Schema::table('inventory_lots', function (Blueprint $table) {
            $this->addIfMissing($table, 'inventory_lots', 'branch_id', fn ($table) => $table->unsignedBigInteger('branch_id')->after('id'));
            $this->addIfMissing($table, 'inventory_lots', 'product_id', fn ($table) => $table->foreignId('product_id')->after('branch_id')->constrained('products')->cascadeOnDelete());
            $this->addIfMissing($table, 'inventory_lots', 'lot_code', fn ($table) => $table->string('lot_code')->after('product_id'));
            $this->addIfMissing($table, 'inventory_lots', 'entry_date', fn ($table) => $table->date('entry_date')->after('lot_code'));
            $this->addIfMissing($table, 'inventory_lots', 'expiration_date', fn ($table) => $table->date('expiration_date')->nullable()->after('entry_date'));
            $this->addIfMissing($table, 'inventory_lots', 'current_stock', fn ($table) => $table->integer('current_stock')->default(0)->after('expiration_date'));
            $this->addIfMissing($table, 'inventory_lots', 'unit_cost', fn ($table) => $table->decimal('unit_cost', 10, 2)->default(0)->after('current_stock'));
            $this->addIfMissing($table, 'inventory_lots', 'active', fn ($table) => $table->boolean('active')->default(true)->after('unit_cost'));
        });

        Schema::table('branch_inventory', function (Blueprint $table) {
            $this->addIfMissing($table, 'branch_inventory', 'branch_id', fn ($table) => $table->unsignedBigInteger('branch_id')->after('id'));
            $this->addIfMissing($table, 'branch_inventory', 'product_id', fn ($table) => $table->foreignId('product_id')->after('branch_id')->constrained('products')->cascadeOnDelete());
            $this->addIfMissing($table, 'branch_inventory', 'current_stock', fn ($table) => $table->integer('current_stock')->default(0)->after('product_id'));
            $this->addIfMissing($table, 'branch_inventory', 'minimum_stock', fn ($table) => $table->integer('minimum_stock')->default(0)->after('current_stock'));
            $this->addIfMissing($table, 'branch_inventory', 'maximum_stock', fn ($table) => $table->integer('maximum_stock')->nullable()->after('minimum_stock'));
        });

        Schema::table('purchases', function (Blueprint $table) {
            $this->addIfMissing($table, 'purchases', 'supplier_id', fn ($table) => $table->foreignId('supplier_id')->after('id')->constrained('suppliers')->cascadeOnDelete());
            $this->addIfMissing($table, 'purchases', 'branch_id', fn ($table) => $table->unsignedBigInteger('branch_id')->after('supplier_id'));
            $this->addIfMissing($table, 'purchases', 'employee_id', fn ($table) => $table->unsignedBigInteger('employee_id')->after('branch_id'));
            $this->addIfMissing($table, 'purchases', 'date', fn ($table) => $table->dateTime('date')->after('employee_id'));
            $this->addIfMissing($table, 'purchases', 'total', fn ($table) => $table->decimal('total', 10, 2)->default(0)->after('date'));
            $this->addIfMissing($table, 'purchases', 'status', fn ($table) => $table->string('status')->default('completed')->after('total'));
        });

        Schema::table('purchase_details', function (Blueprint $table) {
            $this->addIfMissing($table, 'purchase_details', 'purchase_id', fn ($table) => $table->foreignId('purchase_id')->after('id')->constrained('purchases')->cascadeOnDelete());
            $this->addIfMissing($table, 'purchase_details', 'product_id', fn ($table) => $table->foreignId('product_id')->after('purchase_id')->constrained('products')->cascadeOnDelete());
            $this->addIfMissing($table, 'purchase_details', 'lot_id', fn ($table) => $table->foreignId('lot_id')->nullable()->after('product_id')->constrained('inventory_lots')->nullOnDelete());
            $this->addIfMissing($table, 'purchase_details', 'quantity', fn ($table) => $table->integer('quantity')->after('lot_id'));
            $this->addIfMissing($table, 'purchase_details', 'unit_cost', fn ($table) => $table->decimal('unit_cost', 10, 2)->after('quantity'));
            $this->addIfMissing($table, 'purchase_details', 'subtotal', fn ($table) => $table->decimal('subtotal', 10, 2)->after('unit_cost'));
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $this->addIfMissing($table, 'inventory_movements', 'branch_id', fn ($table) => $table->unsignedBigInteger('branch_id')->after('id'));
            $this->addIfMissing($table, 'inventory_movements', 'product_id', fn ($table) => $table->foreignId('product_id')->after('branch_id')->constrained('products')->cascadeOnDelete());
            $this->addIfMissing($table, 'inventory_movements', 'lot_id', fn ($table) => $table->foreignId('lot_id')->nullable()->after('product_id')->constrained('inventory_lots')->nullOnDelete());
            $this->addIfMissing($table, 'inventory_movements', 'type', fn ($table) => $table->string('type')->after('lot_id'));
            $this->addIfMissing($table, 'inventory_movements', 'quantity', fn ($table) => $table->integer('quantity')->after('type'));
            $this->addIfMissing($table, 'inventory_movements', 'date', fn ($table) => $table->dateTime('date')->after('quantity'));
            $this->addIfMissing($table, 'inventory_movements', 'reference', fn ($table) => $table->string('reference')->nullable()->after('date'));
        });

        Schema::table('sales', function (Blueprint $table) {
            $this->addIfMissing($table, 'sales', 'date', fn ($table) => $table->dateTime('date')->after('id'));
            $this->addIfMissing($table, 'sales', 'employee_id', fn ($table) => $table->unsignedBigInteger('employee_id')->after('date'));
            $this->addIfMissing($table, 'sales', 'customer_id', fn ($table) => $table->foreignId('customer_id')->nullable()->after('employee_id')->constrained('customers')->nullOnDelete());
            $this->addIfMissing($table, 'sales', 'branch_id', fn ($table) => $table->unsignedBigInteger('branch_id')->after('customer_id'));
            $this->addIfMissing($table, 'sales', 'payment_method_id', fn ($table) => $table->foreignId('payment_method_id')->after('branch_id')->constrained('payment_methods')->cascadeOnDelete());
            $this->addIfMissing($table, 'sales', 'total', fn ($table) => $table->decimal('total', 10, 2)->default(0)->after('payment_method_id'));
            $this->addIfMissing($table, 'sales', 'status', fn ($table) => $table->string('status')->default('completed')->after('total'));
        });

        Schema::table('sale_details', function (Blueprint $table) {
            $this->addIfMissing($table, 'sale_details', 'sale_id', fn ($table) => $table->foreignId('sale_id')->after('id')->constrained('sales')->cascadeOnDelete());
            $this->addIfMissing($table, 'sale_details', 'product_id', fn ($table) => $table->foreignId('product_id')->after('sale_id')->constrained('products')->cascadeOnDelete());
            $this->addIfMissing($table, 'sale_details', 'barcode_id', fn ($table) => $table->foreignId('barcode_id')->nullable()->after('product_id')->constrained('barcodes')->nullOnDelete());
            $this->addIfMissing($table, 'sale_details', 'lot_id', fn ($table) => $table->foreignId('lot_id')->nullable()->after('barcode_id')->constrained('inventory_lots')->nullOnDelete());
            $this->addIfMissing($table, 'sale_details', 'quantity', fn ($table) => $table->integer('quantity')->after('lot_id'));
            $this->addIfMissing($table, 'sale_details', 'unit_price', fn ($table) => $table->decimal('unit_price', 10, 2)->after('quantity'));
            $this->addIfMissing($table, 'sale_details', 'subtotal', fn ($table) => $table->decimal('subtotal', 10, 2)->after('unit_price'));
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $this->addIfMissing($table, 'vehicles', 'plate', fn ($table) => $table->string('plate')->unique()->after('id'));
            $this->addIfMissing($table, 'vehicles', 'model', fn ($table) => $table->string('model')->nullable()->after('plate'));
            $this->addIfMissing($table, 'vehicles', 'capacity', fn ($table) => $table->decimal('capacity', 10, 2)->nullable()->after('model'));
            $this->addIfMissing($table, 'vehicles', 'active', fn ($table) => $table->boolean('active')->default(true)->after('capacity'));
        });

        Schema::table('trips', function (Blueprint $table) {
            $this->addIfMissing($table, 'trips', 'departure_date', fn ($table) => $table->dateTime('departure_date')->after('id'));
            $this->addIfMissing($table, 'trips', 'arrival_date', fn ($table) => $table->dateTime('arrival_date')->nullable()->after('departure_date'));
            $this->addIfMissing($table, 'trips', 'employee_id', fn ($table) => $table->unsignedBigInteger('employee_id')->after('arrival_date'));
            $this->addIfMissing($table, 'trips', 'vehicle_id', fn ($table) => $table->foreignId('vehicle_id')->after('employee_id')->constrained('vehicles')->cascadeOnDelete());
            $this->addIfMissing($table, 'trips', 'destination', fn ($table) => $table->string('destination')->after('vehicle_id'));
            $this->addIfMissing($table, 'trips', 'status', fn ($table) => $table->string('status')->default('pending')->after('destination'));
        });

        Schema::table('trip_details', function (Blueprint $table) {
            $this->addIfMissing($table, 'trip_details', 'trip_id', fn ($table) => $table->foreignId('trip_id')->after('id')->constrained('trips')->cascadeOnDelete());
            $this->addIfMissing($table, 'trip_details', 'cargo_description', fn ($table) => $table->string('cargo_description')->after('trip_id'));
            $this->addIfMissing($table, 'trip_details', 'quantity', fn ($table) => $table->integer('quantity')->default(1)->after('cargo_description'));
            $this->addIfMissing($table, 'trip_details', 'weight', fn ($table) => $table->decimal('weight', 10, 2)->nullable()->after('quantity'));
        });

        Schema::table('incidents', function (Blueprint $table) {
            $this->addIfMissing($table, 'incidents', 'trip_id', fn ($table) => $table->foreignId('trip_id')->after('id')->constrained('trips')->cascadeOnDelete());
            $this->addIfMissing($table, 'incidents', 'description', fn ($table) => $table->text('description')->after('trip_id'));
            $this->addIfMissing($table, 'incidents', 'date', fn ($table) => $table->dateTime('date')->after('description'));
        });
    }

    public function down(): void
    {
        //
    }
};