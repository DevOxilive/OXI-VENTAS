<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sale_cancellations', function (Blueprint $table) {
            $table->index('sale_id', 'sale_cancellations_sale_id_index');
            $table->dropUnique('sale_cancellations_sale_id_unique');
        });

        Schema::table('sale_cancellation_details', function (Blueprint $table) {
            $table->index('sale_detail_id', 'sale_cancellation_details_sale_detail_index');
            $table->dropUnique('sale_cancellation_details_sale_detail_unique');
        });
    }

    public function down(): void
    {
        // Una venta o renglón puede tener varias devoluciones parciales; no es seguro
        // restablecer las restricciones únicas mientras existan esos registros.
    }
};
