export function getBranchPurchaseOrdersToolbarConfig({ filters = {}, total = 0, branchName = '', actions = [] } = {}) {
    return {
        icon: 'receipt_long',
        title: 'Solicitudes de compra',
        subtitle: branchName || 'Sucursal',
        backButton: false,
        showSearch: true,
        search: filters.search ?? '',
        searchPlaceholder: 'Buscar solicitud SC-…',
        compactFilters: true,
        filters: [
            {
                key: 'status',
                label: 'Estado',
                placeholder: 'Todos los estados',
                value: filters.status ?? 'ALL',
                options: [
                    { label: 'Todos los estados', value: 'ALL' },
                    { label: 'Pendiente', value: 'PENDING' },
                    { label: 'Aprobada', value: 'APPROVED' },
                    { label: 'Rechazada', value: 'REJECTED' },
                    { label: 'En revisión', value: 'REVIEW' },
                    { label: 'Completada', value: 'COMPLETED' },
                ],
            },
            {
                key: 'date_from',
                label: 'Desde',
                type: 'date',
                value: filters.date_from ?? '',
            },
            {
                key: 'date_to',
                label: 'Hasta',
                type: 'date',
                value: filters.date_to ?? '',
            },
        ],
        actions,
        tabs: [],
        activeTab: '',
        recordsPerPage: Number(filters.per_page ?? 25),
        showRecordsPerPage: true,
        totalRecords: total,
        filteredRecords: total,
        showCounter: false,
    }
}
