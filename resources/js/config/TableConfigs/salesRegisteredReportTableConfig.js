export const salesRegisteredReportTableConfig = {
    columns: [
        {
            key: 'folio',
            label: 'Folio',
            format: 'text',
            mobileSecondary: true,
            width: '150px',
        },
        {
            key: 'date_display',
            label: 'Fecha',
            format: 'text',
            mobileDisplay: true,
        },
        {
            key: 'seller',
            label: 'Vendedor',
            format: 'text',
            mobileDisplay: true,
        },
        {
            key: 'branch',
            label: 'Sucursal',
            format: 'text',
            mobileDisplay: true,
        },
        {
            key: 'total_products_sold_display',
            label: 'Total productos vendidos',
            format: 'text',
            mobileDisplay: true,
        },
        {
            key: 'total',
            label: 'Total pagado',
            format: 'currency',
            mobileDisplay: true,
        },
    ],
    actions: [],
    mobileCardHeaderField: 'folio',
    noDataMessage: 'No hay ventas registradas que coincidan con los filtros.',
    rowKey: 'id',
    striped: true,
    hoverEffect: true,
}
