export function getPurchaseOrdersToolbarConfig({ filters = {}, total = 0, tabs = [] } = {}) {
    const showingCentralPurchases = filters.status === 'CENTRAL'

    return {
        icon: showingCentralPurchases ? 'local_shipping' : 'shopping_cart',
        title: showingCentralPurchases ? 'Compra Central' : 'Solicitudes de compra',
        subtitle: '',
        backButton: true,
        backLabel: 'Inventario',
        search: filters.search ?? '',
        searchPlaceholder: showingCentralPurchases
            ? 'Buscar compra central, producto o sucursal'
            : 'Buscar solicitud, sucursal o solicitante',
        showSearch: true,
        compactFilters: true,
        filters: [showingCentralPurchases
            ? {
                key: 'central_status',
                label: 'Estado',
                placeholder: 'Todos los estados',
                value: filters.central_status ?? 'ALL',
                options: [
                    { label: 'Todos los estados', value: 'ALL' },
                    { label: 'En compra', value: 'PURCHASING' },
                    { label: 'Completada', value: 'COMPLETED' },
                ],
            }
            : {
                key: 'source_status',
                label: 'Estado',
                placeholder: 'Todos los estados',
                value: filters.source_status ?? 'ALL',
                options: [
                    { label: 'Todos los estados', value: 'ALL' },
                    { label: 'Pendiente', value: 'PENDING' },
                    { label: 'Aprobada', value: 'APPROVED' },
                    { label: 'Rechazada', value: 'REJECTED' },
                ],
            }],
        actions: [],
        tabs,
        activeTab: filters.status ?? 'GENERATE',
        recordsPerPage: Number(filters.per_page ?? 25),
        showRecordsPerPage: true,
        totalRecords: total,
        filteredRecords: total,
        showCounter: false,
    }
}
