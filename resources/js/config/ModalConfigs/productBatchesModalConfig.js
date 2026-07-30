import { modalPresets } from './modalPresets'

export function getProductBatchesModalConfig({
    totalErrors = 0,
    processing = false,
    productName = 'Producto',
} = {}) {
    return {
        mode: 'edit',
        title: `Lotes del producto - ${productName}`,
        subtitle: 'Administra, consulta y ajusta los lotes de este producto.',
        totalErrors,
        processing,
        saveButtonText: 'Guardar cambios',
        closeButtonText: 'Cerrar',
        ...modalPresets.standard,
        size: '2xl',
        height: 'full',
        scrollMode: 'controlled',
        alerts: {
            entityName: 'Lotes',
            edit: {
                successTitle: 'Lotes actualizados correctamente',
                errorTitle: 'Error al actualizar lotes',
            },
        },
    }
}
