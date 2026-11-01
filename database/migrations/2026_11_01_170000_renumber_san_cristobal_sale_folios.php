<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los veinte tickets existentes al momento de la corrección. No se incluyen
     * ventas posteriores: si aparecen, la migración se detiene antes de escribir.
     */
    private const SAN_CRISTOBAL_SALE_IDS = [
        298,
        302,
        303,
        304,
        305,
        306,
        307,
        308,
        309,
        310,
        311,
        312,
        313,
        314,
        315,
        316,
        317,
        318,
        319,
        320,
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $branches = DB::table('branches')
                ->whereIn('name', ['San Cristobal', 'San Cristóbal'])
                ->lockForUpdate()
                ->get(['id']);

            if ($branches->isEmpty()) {
                // Instalación nueva: aún no hay historial de San Cristóbal que corregir.
                return;
            }

            if ($branches->count() > 1) {
                throw new \RuntimeException('Se requiere una sola sucursal San Cristóbal para renumerar sus tickets.');
            }

            $branchId = (int) $branches->first()->id;
            $sales = DB::table('sales')
                ->where('branch_id', $branchId)
                ->orderBy('date')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'folio']);

            $actualIds = $sales->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($actualIds !== self::SAN_CRISTOBAL_SALE_IDS) {
                throw new \RuntimeException('Las ventas de San Cristóbal cambiaron desde la validación. No se renumeró ningún ticket.');
            }

            foreach ($sales as $position => $sale) {
                DB::table('sales')->where('id', $sale->id)->update([
                    'folio' => 'V-'.str_pad((string) ($position + 1), 6, '0', STR_PAD_LEFT),
                ]);
            }

            DB::table('branches')->where('id', $branchId)->update(['next_sale_folio' => count(self::SAN_CRISTOBAL_SALE_IDS) + 1]);
        });
    }

    public function down(): void
    {
        // No es seguro recuperar los folios globales anteriores desde la numeración local.
    }
};
