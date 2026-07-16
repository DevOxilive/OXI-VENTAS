export function getPurchaseOrdersToolbarConfig({ filters = {}, total = 0 } = {}) {
    return {
        title: 'Órdenes de compra',
        subtitle: 'Consulta órdenes generadas y captura los productos que realmente se compraron.',
        backButton: true,
        backLabel: 'Centro de reportes',
        search: filters.search ?? '',
        searchPlaceholder: 'Buscar por OC, producto o código',
        showSearch: true,
        compactFilters: true,
        filters: [
            {
                key: 'status',
                label: '',
                placeholder: 'Todos los estados',
                value: filters.status ?? '',
                options: [
                    { label: 'Generadas', value: 'GENERATED' },
                    { label: 'Completadas', value: 'COMPLETED' },
                    { label: 'Canceladas', value: 'CANCELLED' },
                ],
            },
        ],
        actions: [
            {
                id: 'new-list',
                label: 'Nueva lista',
                icon: 'add_shopping_cart',
                variant: 'primary',
            },
        ],
        tabs: [],
        recordsPerPage: Number(filters.per_page ?? 25),
        recordsPerPageOptions: [15, 25, 50],
        showRecordsPerPage: true,
        totalRecords: total,
        filteredRecords: total,
        showCounter: true,
    }
}
