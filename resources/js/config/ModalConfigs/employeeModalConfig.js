import { modalPresets } from './modalPresets'

export function getEmployeeModalConfig({
    mode = 'create',
    totalErrors = 0,
    processing = false,
} = {}) {
    return {
        mode,
        modeTitles: {
            create: 'Registrar empleado',
            edit: 'Actualizar empleado',
            view: 'Detalle del empleado',
        },
        subtitle: 'Información general del empleado',
        totalErrors,
        processing,
        saveButtonText: mode === 'edit'
            ? 'Actualizar empleado'
            : 'Guardar empleado',
        closeButtonText: 'Cancelar',
        ...modalPresets.workspace,
        alerts: {
            entityName: 'Empleado',
            create: {
                successTitle: 'Empleado registrado correctamente',
                errorTitle: 'Error al registrar empleado',
            },
            edit: {
                successTitle: 'Empleado actualizado correctamente',
                errorTitle: 'Error al actualizar empleado',
            },
            delete: {
                successTitle: 'Empleado eliminado correctamente',
                errorTitle: 'Error al eliminar empleado',
            },
        },
    }
}
