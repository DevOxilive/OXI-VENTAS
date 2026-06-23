import { modalPresets } from './modalPresets'

export function getBranchModalConfig({
    mode = 'create',
    totalErrors = 0,
    processing = false,
} = {}) {
    return {
        mode,
        modeTitles: {
            create: 'Nueva sucursal',
            edit: 'Editar sucursal',
            view: 'Ver sucursal',
        },
        subtitle: 'Administra la información básica de la sucursal',
        totalErrors,
        processing,
        saveButtonText: mode === 'edit'
            ? 'Actualizar sucursal'
            : 'Guardar sucursal',
        closeButtonText: mode === 'view' ? 'Cerrar' : 'Cancelar',
        ...modalPresets.compact,
        size: 'sm',
        showSave: mode !== 'view',
        alerts: {
            entityName: 'Sucursal',
            create: {
                successTitle: 'Sucursal creada correctamente',
                errorTitle: 'Error al crear sucursal',
            },
            edit: {
                successTitle: 'Sucursal actualizada correctamente',
                errorTitle: 'Error al actualizar sucursal',
            },
            delete: {
                successTitle: 'Sucursal eliminada correctamente',
                errorTitle: 'Error al eliminar sucursal',
            },
        },
    }
}
