export function getPurchaseOrdersTableConfig({ mode = 'view' } = {}) {
    const completed = mode === 'history'

    return {
        columns: [
            {
                key: 'folio',
                label: 'Orden general',
                width: '190px',
                mobileSecondary: false,
            },
            {
                key: 'items_count',
                label: 'Productos',
                format: 'number',
                mobileLabel: 'Productos',
                mobileDisplay: true,
            },
            {
                key: 'branches_count',
                label: 'Sucursales',
                format: 'number',
                mobileLabel: 'Sucursales',
                mobileDisplay: true,
            },
            ...(completed ? [{
                key: 'actual_total',
                label: 'Total pagado',
                format: 'currency',
                mobileLabel: 'Total pagado',
                mobileDisplay: true,
            }] : []),
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
                label: mode === 'tracking' ? 'Capturar compra' : 'Ver detalle',
                icon: mode === 'tracking' ? 'edit_note' : 'visibility',
                variant: 'blue',
            },
        ],
        mobileCardHeaderField: 'folio',
        noDataMessage: completed
            ? 'Todavia no hay compras generales completadas.'
            : 'Todavia no hay ordenes generales de compra.',
        rowKey: 'id',
        striped: true,
        hoverEffect: true,
    }
}
