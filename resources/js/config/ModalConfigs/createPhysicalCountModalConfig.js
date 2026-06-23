import { modalPresets } from './modalPresets'

export function getCreatePhysicalCountModalConfig({
    totalErrors = 0,
    processing = false,
} = {}) {
    return {
        mode: 'create',
        title: 'Nuevo conteo fisico',
        subtitle: 'Crea una nueva sesion para la sucursal seleccionada.',
        totalErrors,
        processing,
        saveButtonText: 'Crear conteo',
        closeButtonText: 'Cancelar',
        ...modalPresets.compact,
        alerts: {
            entityName: 'Conteo fisico',
            create: {
                successTitle: 'Conteo creado correctamente',
                errorTitle: 'Error al crear conteo',
                errorMessage: 'No fue posible registrar el conteo fisico.',
            },
        },
    }
}
