export function getBranchPurchaseOrdersToolbarConfig({ filters = {}, total = 0, branchName = '' } = {}) {
    return {
        icon: 'receipt_long',
        title: 'Órdenes de compra',
        subtitle: `Consulta las órdenes pendientes, por revisar y completadas de ${branchName || 'la sucursal'}.`,
        backButton: false,
        showSearch: false,
        compactFilters: true,
        filters: [],
        actions: [],
        tabs: [
            { key: 'GENERATED', label: 'Pendientes', icon: 'schedule' },
            { key: 'REVIEW', label: 'Por revisar', icon: 'fact_check' },
            { key: 'COMPLETED', label: 'Completadas', icon: 'task_alt' },
        ],
        activeTab: filters.status ?? 'GENERATED',
        recordsPerPage: Number(filters.per_page ?? 25),
        showRecordsPerPage: true,
        totalRecords: total,
        filteredRecords: total,
        showCounter: true,
    }
}
