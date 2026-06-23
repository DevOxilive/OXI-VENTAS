import { computed } from 'vue'
import {
    ErrorAlert,
    ToastAlert,
    UniversalActionModal,
} from '@/Components/Modales/UniversalActionModal'

export function useModalConfig(config, overrides = {}) {
    return computed(() => ({
        ...config,
        ...overrides,
    }))
}

const actionLabels = {
    create: 'creado',
    edit: 'actualizado',
    update: 'actualizado',
    delete: 'eliminado',
    save: 'guardado',
}

const actionErrorLabels = {
    create: 'crear',
    edit: 'actualizar',
    update: 'actualizar',
    delete: 'eliminar',
    save: 'guardar',
}

export function getModalActionName(mode = 'save') {
    if (mode === 'edit') return 'update'

    return mode
}

export function getModalActionMessages({
    mode = 'save',
    entityName = 'Registro',
    successTitle,
    errorTitle,
    errorMessage,
} = {}) {
    const action = getModalActionName(mode)
    const normalizedEntity = entityName || 'Registro'

    return {
        successTitle:
            successTitle ??
            `${normalizedEntity} ${actionLabels[action] ?? 'guardado'} correctamente`,
        errorTitle:
            errorTitle ??
            `Error al ${actionErrorLabels[action] ?? 'guardar'} ${normalizedEntity.toLowerCase()}`,
        errorMessage:
            errorMessage ??
            `No fue posible ${actionErrorLabels[action] ?? 'guardar'} ${normalizedEntity.toLowerCase()}.`,
    }
}

export function getModalRequestOptions({
    mode = 'save',
    entityName = 'Registro',
    close,
    onSuccess,
    onError,
    preserveScroll = true,
    successTitle,
    errorTitle,
    errorMessage,
    showSuccess = true,
    showError = true,
    ...options
} = {}) {
    const messages = getModalActionMessages({
        mode,
        entityName,
        successTitle,
        errorTitle,
        errorMessage,
    })

    return {
        preserveScroll,
        ...options,
        onSuccess: (...args) => {
            close?.()

            if (showSuccess) {
                ToastAlert({
                    title: messages.successTitle,
                })
            }

            onSuccess?.(...args)
        },
        onError: (...args) => {
            if (showError) {
                ErrorAlert({
                    title: messages.errorTitle,
                    message: messages.errorMessage,
                })
            }

            onError?.(...args)
        },
    }
}

export async function confirmModalAction({
    mode = 'delete',
    entityName = 'registro',
    title,
    message,
    confirmText,
    cancelText = 'Cancelar',
    confirmButtonColor,
} = {}) {
    const action = getModalActionName(mode)
    const actionText = actionErrorLabels[action] ?? 'continuar'

    return UniversalActionModal({
        title: title ?? `${actionText.charAt(0).toUpperCase()}${actionText.slice(1)} ${entityName}`,
        message: message ?? `¿Deseas ${actionText} este ${entityName}?`,
        confirmText: confirmText ?? `Sí, ${actionText}`,
        cancelText,
        confirmButtonColor: confirmButtonColor ?? (action === 'delete' ? '#ef4444' : '#1f2937'),
    })
}
