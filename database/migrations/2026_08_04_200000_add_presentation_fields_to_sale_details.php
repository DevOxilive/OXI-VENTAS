<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_details', function (Blueprint $table) {
            $table->string('sale_unit', 12)->default('piece')->after('quantity');
            $table->decimal('base_quantity', 12, 3)->nullable()->after('sale_unit');
            $table->unsignedInteger('pieces_per_box')->nullable()->after('base_quantity');
        });

        DB::table('sale_details')->update([
            'base_quantity' => DB::raw('quantity'),
        ]);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE sale_details MODIFY quantity DECIMAL(12,3) NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE sale_details MODIFY quantity INT NOT NULL');
        }

        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropColumn(['sale_unit', 'base_quantity', 'pieces_per_box']);
        });
    }
};
