export function getGeneralPurchaseSourceOrdersTableConfig() {
    return {
        columns: [
            {
                key: 'folio',
                label: 'Solicitud',
                width: '175px',
                mobileSecondary: false,
            },
            {
                key: 'branch_name',
                label: 'Sucursal',
                mobileDisplay: true,
            },
            {
                key: 'review_status_label',
                label: 'Estado',
                format: 'badge',
                formatOptions: {
                    statusMap: {
                        Pendiente: 'amber',
                        Aprobada: 'green',
                        Rechazada: 'red',
                    },
                },
                subKey: 'review_activity',
                mobileDisplay: true,
            },
            {
                key: 'requested_by_name',
                label: 'Solicitado por',
                mobileLabel: 'Solicitado por',
                mobileDisplay: true,
            },
            {
                key: 'generated_at',
                label: 'Fecha',
                format: 'date',
                mobileLabel: 'Fecha',
                mobileDisplay: true,
            },
        ],
        actions: [
            {
                id: 'edit',
                label: 'Editar',
                icon: 'edit',
                variant: 'yellow',
                permission: 'inventory.purchase-orders.source.update',
                hidden: (row) => row.review_status !== 'PENDING' || row.in_draft,
            },
            {
                id: 'approve',
                label: 'Aceptar',
                icon: 'check_circle',
                variant: 'green',
                permission: 'inventory.purchase-orders.source.review',
                hidden: (row) => row.review_status !== 'PENDING' || row.selected || row.in_draft,
            },
            {
                id: 'reopen',
                label: 'Regresar a revisión',
                icon: 'undo',
                variant: 'blue',
                permission: 'inventory.purchase-orders.source.review',
                hidden: (row) => !['REJECTED', 'APPROVED'].includes(row.review_status),
            },
            {
                id: 'reject',
                label: 'Rechazar',
                icon: 'cancel',
                variant: 'red',
                permission: 'inventory.purchase-orders.source.review',
                hidden: (row) => !['PENDING', 'APPROVED'].includes(row.review_status) || row.in_draft,
            },
            {
                id: 'delete',
                label: 'Eliminar',
                icon: 'delete',
                variant: 'red',
                permission: 'inventory.purchase-orders.source.review',
                hidden: (row) => row.review_status !== 'REJECTED',
            },
        ],
        mobileCardHeaderField: 'folio',
        noDataMessage: 'No hay solicitudes en esta sección.',
        rowKey: 'id',
        hoverEffect: true,
    }
}
