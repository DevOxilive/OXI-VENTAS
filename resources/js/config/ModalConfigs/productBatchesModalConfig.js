import { modalPresets } from './modalPresets'

export function getProductBatchesModalConfig({
    totalErrors = 0,
    processing = false,
} = {}) {
    return {
        mode: 'edit',
        title: 'Lotes del producto',
        subtitle: 'Administra, consulta y ajusta los lotes de este producto.',
        totalErrors,
        processing,
        saveButtonText: 'Guardar cambios',
        closeButtonText: 'Cerrar',
        ...modalPresets.workspaceControlled,
        alerts: {
            entityName: 'Lotes',
            edit: {
                successTitle: 'Lotes actualizados correctamente',
                errorTitle: 'Error al actualizar lotes',
            },
        },
    }
}
