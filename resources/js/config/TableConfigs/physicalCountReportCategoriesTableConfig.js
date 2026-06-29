export const physicalCountReportCategoriesTableConfig = {
    mobileCardHeaderField: 'category_name',
    noDataMessage: 'Sin resumen por categoria.',
    columns: [
        { key: 'category_name', label: 'Categoria' },
        { key: 'products', label: 'Productos', format: 'number' },
        { key: 'counted_products', label: 'Contados', format: 'number' },
        { key: 'pending_products', label: 'Pendientes', format: 'number' },
        { key: 'missing_products', label: 'Faltantes', format: 'number' },
        { key: 'surplus_products', label: 'Sobrantes', format: 'number' },
        { key: 'matched_products', label: 'Correctos', format: 'number' },
    ],
    actions: [],
}
