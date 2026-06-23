import { modalPresets } from './modalPresets'

export function getPhysicalCountEntryModalConfig({
    mode = 'view',
    totalErrors = 0,
    processing = false,
} = {}) {
    return {
        mode,
        modeTitles: {
            view: 'Detalle del registro',
            edit: 'Editar registro',
            delete: 'Eliminar registro',
        },
        subtitle: mode === 'delete'
            ? 'Confirma la eliminacion del registro seleccionado'
            : 'Información del registro de conteo físico',
        totalErrors,
        processing,
        saveButtonText: mode === 'delete'
            ? 'Eliminar registro'
            : 'Guardar cambios',
        closeButtonText: 'Cancelar',
        ...modalPresets.workspace,
        size: 'lg',
        alerts: {
            entityName: 'Registro',
            edit: {
                successTitle: 'Registro actualizado correctamente',
                errorTitle: 'Error al actualizar',
                errorMessage: 'No fue posible actualizar el registro.',
            },
            delete: {
                successTitle: 'Registro eliminado correctamente',
                errorTitle: 'Error al eliminar',
                errorMessage: 'No fue posible eliminar el registro.',
            },
        },
    }
}
