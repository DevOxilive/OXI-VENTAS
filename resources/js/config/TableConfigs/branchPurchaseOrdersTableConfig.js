export function getBranchPurchaseOrdersTableConfig({ status = 'DRAFT' } = {}) {
    const completed = status === 'COMPLETED'

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
                key: completed ? 'actual_total' : 'estimated_total',
                label: completed ? 'Total pagado' : 'Total estimado',
                format: 'currency',
                mobileLabel: completed ? 'Total pagado' : 'Total estimado',
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
                label: 'Ver detalle',
                icon: 'visibility',
                variant: 'blue',
            },
        ],
        mobileCardHeaderField: 'folio',
        noDataMessage: {
            DRAFT: 'Todavia no hay borradores de esta sucursal.',
            GENERATED: 'Todavia no hay ordenes generadas de esta sucursal.',
            COMPLETED: 'Todavia no hay ordenes completadas de esta sucursal.',
        }[status] || 'No hay ordenes para mostrar.',
        rowKey: 'id',
        striped: true,
        hoverEffect: true,
    }
}
