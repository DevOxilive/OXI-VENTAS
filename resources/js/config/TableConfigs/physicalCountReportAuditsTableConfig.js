export const physicalCountReportAuditsTableConfig = {
    mobileCardHeaderField: 'audit_name',
    noDataMessage: 'Sin resumen por auditoria.',
    columns: [
        { key: 'branch_name', label: 'Sucursal' },
        { key: 'audit_name', label: 'Auditoria' },
        { key: 'folio', label: 'Folio' },
        { key: 'audit_date', label: 'Fecha', format: 'date' },
        { key: 'products', label: 'Productos', format: 'number' },
        { key: 'counted_products', label: 'Contados', format: 'number' },
        { key: 'pending_products', label: 'No encontrados', format: 'number' },
        { key: 'matched_products', label: 'Macheados', format: 'number' },
        { key: 'missing_products', label: 'Faltantes', format: 'number' },
        { key: 'surplus_products', label: 'Sobrantes', format: 'number' },
        { key: 'advanceLabel', label: 'Avance' },
        { key: 'absolute_difference_units', label: 'Dif. absoluta', format: 'number' },
    ],
    actions: [],
}
