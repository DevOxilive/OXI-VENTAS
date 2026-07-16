export function getPurchaseOrdersTableConfig() {
    return {
        columns: [
            {
                key: 'folio',
                label: 'Orden de compra',
                width: '190px',
                mobileSecondary: false,
            },
            {
                key: 'status_label',
                label: 'Estado',
                format: 'badge',
                formatOptions: {
                    statusMap: {
                        Generada: 'blue',
                        Completada: 'green',
                        Cancelada: 'red',
                    },
                },
                mobileBadge: true,
            },
            {
                key: 'items_count',
                label: 'Productos',
                format: 'number',
                mobileLabel: 'Productos',
                mobileDisplay: true,
            },
            {
                key: 'estimated_total',
                label: 'Estimado',
                format: 'currency',
                mobileLabel: 'Estimado',
                mobileDisplay: true,
            },
            {
                key: 'actual_total',
                label: 'Total real',
                format: 'currency',
                mobileLabel: 'Total real',
                mobileDisplay: true,
            },
            {
                key: 'display_date',
                label: 'Fecha',
                format: 'date',
                mobileLabel: 'Fecha',
                mobileDisplay: true,
            },
        ],
        actions: [
            {
                id: 'open',
                label: 'Abrir',
                icon: 'visibility',
                variant: 'blue',
            },
        ],
        mobileCardHeaderField: 'folio',
        noDataMessage: 'No hay órdenes de compra que coincidan con los filtros.',
        rowKey: 'id',
        striped: true,
        hoverEffect: true,
    }
}
