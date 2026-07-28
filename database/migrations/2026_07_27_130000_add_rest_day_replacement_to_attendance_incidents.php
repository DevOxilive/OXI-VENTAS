<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_incidents', function (Blueprint $table) {
            $table->boolean('rest_day_requested')->default(false)->after('estimated_arrival_at');
            $table->date('rest_day_date')->nullable()->after('rest_day_requested');
            $table->date('make_up_date')->nullable()->after('rest_day_date');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_incidents', function (Blueprint $table) {
            $table->dropColumn([
                'rest_day_requested',
                'rest_day_date',
                'make_up_date',
            ]);
        });
    }
};
