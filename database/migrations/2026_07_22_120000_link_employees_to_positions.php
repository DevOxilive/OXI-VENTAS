<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('employees', 'position_id')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->unsignedBigInteger('position_id')->nullable()->after('photo');
            });
        }

        $hasLegacyPosition = Schema::hasColumn('employees', 'position');
        $hasLegacyDepartment = Schema::hasColumn('employees', 'department');

        if ($hasLegacyPosition && $hasLegacyDepartment) {
            DB::table('employees')
                ->select(['id', 'position', 'department'])
                ->orderBy('id')
                ->chunkById(100, function ($employees) {
                    foreach ($employees as $employee) {
                        $departmentName = trim((string) $employee->department);
                        $positionName = trim((string) $employee->position);

                        if ($departmentName === '' || $positionName === '') {
                            continue;
                        }

                        $departmentId = DB::table('departments')
                            ->where('name', $departmentName)
                            ->value('id');

                        if (!$departmentId) {
                            $departmentId = DB::table('departments')->insertGetId([
                                'name' => $departmentName,
                                'active' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        $positionId = DB::table('positions')
                            ->where('department_id', $departmentId)
                            ->where('name', $positionName)
                            ->value('id');

                        if (!$positionId) {
                            $positionId = DB::table('positions')->insertGetId([
                                'name' => $positionName,
                                'description' => null,
                                'department_id' => $departmentId,
                                'active' => true,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }

                        DB::table('employees')
                            ->where('id', $employee->id)
                            ->update(['position_id' => $positionId]);
                    }
                });
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('position_id')
                ->references('id')
                ->on('positions')
                ->restrictOnDelete();
        });

        $legacyColumns = collect(['position', 'department'])
            ->filter(fn (string $column) => Schema::hasColumn('employees', $column))
            ->all();

        if ($legacyColumns !== []) {
            Schema::table('employees', function (Blueprint $table) use ($legacyColumns) {
                $table->dropColumn($legacyColumns);
            });
        }

    }

    public function down(): void
    {
        if (!Schema::hasColumn('employees', 'position')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('position')->nullable();
                $table->string('department')->nullable();
            });
        }

        DB::table('employees')
            ->leftJoin('positions', 'positions.id', '=', 'employees.position_id')
            ->leftJoin('departments', 'departments.id', '=', 'positions.department_id')
            ->update([
                'employees.position' => DB::raw('positions.name'),
                'employees.department' => DB::raw('departments.name'),
            ]);

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['position_id']);
            $table->dropColumn('position_id');
        });
    }
};
