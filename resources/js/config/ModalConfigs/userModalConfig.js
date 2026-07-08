import { modalPresets } from './modalPresets'

export function getUserModalConfig({
    isEditing = false,
    canSave = true,
    totalErrors = 0,
    processing = false,
} = {}) {
    return {
        mode: isEditing ? 'edit' : 'create',
        title: 'Formulario de usuario',
        subtitle: isEditing
            ? 'Actualiza la cuenta de acceso, el rol y los permisos asignados.'
            : 'Configura la cuenta de acceso, el rol y los permisos asignados.',
        totalErrors,
        processing,
        saveButtonText: isEditing
            ? 'Guardar cambios'
            : 'Crear usuario',
        closeButtonText: canSave ? 'Cancelar' : 'Cerrar',
        ...modalPresets.workspace,
        size: 'full',
        showSave: canSave,
        alerts: {
            entityName: 'Usuario',
            create: {
                successTitle: 'Usuario registrado correctamente',
                errorTitle: 'Error al registrar usuario',
            },
            edit: {
                successTitle: 'Usuario actualizado correctamente',
                errorTitle: 'Error al actualizar usuario',
            },
            delete: {
                successTitle: 'Usuario eliminado correctamente',
                errorTitle: 'Error al eliminar usuario',
            },
        },
    }
}
