<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'folio')) {
                $table->string('folio')->nullable()->unique()->after('id');
            }
        });

        DB::table('sales')
            ->select('id')
            ->orderBy('id')
            ->chunkById(200, function ($sales) {
                foreach ($sales as $sale) {
                    DB::table('sales')
                        ->where('id', $sale->id)
                        ->update([
                            'folio' => 'V-' . str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT),
                        ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'folio')) {
                $table->dropColumn('folio');
            }
        });
    }
};
