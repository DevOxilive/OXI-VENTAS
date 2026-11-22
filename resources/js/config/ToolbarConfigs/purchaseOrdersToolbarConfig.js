export function getPurchaseOrdersToolbarConfig({ filters = {}, total = 0, mode = 'view', tabs = null, actions = [] } = {}) {
    const headings = {
        view: ['Solicitudes de compra', 'Revisa solicitudes de sucursal y reúne las que comprarás.'],
        tracking: ['Compras centrales', 'Registra los productos comprados.'],
        history: ['Compras cerradas', 'Consulta las compras que ya fueron terminadas.'],
    }
    const [title, subtitle] = headings[mode] || headings.view

    return {
        icon: mode === 'tracking' ? 'local_shipping' : mode === 'history' ? 'history' : 'shopping_cart',
        title,
        subtitle,
        backButton: true,
        backLabel: 'Inventario',
        search: filters.search ?? '',
        searchPlaceholder: 'Buscar compra, producto, código o sucursal',
        showSearch: mode !== 'view' || filters.status !== 'GENERATE',
        compactFilters: true,
        filters: [],
        actions,
        tabs: mode === 'view' ? (tabs ?? [
            { key: 'GENERATE', label: 'Solicitudes', icon: 'playlist_add' },
            { key: 'PURCHASING', label: 'Compras centrales', icon: 'local_shipping' },
            { key: 'COMPLETED', label: 'Cerradas', icon: 'task_alt' },
        ]) : [],
        activeTab: filters.status ?? 'GENERATE',
        recordsPerPage: Number(filters.per_page ?? 25),
        showRecordsPerPage: mode !== 'view' || filters.status !== 'GENERATE',
        totalRecords: total,
        filteredRecords: total,
        showCounter: true,
    }
}
