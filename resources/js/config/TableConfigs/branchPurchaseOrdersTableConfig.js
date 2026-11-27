export function getBranchPurchaseOrdersTableConfig({ status = 'GENERATED' } = {}) {
    return {
        columns: [
            {
                key: 'folio',
                label: 'Solicitud',
                width: '180px',
                mobileSecondary: false,
            },
            {
                key: 'workflow_status_label',
                label: 'Estado',
                format: 'badge',
                formatOptions: {
                    colorKey: 'workflow_status_tone',
                    statusMap: {
                        Pendiente: 'amber',
                        Modificada: 'red',
                        Aprobada: 'green',
                        Rechazada: 'red',
                        'En revisión': 'amber',
                        Completada: 'green',
                        Cancelada: 'red',
                    },
                },
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
                id: 'view',
                label: 'Visualizar',
                icon: 'visibility',
                variant: 'blue',
                permission: 'sales.purchase-orders.view',
            },
            {
                id: 'review',
                label: 'Revisar compra',
                icon: 'checklist',
                variant: 'yellow',
                permission: 'sales.purchase-orders.receive',
                hidden: (row) => row.status !== 'REVIEW',
            },
        ],
        mobileCardHeaderField: 'folio',
        noDataMessage: {
            PENDING: 'No hay solicitudes pendientes.',
            APPROVED: 'No hay solicitudes aprobadas.',
            REJECTED: 'No hay solicitudes rechazadas.',
            REVIEW: 'No hay solicitudes en revisión.',
            COMPLETED: 'No hay solicitudes completadas.',
        }[status] || 'No hay solicitudes para mostrar.',
        rowKey: 'id',
        hoverEffect: true,
    }
}
