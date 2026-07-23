<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_schedule_assignments', function (Blueprint $table) {
            $table->text('observations')->nullable()->after('priority');
            $table->foreignId('assigned_by')->nullable()->after('observations')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('attendance_schedule_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn('observations');
        });
    }
};
