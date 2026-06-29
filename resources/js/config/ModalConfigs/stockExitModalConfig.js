import { modalPresets } from './modalPresets'

export function getStockExitModalConfig({
    totalErrors = 0,
    processing = false,
} = {}) {
    return {
        mode: 'delete',
        title: 'Salida',
        subtitle: 'Registra producto que sale del inventario.',
        totalErrors,
        processing,
        saveButtonText: 'Registrar salida',
        closeButtonText: 'Cancelar',
        ...modalPresets.standard,
        alerts: {
            entityName: 'Salida de stock',
            delete: {
                successTitle: 'Salida registrada correctamente',
                errorTitle: 'Error al registrar salida',
            },
        },
    }
}
