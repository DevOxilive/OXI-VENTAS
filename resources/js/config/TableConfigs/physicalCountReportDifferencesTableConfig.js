export const physicalCountReportDifferencesTableConfig = {
    mobileCardHeaderField: 'product_name',
    noDataMessage: 'Sin diferencias relevantes.',
    columns: [
        { key: 'product_name', label: 'Producto' },
        { key: 'category_name', label: 'Categoría' },
        { key: 'scanned_code', label: 'Código' },
        { key: 'system_stock_display', label: 'Sistema' },
        { key: 'counted_stock_display', label: 'Conteo' },
        { key: 'difference_label', label: 'Diferencia' },
        { key: 'status_label', label: 'Resultado' },
    ],
    actions: [],
}
