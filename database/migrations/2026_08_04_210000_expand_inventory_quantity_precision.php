<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->change('general_purchase_order_items', [
            'requested_quantity' => 'DECIMAL(12,3) NOT NULL DEFAULT 0',
            'package_quantity' => 'DECIMAL(12,3) NULL',
            'units_per_package' => 'DECIMAL(12,3) NULL',
            'purchased_quantity' => 'DECIMAL(12,3) NOT NULL DEFAULT 0',
        ]);
        $this->change('purchase_order_items', [
            'current_stock' => 'DECIMAL(12,3) NOT NULL DEFAULT 0',
            'min_stock' => 'DECIMAL(12,3) NOT NULL DEFAULT 0',
            'requested_quantity' => 'DECIMAL(12,3) NOT NULL DEFAULT 0',
            'purchased_quantity' => 'DECIMAL(12,3) NULL',
            'received_quantity' => 'DECIMAL(12,3) NULL',
        ]);
        $this->change('purchase_report_items', [
            'current_stock' => 'DECIMAL(12,3) NOT NULL DEFAULT 0',
            'min_stock' => 'DECIMAL(12,3) NOT NULL DEFAULT 0',
            'requested_quantity' => 'DECIMAL(12,3) NOT NULL DEFAULT 0',
            'purchased_quantity' => 'DECIMAL(12,3) NULL',
        ]);
        $this->change('physical_count_entries', [
            'counted_quantity' => 'DECIMAL(12,3) NOT NULL DEFAULT 0',
            'damaged_quantity' => 'DECIMAL(12,3) NOT NULL DEFAULT 0',
            'expired_quantity' => 'DECIMAL(12,3) NOT NULL DEFAULT 0',
        ]);
        $this->change('physical_count_snapshot_items', [
            'system_stock' => 'DECIMAL(12,3) NOT NULL DEFAULT 0',
            'batch_stock' => 'DECIMAL(12,3) NOT NULL DEFAULT 0',
        ]);
    }

    public function down(): void
    {
        $this->change('general_purchase_order_items', [
            'requested_quantity' => 'DECIMAL(12,2) NOT NULL DEFAULT 0', 'package_quantity' => 'DECIMAL(12,2) NULL',
            'units_per_package' => 'DECIMAL(12,2) NULL', 'purchased_quantity' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        ]);
        $this->change('purchase_order_items', [
            'current_stock' => 'DECIMAL(12,2) NOT NULL DEFAULT 0', 'min_stock' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'requested_quantity' => 'DECIMAL(12,2) NOT NULL DEFAULT 0', 'purchased_quantity' => 'DECIMAL(12,2) NULL', 'received_quantity' => 'DECIMAL(12,2) NULL',
        ]);
        $this->change('purchase_report_items', [
            'current_stock' => 'DECIMAL(12,2) NOT NULL DEFAULT 0', 'min_stock' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            'requested_quantity' => 'DECIMAL(12,2) NOT NULL DEFAULT 0', 'purchased_quantity' => 'DECIMAL(12,2) NULL',
        ]);
        $this->change('physical_count_entries', [
            'counted_quantity' => 'DECIMAL(12,2) NOT NULL DEFAULT 0', 'damaged_quantity' => 'DECIMAL(12,2) NOT NULL DEFAULT 0', 'expired_quantity' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        ]);
        $this->change('physical_count_snapshot_items', [
            'system_stock' => 'DECIMAL(12,2) NOT NULL DEFAULT 0', 'batch_stock' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
        ]);
    }

    private function change(string $table, array $columns): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column => $definition) {
            if (Schema::hasColumn($table, $column)) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition} NULL");
            }
        }
    }
};
