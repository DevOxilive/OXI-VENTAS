export const salesProductsReportTableConfig = {
    columns: [
        {
            key: 'product',
            label: 'Producto',
            format: 'text',
            mobileSecondary: true,
            width: '260px',
        },
        {
            key: 'code',
            label: 'Código',
            format: 'text',
            mobileDisplay: true,
            width: '160px',
        },
        {
            key: 'branch',
            label: 'Sucursal',
            format: 'text',
            mobileDisplay: true,
        },
        {
            key: 'current_stock_display',
            label: 'Stock actual',
            format: 'text',
            mobileDisplay: true,
        },
        {
            key: 'sold_quantity_display',
            label: 'Vendido en periodo',
            format: 'text',
            mobileDisplay: true,
        },
        {
            key: 'monthly_average_display',
            label: 'Promedio mensual',
            format: 'text',
            mobileDisplay: true,
        },
        {
            key: 'last_sale_display',
            label: 'Última venta',
            format: 'text',
            mobileDisplay: true,
        },
    ],
    actions: [],
    mobileCardHeaderField: 'product',
    noDataMessage: 'No hay productos vendidos que coincidan con los filtros.',
    rowKey: 'id',
    hoverEffect: true,
}
