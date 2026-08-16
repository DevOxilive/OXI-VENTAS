<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_schedule_assignments', function (Blueprint $table) {
            $table->unsignedSmallInteger('shift_order')->default(1)->after('priority');
            $table->index(
                ['assignable_type', 'assignable_id', 'active', 'shift_order'],
                'attendance_assignment_shift_lookup_index'
            );
        });

        Schema::table('attendance_records', function (Blueprint $table) {
            $table->foreignId('attendance_schedule_assignment_id')
                ->nullable()
                ->after('employee_id')
                ->constrained()
                ->nullOnDelete();
            $table->string('shift_label', 120)->nullable()->after('type');
            $table->unsignedSmallInteger('shift_order')->default(1)->after('shift_label');
            $table->index(
                ['user_id', 'attendance_date', 'attendance_schedule_assignment_id', 'type'],
                'attendance_record_shift_type_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropIndex('attendance_record_shift_type_index');
            $table->dropConstrainedForeignId('attendance_schedule_assignment_id');
            $table->dropColumn(['shift_label', 'shift_order']);
        });

        Schema::table('attendance_schedule_assignments', function (Blueprint $table) {
            $table->dropIndex('attendance_assignment_shift_lookup_index');
            $table->dropColumn('shift_order');
        });
    }
};
