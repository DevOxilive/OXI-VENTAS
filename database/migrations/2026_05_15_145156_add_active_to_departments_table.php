<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (!Schema::hasColumn('departments', 'name')) {
                $table->string('name')->after('id');
            }

            if (!Schema::hasColumn('departments', 'active')) {
                $table->boolean('active')
                    ->default(true)
                    ->after('name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'active')) {
                $table->dropColumn('active');
            }
        });
    }
};
