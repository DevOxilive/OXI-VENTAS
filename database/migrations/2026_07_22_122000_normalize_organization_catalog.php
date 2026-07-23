<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacyDepartments = [
            'Management' => 'Administración',
            'PointOfSale' => 'Ventas',
            'Warehouse' => 'Inventario',
            'Distribution' => 'Logística',
        ];
        $legacyPositions = ['Manager', 'Cashier', 'InventoryManager'];

        foreach ($legacyDepartments as $legacyName => $currentName) {
            $legacyDepartmentId = DB::table('departments')->where('name', $legacyName)->value('id');
            $currentDepartmentId = DB::table('departments')->where('name', $currentName)->value('id');

            if (!$legacyDepartmentId || !$currentDepartmentId) {
                continue;
            }

            $positions = DB::table('positions')
                ->where('department_id', $legacyDepartmentId)
                ->get(['id', 'name']);

            foreach ($positions as $position) {
                $hasEmployees = DB::table('employees')
                    ->where('position_id', $position->id)
                    ->exists();

                if (!$hasEmployees && in_array($position->name, $legacyPositions, true)) {
                    DB::table('positions')->where('id', $position->id)->delete();
                    continue;
                }

                $currentPositionId = DB::table('positions')
                    ->where('department_id', $currentDepartmentId)
                    ->where('name', $position->name)
                    ->value('id');

                if ($currentPositionId) {
                    DB::table('employees')
                        ->where('position_id', $position->id)
                        ->update(['position_id' => $currentPositionId]);
                    DB::table('positions')->where('id', $position->id)->delete();
                    continue;
                }

                DB::table('positions')
                    ->where('id', $position->id)
                    ->update([
                        'department_id' => $currentDepartmentId,
                        'updated_at' => now(),
                    ]);
            }

            DB::table('departments')->where('id', $legacyDepartmentId)->delete();
        }

        if (!Schema::hasIndex('departments', 'departments_name_unique')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->unique('name', 'departments_name_unique');
            });
        }

        if (!Schema::hasIndex('positions', 'positions_department_name_unique')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->unique(['department_id', 'name'], 'positions_department_name_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('positions', 'positions_department_name_unique')) {
            Schema::table('positions', function (Blueprint $table) {
                $table->dropUnique('positions_department_name_unique');
            });
        }

        if (Schema::hasIndex('departments', 'departments_name_unique')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->dropUnique('departments_name_unique');
            });
        }

        // Los nombres heredados no se recrean al revertir para no duplicar catálogos.
    }
};
