<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            if (!Schema::hasColumn('positions', 'name')) {
                $table->string('name')->after('id');
            }

            if (!Schema::hasColumn('positions', 'description')) {
                $table->text('description')->nullable()->after('name');
            }

            if (!Schema::hasColumn('positions', 'department_id')) {
                $table->foreignId('department_id')
                    ->after('description')
                    ->constrained('departments')
                    ->cascadeOnDelete();
            }

            if (!Schema::hasColumn('positions', 'active')) {
                $table->boolean('active')
                    ->default(true)
                    ->after('department_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('positions', function (Blueprint $table) {
            if (Schema::hasColumn('positions', 'department_id')) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            }

            if (Schema::hasColumn('positions', 'description')) {
                $table->dropColumn('description');
            }

            if (Schema::hasColumn('positions', 'active')) {
                $table->dropColumn('active');
            }
        });
    }
};