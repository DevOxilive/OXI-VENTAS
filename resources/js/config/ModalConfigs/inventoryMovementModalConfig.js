import { modalPresets } from './modalPresets'

export function getInventoryMovementModalConfig({
    totalErrors = 0,
    processing = false,
} = {}) {
    return {
        mode: 'create',
        title: 'Nuevo movimiento',
        subtitle: 'Registrar movimiento de inventario',
        totalErrors,
        processing,
        saveButtonText: 'Guardar movimiento',
        closeButtonText: 'Cancelar',
        ...modalPresets.standard,
        alerts: {
            entityName: 'Movimiento',
            create: {
                successTitle: 'Movimiento registrado correctamente',
                errorTitle: 'Error al registrar movimiento',
                errorMessage: 'No fue posible registrar el movimiento.',
            },
        },
    }
}
