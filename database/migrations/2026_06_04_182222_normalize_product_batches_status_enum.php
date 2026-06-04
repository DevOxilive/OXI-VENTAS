<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            UPDATE product_batches
            SET status = 'ACTIVE'
            WHERE status IN ('EXPIRED', 'DEPLETED', 'RETURNED')
        ");

        DB::statement("
            ALTER TABLE product_batches
            MODIFY COLUMN status ENUM(
                'ACTIVE',
                'INACTIVE',
                'SEASONAL'
            ) NOT NULL DEFAULT 'ACTIVE'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE product_batches
            MODIFY COLUMN status ENUM(
                'ACTIVE',
                'EXPIRED',
                'DEPLETED',
                'RETURNED'
            ) NOT NULL DEFAULT 'ACTIVE'
        ");
    }
};