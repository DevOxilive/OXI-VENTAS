export const physicalCountReportDetailTableConfig = {
    mobileCardHeaderField: 'product_name',
    noDataMessage: 'No hay resultados para los filtros seleccionados.',
    columns: [
        { key: 'product_name', label: 'Producto' },
        { key: 'scanned_code', label: 'Código' },
        { key: 'branch_name', label: 'Sucursal' },
        { key: 'audit_name', label: 'Auditoría' },
        { key: 'status_label', label: 'Resultado' },
        { key: 'system_stock_display', label: 'Stock sistema' },
        { key: 'counted_stock_display', label: 'Conteo físico' },
        { key: 'difference_label', label: 'Diferencia' },
        { key: 'participantsLabel', label: 'Usuarios' },
        { key: 'audit_date', label: 'Fecha', format: 'date' },
    ],
    actions: [],
}
