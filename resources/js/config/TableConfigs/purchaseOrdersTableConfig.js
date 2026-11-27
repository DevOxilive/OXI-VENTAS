export function getPurchaseOrdersTableConfig({ mode = 'view', viewPermission = null } = {}) {
    const purchasing = mode === 'central'

    return {
        columns: [
            {
                key: 'folio',
                label: 'Compra central',
                width: '190px',
                mobileSecondary: false,
            },
            {
                key: 'responsible_name',
                label: 'Responsable de compra',
                mobileLabel: 'Responsable',
                mobileDisplay: true,
                fallback: 'Sin información',
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
                key: 'status_label',
                label: 'Estado',
                format: 'badge',
                formatOptions: {
                    statusMap: {
                        'En compra': 'blue',
                        Completada: 'green',
                    },
                },
                mobileLabel: 'Estado',
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
                permission: 'inventory.purchase-orders.general.update',
                hidden: (row) => !purchasing || row.status !== 'PURCHASING' || !row.can_edit,
            },
            {
                id: 'reopen',
                label: 'Regresar a edición',
                icon: 'undo',
                variant: 'secondary',
                permission: 'inventory.purchase-orders.general.create',
                hidden: (row) => !purchasing || row.status !== 'PURCHASING' || !row.can_edit,
            },
            {
                id: 'discard',
                label: 'Descartar compra',
                icon: 'delete_forever',
                variant: 'red',
                permission: 'inventory.purchase-orders.general.create',
                hidden: (row) => !purchasing || row.status !== 'PURCHASING' || !row.can_edit,
            },
        ],
        mobileCardHeaderField: 'folio',
        noDataMessage: 'No hay compras centrales para mostrar.',
        rowKey: 'id',
        hoverEffect: true,
    }
}
