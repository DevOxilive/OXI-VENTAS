<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $permissions = [
        'sales.customers.view',
        'sales.customers.create',
        'sales.customers.update',
        'sales.customers.delete',
    ];

    public function up(): void
    {
        Schema::table('employee_credit_accounts', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropUnique(['employee_id']);
        });

        Schema::table('employee_credit_accounts', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->change();
            $table->foreignId('customer_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('customers')
                ->nullOnDelete();
            $table->unique('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->unique('customer_id');
        });

        $now = now();
        foreach ($this->permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('name', $this->permissions)->pluck('id');
        $roleIds = DB::table('roles')->whereIn('name', ['Administrador', 'Super Administrador'])->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('employee_credit_accounts')->whereNotNull('customer_id')->delete();

        Schema::table('employee_credit_accounts', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropUnique(['customer_id']);
            $table->dropColumn('customer_id');
            $table->dropForeign(['employee_id']);
            $table->dropUnique(['employee_id']);
        });

        Schema::table('employee_credit_accounts', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable(false)->change();
            $table->unique('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
        });

        $permissionIds = DB::table('permissions')->whereIn('name', $this->permissions)->pluck('id');
        DB::table('role_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
