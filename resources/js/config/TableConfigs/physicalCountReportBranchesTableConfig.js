export const physicalCountReportBranchesTableConfig = {
    mobileCardHeaderField: 'branch_name',
    noDataMessage: 'Sin resumen por sucursal.',
    columns: [
        { key: 'branch_name', label: 'Sucursal' },
        { key: 'audits', label: 'Auditorias', format: 'number' },
        { key: 'products', label: 'Productos', format: 'number' },
        { key: 'counted_products', label: 'Contados', format: 'number' },
        { key: 'pending_products', label: 'No encontrados', format: 'number' },
        { key: 'matched_products', label: 'Macheados', format: 'number' },
        { key: 'missing_products', label: 'Faltantes', format: 'number' },
        { key: 'surplus_products', label: 'Sobrantes', format: 'number' },
        { key: 'advanceLabel', label: 'Avance' },
        { key: 'difference_units', label: 'Dif. neta', format: 'number' },
        { key: 'absolute_difference_units', label: 'Dif. absoluta', format: 'number' },
    ],
    actions: [],
}
