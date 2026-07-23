export function getPurchaseOrdersTableConfig({ mode = 'view', viewPermission = null } = {}) {
    const completed = mode === 'history'
    const purchasing = mode === 'purchasing' || mode === 'tracking'

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
                id: 'view',
                label: 'Ver detalle',
                icon: 'visibility',
                variant: 'blue',
                permission: viewPermission,
            },
            {
                id: 'edit',
                label: 'Editar compra',
                icon: 'edit',
                variant: 'yellow',
                permission: 'inventory.purchase-orders.costs',
                hidden: () => !purchasing,
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
