import { modalPresets } from './modalPresets'

export function getAuditBatchModalConfig({
    totalErrors = 0,
    processing = false,
    productName = 'Producto',
} = {}) {
    return {
        mode: 'create',
        title: 'Crear lote',
        subtitle: `Registra un lote para usarlo en el conteo de ${productName}.`,
        totalErrors,
        processing,
        saveButtonText: 'Guardar lote',
        closeButtonText: 'Cancelar',
        ...modalPresets.workspace,
        size: 'xl',
        alerts: {
            entityName: 'Lote',
            create: {
                successTitle: 'Lote creado correctamente',
                errorTitle: 'Error al crear lote',
                errorMessage: 'No fue posible registrar el lote de auditoría.',
            },
        },
    }
}
