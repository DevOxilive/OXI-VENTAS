export function getPurchaseOrdersToolbarConfig({ filters = {}, total = 0, cycle = {}, mode = 'view', canConsolidate = false, canGenerate = false } = {}) {
    const headings = {
        view: ['Reportes generales', 'Consulta ordenes generales en compra y compras generales completadas.'],
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
        searchPlaceholder: 'Buscar por OCG, producto, codigo o sucursal',
        showSearch: true,
        compactFilters: true,
        filters: [],
        actions: mode === 'view' && canConsolidate && filters.status !== 'COMPLETED' ? [
            {
                id: 'consolidate',
                label: canGenerate ? 'Generar orden general' : 'Selecciona sucursales',
                icon: canGenerate ? 'merge_type' : 'hourglass_top',
                variant: 'primary',
                disabled: !canGenerate,
            },
        ] : [],
        tabs: mode === 'view' ? [
            { key: 'PURCHASING', label: 'En compra', icon: 'local_shipping' },
            { key: 'COMPLETED', label: 'Completadas', icon: 'task_alt' },
        ] : [],
        activeTab: filters.status ?? 'PURCHASING',
        recordsPerPage: Number(filters.per_page ?? 25),
        recordsPerPageOptions: [15, 25, 50],
        showRecordsPerPage: true,
        totalRecords: total,
        filteredRecords: total,
        showCounter: true,
    }
}
