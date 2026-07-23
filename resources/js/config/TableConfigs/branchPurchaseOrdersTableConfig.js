export function getBranchPurchaseOrdersTableConfig({ status = 'GENERATED' } = {}) {
    return {
        columns: [
            {
                key: 'folio',
                label: 'Orden',
                width: '180px',
                mobileSecondary: false,
            },
            {
                key: 'status_label',
                label: 'Estado',
                subKey: 'inventory_edit_label',
                subKeyBadge: true,
                mobileLabel: 'Estado',
                mobileDisplay: true,
            },
            {
                key: 'items_count',
                label: 'Productos',
                format: 'number',
                mobileLabel: 'Productos',
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
                id: 'edit',
                label: 'Editar orden',
                icon: 'edit',
                variant: 'yellow',
                permission: 'inventory.purchase-reports.update',
                hidden: (row) => !['GENERATED', 'REVIEW'].includes(row.status),
            },
            {
                id: 'view',
                label: 'Ver detalle',
                icon: 'visibility',
                variant: 'blue',
                permission: 'inventory.purchase-reports.view',
            },
        ],
        mobileCardHeaderField: 'folio',
        noDataMessage: {
            GENERATED: 'Todavía no hay órdenes pendientes de esta sucursal.',
            REVIEW: 'Todavía no hay órdenes por revisar de esta sucursal.',
            COMPLETED: 'Todavía no hay órdenes completadas de esta sucursal.',
        }[status] || 'No hay órdenes para mostrar.',
        rowKey: 'id',
        striped: true,
        hoverEffect: true,
    }
}
