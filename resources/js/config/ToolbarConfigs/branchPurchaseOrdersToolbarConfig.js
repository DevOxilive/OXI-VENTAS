export function getBranchPurchaseOrdersToolbarConfig({ filters = {}, total = 0, branchName = '' } = {}) {
    return {
        title: 'Reportes por sucursal',
        subtitle: `Consulta borradores, ordenes generadas y compras completadas de ${branchName || 'la sucursal'}.`,
        backButton: true,
        backLabel: 'Centro de reportes',
        search: filters.search ?? '',
        searchPlaceholder: 'Buscar por orden, producto, codigo o notas',
        showSearch: true,
        compactFilters: true,
        filters: [],
        actions: [],
        tabs: [
            { key: 'DRAFT', label: 'Borradores', icon: 'edit_note' },
            { key: 'GENERATED', label: 'Generadas', icon: 'receipt_long' },
            { key: 'COMPLETED', label: 'Completadas', icon: 'task_alt' },
        ],
        activeTab: filters.status ?? 'DRAFT',
        recordsPerPage: Number(filters.per_page ?? 25),
        recordsPerPageOptions: [15, 25, 50],
        showRecordsPerPage: true,
        totalRecords: total,
        filteredRecords: total,
        showCounter: true,
    }
}
