import { modalPresets } from './modalPresets'

export function getBranchProductConfigModalConfig({
    totalErrors = 0,
    processing = false,
} = {}) {
    return {
        mode: 'edit',
        title: 'Configurar producto',
        subtitle: 'Define las reglas generales del producto en esta sucursal.',
        totalErrors,
        processing,
        saveButtonText: 'Guardar configuración',
        closeButtonText: 'Cancelar',
        ...modalPresets.standard,
        size: 'xl',
        alerts: {
            entityName: 'Configuración',
            edit: {
                successTitle: 'Configuración actualizada correctamente',
                errorTitle: 'Error al actualizar configuración',
            },
        },
    }
}
