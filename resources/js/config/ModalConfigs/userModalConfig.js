import { modalPresets } from './modalPresets'

export function getUserModalConfig({
    isEditing = false,
    canSave = true,
    totalErrors = 0,
    processing = false,
} = {}) {
    return {
        mode: isEditing ? 'edit' : 'create',
        title: isEditing ? 'Actualizar usuario' : 'Registrar usuario',
        subtitle: 'Configuracion de acceso y permisos',
        totalErrors,
        processing,
        saveButtonText: isEditing
            ? 'Actualizar usuario'
            : 'Guardar usuario',
        closeButtonText: canSave ? 'Cancelar' : 'Cerrar',
        ...modalPresets.workspace,
        size: 'lg',
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
