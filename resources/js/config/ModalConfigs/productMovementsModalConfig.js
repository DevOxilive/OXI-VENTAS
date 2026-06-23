import { modalPresets } from './modalPresets'

export function getProductMovementsModalConfig() {
    return {
        mode: 'view',
        title: 'Historial',
        subtitle: 'Movimientos clasificados por tipo de operacion.',
        totalErrors: 0,
        processing: false,
        closeButtonText: 'Cerrar',
        ...modalPresets.workspaceControlled,
        showSave: false,
    }
}
