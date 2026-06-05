<?php

namespace App\Exports;

use App\Models\PhysicalCount;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PhysicalCountExport implements FromArray, WithHeadings
{
    public function __construct(
        protected PhysicalCount $physicalCount
    ) {}

    public function headings(): array
    {
        return [
            'Producto',
            'Stock sistema',
            'Cantidad contada',
            'Cantidad dañada',
            'Cantidad caducada',
            'Diferencia',
            'Estado',
        ];
    }

    public function array(): array
    {
        return $this->physicalCount->entries()
            ->select(
                'branch_product_id',
                DB::raw('SUM(counted_quantity) as counted_stock'),
                DB::raw('SUM(damaged_quantity) as damaged_stock'),
                DB::raw('SUM(expired_quantity) as expired_stock')
            )
            ->with('branchProduct')
            ->groupBy('branch_product_id')
            ->get()
            ->map(function ($item) {
                $systemStock = (float) ($item->branchProduct->stock ?? 0);
                $countedStock = (float) $item->counted_stock;
                $difference = $countedStock - $systemStock;

                $status = $difference < 0
                    ? 'Faltante'
                    : ($difference > 0 ? 'Sobrante' : 'Correcto');

                return [
                    $item->branchProduct->name ?? 'Sin producto',
                    $systemStock,
                    $countedStock,
                    (float) $item->damaged_stock,
                    (float) $item->expired_stock,
                    $difference,
                    $status,
                ];
            })
            ->toArray();
    }
}