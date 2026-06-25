export const physicalCountReportDetailTableConfig = {
    mobileCardHeaderField: 'product_name',
    noDataMessage: 'No hay resultados para los filtros seleccionados.',
    columns: [
        { key: 'product_name', label: 'Producto' },
        { key: 'scanned_code', label: 'Codigo' },
        { key: 'audit_name', label: 'Auditoria' },
        { key: 'status_label', label: 'Resultado' },
        { key: 'system_stock', label: 'Stock sistema', format: 'number' },
        { key: 'counted_stock', label: 'Conteo fisico', format: 'number' },
        { key: 'differenceLabel', label: 'Diferencia' },
        { key: 'participantsLabel', label: 'Usuarios' },
        { key: 'audit_date', label: 'Fecha', format: 'date' },
    ],
    actions: [],
}
