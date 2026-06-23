export function getPhysicalCountDetailToolbarConfig({ physicalCount }) {
    return {
        title: physicalCount.name || 'Detalle de auditoria',
        subtitle: `Sucursal: ${physicalCount.branch?.name || 'Sin sucursal'} · Estado: ${physicalCount.status}`,
        actions: [
            {
                id: 'back',
                label: 'Volver',
                icon: 'arrow_back',
                variant: 'slate',
            },
        ],
    }
}
