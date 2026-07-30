import { modalPresets } from './modalPresets'

export function getStockEntryModalConfig({
    totalErrors = 0,
    processing = false,
    productName = 'Producto',
} = {}) {
    return {
        mode: 'create',
        title: `Entrada - ${productName}`,
        subtitle: 'Registra producto que entra al inventario.',
        totalErrors,
        processing,
        saveButtonText: 'Registrar entrada',
        closeButtonText: 'Cancelar',
        ...modalPresets.standard,
        size: '2xl',
        alerts: {
            entityName: 'Entrada de stock',
            create: {
                successTitle: 'Entrada registrada correctamente',
                errorTitle: 'Error al registrar entrada',
            },
        },
    }
}
