<?php

use App\Support\SystemPermission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $softDeleteTables = [
        'users',
        'branches',
        'employees',
        'customers',
        'products',
        'categories',
        'cash_register_closures',
        'physical_counts',
        'physical_count_entries',
        'purchase_reports',
        'ticket_templates',
    ];

    public function up(): void
    {
        Schema::create('system_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('role_name')->nullable();
            $table->string('module', 120)->index();
            $table->string('action', 80)->index();
            $table->nullableMorphs('auditable');
            $table->string('record_label')->nullable();
            $table->string('result', 20)->default('success')->index();
            $table->text('observations')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('browser')->nullable();
            $table->string('operating_system')->nullable();
            $table->string('device')->nullable();
            $table->text('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });

        foreach ($this->softDeleteTables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->softDeletes());
            }
        }

        $now = now();
        $permissions = array_merge(SystemPermission::exclusive(), [SystemPermission::BRANCHES_ACCESS_ALL]);

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        DB::table('roles')->updateOrInsert(
            ['name' => 'Super Administrador'],
            ['created_at' => $now, 'updated_at' => $now],
        );

        $superAdministratorRoleId = DB::table('roles')->where('name', 'Super Administrador')->value('id');
        $administratorRoleId = DB::table('roles')->where('name', 'Administrador')->value('id');

        $allPermissionIds = DB::table('permissions')->pluck('id');
        foreach ($allPermissionIds as $permissionId) {
            DB::table('role_permission')->updateOrInsert([
                'role_id' => $superAdministratorRoleId,
                'permission_id' => $permissionId,
            ]);
        }

        if ($administratorRoleId) {
            $branchPermissionId = DB::table('permissions')
                ->where('name', SystemPermission::BRANCHES_ACCESS_ALL)
                ->value('id');

            DB::table('role_permission')->updateOrInsert([
                'role_id' => $administratorRoleId,
                'permission_id' => $branchPermissionId,
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->softDeleteTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropSoftDeletes());
            }
        }

        Schema::dropIfExists('system_audits');
    }
};
