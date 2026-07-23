<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('departments', 'area_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('area_id');
            });
        }

        Schema::dropIfExists('areas');
    }

    public function down(): void
    {
        if (!Schema::hasTable('areas')) {
            Schema::create('areas', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->boolean('active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('departments', 'area_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->foreignId('area_id')
                    ->nullable()
                    ->after('name')
                    ->constrained('areas')
                    ->nullOnDelete();
            });
        }
    }
};
