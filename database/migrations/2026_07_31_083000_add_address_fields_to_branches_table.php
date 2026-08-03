<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'address')) {
                $table->text('address')->nullable()->after('color');
            }
            if (! Schema::hasColumn('branches', 'street')) {
                $table->string('street')->nullable()->after('address');
            }
            if (! Schema::hasColumn('branches', 'external_number')) {
                $table->string('external_number')->nullable()->after('street');
            }
            if (! Schema::hasColumn('branches', 'internal_number')) {
                $table->string('internal_number')->nullable()->after('external_number');
            }
            if (! Schema::hasColumn('branches', 'postal_code')) {
                $table->string('postal_code', 5)->nullable()->after('internal_number');
            }
            if (! Schema::hasColumn('branches', 'neighborhood')) {
                $table->string('neighborhood')->nullable()->after('postal_code');
            }
            if (! Schema::hasColumn('branches', 'municipality')) {
                $table->string('municipality')->nullable()->after('neighborhood');
            }
            if (! Schema::hasColumn('branches', 'address_state')) {
                $table->string('address_state')->nullable()->after('municipality');
            }
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'street',
                'external_number',
                'internal_number',
                'postal_code',
                'neighborhood',
                'municipality',
                'address_state',
            ]);
        });
    }
};
