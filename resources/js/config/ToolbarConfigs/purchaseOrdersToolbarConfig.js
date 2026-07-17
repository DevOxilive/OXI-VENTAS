export function getPurchaseOrdersToolbarConfig({ filters = {}, total = 0, cycle = {}, mode = 'view', canConsolidate = false, canGenerate = false } = {}) {
    const headings = {
        view: ['Ordenes generales', 'Consulta los productos solicitados por todas las sucursales.'],
        tracking: ['Seguimiento de compras', 'Registra lo comprado al regresar de Central.'],
        history: ['Compras completadas', 'Consulta las compras generales que ya fueron cerradas.'],
    }
    const [title, subtitle] = headings[mode] || headings.view

    return {
        title,
        subtitle,
        backButton: false,
        search: filters.search ?? '',
        searchPlaceholder: 'Buscar por OCG, producto, codigo o sucursal',
        showSearch: true,
        compactFilters: true,
        filters: [],
        actions: mode === 'view' && canConsolidate ? [
            {
                id: 'consolidate',
                label: canGenerate ? 'Generar orden general' : 'Selecciona sucursales',
                icon: canGenerate ? 'merge_type' : 'hourglass_top',
                variant: 'primary',
                disabled: !canGenerate,
            },
        ] : [],
        tabs: [],
        recordsPerPage: Number(filters.per_page ?? 25),
        recordsPerPageOptions: [15, 25, 50],
        showRecordsPerPage: true,
        totalRecords: total,
        filteredRecords: total,
        showCounter: true,
    }
}
