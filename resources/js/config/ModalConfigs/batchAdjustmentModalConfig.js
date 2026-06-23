import { modalPresets } from './modalPresets'

export function getBatchAdjustmentModalConfig({
    totalErrors = 0,
    processing = false,
} = {}) {
    return {
        mode: 'edit',
        title: 'Ajustar lote',
        subtitle: 'Corrige la informacion registrada del lote.',
        totalErrors,
        processing,
        saveButtonText: 'Guardar cambios',
        closeButtonText: 'Cancelar',
        ...modalPresets.workspace,
        alerts: {
            entityName: 'Lote',
            edit: {
                successTitle: 'Lote actualizado correctamente',
                errorTitle: 'Error al actualizar lote',
            },
        },
    }
}
