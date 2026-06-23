import { modalPresets } from './modalPresets'

export function getStockEntryModalConfig({
    totalErrors = 0,
    processing = false,
} = {}) {
    return {
        mode: 'create',
        title: 'Entrada',
        subtitle: 'Registra producto que entra al inventario.',
        totalErrors,
        processing,
        saveButtonText: 'Registrar entrada',
        closeButtonText: 'Cancelar',
        ...modalPresets.standard,
        alerts: {
            entityName: 'Entrada de stock',
            create: {
                successTitle: 'Entrada registrada correctamente',
                errorTitle: 'Error al registrar entrada',
            },
        },
    }
}
