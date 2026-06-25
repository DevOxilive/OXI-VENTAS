export const physicalCountReportUsersTableConfig = {
    mobileCardHeaderField: 'user_name',
    noDataMessage: 'Sin resumen por usuario.',
    columns: [
        { key: 'user_name', label: 'Usuario' },
        { key: 'records', label: 'Registros', format: 'number' },
        { key: 'products', label: 'Productos', format: 'number' },
        { key: 'counted_stock', label: 'Contado', format: 'number' },
        { key: 'damaged_stock', label: 'Danado', format: 'number' },
        { key: 'expired_stock', label: 'Caducado', format: 'number' },
    ],
    actions: [],
}
