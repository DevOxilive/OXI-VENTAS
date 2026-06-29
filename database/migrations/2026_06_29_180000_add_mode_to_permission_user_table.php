<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permission_user', function (Blueprint $table) {
            $table->string('mode', 10)
                ->default('allow')
                ->after('permission_id');
        });

        $duplicates = DB::table('permission_user')
            ->select('user_id', 'permission_id', DB::raw('MIN(id) as keep_id'))
            ->groupBy('user_id', 'permission_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('permission_user')
                ->where('user_id', $duplicate->user_id)
                ->where('permission_id', $duplicate->permission_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('permission_user', function (Blueprint $table) {
            $table->unique(['user_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::table('permission_user', function (Blueprint $table) {
            $table->dropUnique('permission_user_user_id_permission_id_unique');
            $table->dropColumn('mode');
        });
    }
};
