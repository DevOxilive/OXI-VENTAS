<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PhysicalCountReportExport implements FromCollection, WithHeadings
{
    public function __construct(
        protected Collection $rows
    ) {}

    public function headings(): array
    {
        return [
            'Auditoria',
            'Folio',
            'Fecha',
            'Tipo de resultado',
            'Estado',
            'Producto',
            'Categoria',
            'Subcategoria',
            'Codigo',
            'Stock sistema',
            'Conteo fisico',
            'Danado',
            'Caducado',
            'Diferencia',
            'Usuarios',
        ];
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($row) {
            return [
                $row['audit_name'] ?? 'Sin auditoria',
                $row['folio'] ?? 'Sin folio',
                $row['audit_date'] ?? '',
                $row['row_type_label'] ?? '',
                $row['status_label'] ?? '',
                $row['product_name'] ?? '',
                $row['category_name'] ?? '',
                $row['subcategory_name'] ?? '',
                $row['scanned_code'] ?? '',
                $row['system_stock'] ?? 0,
                $row['counted_stock'] ?? 0,
                $row['damaged_stock'] ?? 0,
                $row['expired_stock'] ?? 0,
                $row['difference'] ?? '',
                implode(', ', $row['participants'] ?? []),
            ];
        });
    }
}
