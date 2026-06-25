export const physicalCountReportDifferencesTableConfig = {
    mobileCardHeaderField: 'product_name',
    noDataMessage: 'Sin diferencias relevantes.',
    columns: [
        { key: 'product_name', label: 'Producto' },
        { key: 'category_name', label: 'Categoria' },
        { key: 'scanned_code', label: 'Codigo' },
        { key: 'system_stock', label: 'Sistema', format: 'number' },
        { key: 'counted_stock', label: 'Conteo', format: 'number' },
        { key: 'differenceLabel', label: 'Diferencia' },
        { key: 'status_label', label: 'Resultado' },
    ],
    actions: [],
}
