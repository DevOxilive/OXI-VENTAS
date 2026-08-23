<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('employee_credit_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('employees')->cascadeOnDelete();
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->decimal('credit_balance', 12, 2)->default(0);
            $table->date('estimated_payment_date')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_credit_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_credit_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->unique()->constrained('sales')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('outstanding_amount', 12, 2);
            $table->date('estimated_payment_date')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();
            $table->index(['employee_credit_account_id', 'status']);
        });

        Schema::create('employee_credit_payments', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('employee_credit_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('payment_method_id')->constrained('payment_methods')->restrictOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cash_box_number', 10)->default('1');
            $table->decimal('amount', 12, 2);
            $table->decimal('cash_received', 12, 2)->default(0);
            $table->decimal('change_due', 12, 2)->default(0);
            $table->timestamp('paid_at');
            $table->timestamps();
            $table->index(['branch_id', 'cash_box_number', 'paid_at']);
        });

        Schema::create('employee_credit_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_credit_payment_id');
            $table->unsignedBigInteger('employee_credit_charge_id');
            $table->foreign('employee_credit_payment_id', 'ecpa_payment_fk')->references('id')->on('employee_credit_payments')->cascadeOnDelete();
            $table->foreign('employee_credit_charge_id', 'ecpa_charge_fk')->references('id')->on('employee_credit_charges')->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['employee_credit_payment_id', 'employee_credit_charge_id'], 'employee_credit_payment_charge_unique');
        });

        DB::table('payment_methods')->updateOrInsert(
            ['name' => 'Crédito empleado'],
            ['active' => true, 'created_at' => now(), 'updated_at' => now()]
        );

        foreach (['sales.employee-credit.view', 'sales.employee-credit.create', 'sales.employee-credit.collect'] as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('name', ['sales.employee-credit.view', 'sales.employee-credit.create', 'sales.employee-credit.collect'])->pluck('id');
        $roleIds = DB::table('roles')->whereIn('name', ['Administrador', 'Super Administrador'])->pluck('id');
        foreach ($roleIds as $roleId) foreach ($permissionIds as $permissionId) {
            DB::table('role_permission')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId], []);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_credit_payment_allocations');
        Schema::dropIfExists('employee_credit_payments');
        Schema::dropIfExists('employee_credit_charges');
        Schema::dropIfExists('employee_credit_accounts');
    }
};
