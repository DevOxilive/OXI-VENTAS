export function getPhysicalCountDetailToolbarConfig({ physicalCount }) {
    const statusLabels = {
        open: 'Abierta',
        closed: 'Cerrada',
        applied: 'Aplicada',
    }

    return {
        icon: 'fact_check',
        title: physicalCount.name || 'Detalle de auditoría',
        subtitle: `Sucursal: ${physicalCount.branch?.name || 'Sin sucursal'} · Estado: ${statusLabels[physicalCount.status] || 'Sin estado'}`,
        backButton: true,
        backLabel: 'Auditorías',
    }
}
