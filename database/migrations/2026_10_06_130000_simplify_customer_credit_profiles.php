<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('first_name', 80)->nullable()->after('name');
            $table->string('last_name', 80)->nullable()->after('first_name');
        });

        DB::table('customers')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->chunkById(200, function ($customers) {
                foreach ($customers as $customer) {
                    $parts = preg_split('/\s+/u', trim((string) $customer->name), 2) ?: [];
                    DB::table('customers')->where('id', $customer->id)->update([
                        'first_name' => $parts[0] ?? (string) $customer->name,
                        'last_name' => $parts[1] ?? null,
                    ]);
                }
            });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('active');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('email');
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
