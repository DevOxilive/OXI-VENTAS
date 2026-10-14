<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $cleaningDepartmentId = DB::table('product_departments')
                ->where('name', 'Limpieza')
                ->value('id');
            $convenienceDepartmentId = DB::table('product_departments')
                ->where('name', 'Conveniencia')
                ->value('id');

            if (! $cleaningDepartmentId || ! $convenienceDepartmentId) {
                throw new RuntimeException('No se encontraron los departamentos Limpieza y Conveniencia requeridos para mover Desechables.');
            }

            $disposablesCategory = DB::table('categories')
                ->where('product_department_id', $cleaningDepartmentId)
                ->where('name', 'Desechables')
                ->first(['id']);

            if (! $disposablesCategory) {
                return;
            }

            $alreadyInConvenience = DB::table('categories')
                ->where('product_department_id', $convenienceDepartmentId)
                ->where('name', 'Desechables')
                ->exists();

            if ($alreadyInConvenience) {
                throw new RuntimeException('Ya existe una categoría Desechables en Conveniencia; se cancela para no duplicar categorías.');
            }

            DB::table('categories')
                ->where('id', $disposablesCategory->id)
                ->update([
                    'product_department_id' => $convenienceDepartmentId,
                    'sort_order' => 1108,
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $cleaningDepartmentId = DB::table('product_departments')
                ->where('name', 'Limpieza')
                ->value('id');
            $convenienceDepartmentId = DB::table('product_departments')
                ->where('name', 'Conveniencia')
                ->value('id');

            if (! $cleaningDepartmentId || ! $convenienceDepartmentId) {
                return;
            }

            DB::table('categories')
                ->where('product_department_id', $convenienceDepartmentId)
                ->where('name', 'Desechables')
                ->update([
                    'product_department_id' => $cleaningDepartmentId,
                    'sort_order' => 608,
                    'updated_at' => now(),
                ]);
        });
    }
};
