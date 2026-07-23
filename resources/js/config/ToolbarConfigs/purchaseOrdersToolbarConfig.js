export function getPurchaseOrdersToolbarConfig({ filters = {}, total = 0, mode = 'view', tabs = null } = {}) {
    const headings = {
        view: ['Órdenes de compra generales', 'Selecciona Órdenes de compra de sucursal, prepara la Orden de compra general y consulta su seguimiento.'],
        tracking: ['Seguimiento de compras', 'Registra lo comprado al regresar de Central.'],
        history: ['Compras completadas', 'Consulta las compras generales que ya fueron cerradas.'],
    }
    const [title, subtitle] = headings[mode] || headings.view

    return {
        title,
        subtitle,
        backButton: true,
        backLabel: 'Centro de reportes',
        search: filters.search ?? '',
        searchPlaceholder: 'Buscar por Orden de compra general, producto, código o sucursal',
        showSearch: mode !== 'view' || filters.status !== 'GENERATE',
        compactFilters: true,
        filters: [],
        actions: [],
        tabs: mode === 'view' ? (tabs ?? [
            { key: 'GENERATE', label: 'Generar', icon: 'playlist_add' },
            { key: 'PURCHASING', label: 'En compra', icon: 'local_shipping' },
            { key: 'COMPLETED', label: 'Completadas', icon: 'task_alt' },
        ]) : [],
        activeTab: filters.status ?? 'GENERATE',
        recordsPerPage: Number(filters.per_page ?? 25),
        recordsPerPageOptions: [15, 25, 50],
        showRecordsPerPage: mode !== 'view' || filters.status !== 'GENERATE',
        totalRecords: total,
        filteredRecords: total,
        showCounter: true,
    }
}
