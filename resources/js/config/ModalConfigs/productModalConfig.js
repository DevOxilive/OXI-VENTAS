import { modalPresets } from './modalPresets'

export function getProductModalConfig({
    mode = 'create',
    totalErrors = 0,
    processing = false,
} = {}) {
    return {
        mode,
        modeTitles: {
            create: 'Registrar producto',
            edit: 'Actualizar producto',
            view: 'Detalle del producto',
        },
        subtitle: 'Información general del producto',
        totalErrors,
        processing,
        saveButtonText: mode === 'edit'
            ? 'Actualizar producto'
            : 'Guardar producto',
        closeButtonText: 'Cancelar',
        ...modalPresets.workspace,
        size: 'full',
        height: 'auto',
        alerts: {
            entityName: 'Producto',
            create: {
                successTitle: 'Producto creado correctamente',
                errorTitle: 'Error al crear producto',
            },
            edit: {
                successTitle: 'Producto actualizado correctamente',
                errorTitle: 'Error al actualizar producto',
            },
            delete: {
                successTitle: 'Producto eliminado correctamente',
                errorTitle: 'Error al eliminar producto',
            },
        },
    }
}
