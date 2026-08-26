<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('last_name');
            }

            if (! Schema::hasColumn('employees', 'emergency_contact_name')) {
                $table->string('emergency_contact_name')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('employees', 'emergency_contact_relationship')) {
                $table->string('emergency_contact_relationship', 80)->nullable()->after('emergency_contact_name');
            }

            if (! Schema::hasColumn('employees', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_relationship');
            }

            if (! Schema::hasColumn('employees', 'secondary_emergency_contact_name')) {
                $table->string('secondary_emergency_contact_name')->nullable()->after('emergency_contact_phone');
            }

            if (! Schema::hasColumn('employees', 'secondary_emergency_contact_relationship')) {
                $table->string('secondary_emergency_contact_relationship', 80)->nullable()->after('secondary_emergency_contact_name');
            }

            if (! Schema::hasColumn('employees', 'secondary_emergency_contact_phone')) {
                $table->string('secondary_emergency_contact_phone', 20)->nullable()->after('secondary_emergency_contact_relationship');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $columns = [
                'secondary_emergency_contact_phone',
                'secondary_emergency_contact_relationship',
                'secondary_emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relationship',
                'emergency_contact_name',
                'birth_date',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
