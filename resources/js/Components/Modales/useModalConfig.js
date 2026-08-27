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

function getFirstRequestError(errors) {
    if (!errors || typeof errors !== 'object') return null

    for (const error of Object.values(errors)) {
        if (Array.isArray(error)) {
            const message = error.find((item) => typeof item === 'string' && item.trim())
            if (message) return message
        }

        if (typeof error === 'string' && error.trim()) return error
    }

    return null
}

function getReadableRequestError(errors) {
    const message = getFirstRequestError(errors)

    // Cuando Laravel no encuentra una traducción devuelve la clave (por ejemplo,
    // "validation.required"). Esa clave nunca debe llegar a una persona usuaria.
    if (!message) return null

    if (/^validation\.[\w.]+$/i.test(message.trim())) {
        return 'Completa el campo para continuar.'
    }

    return message
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
    closeOnSuccess = true,
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
            if (closeOnSuccess) {
                close?.()
            }

            if (showSuccess) {
                ToastAlert({
                    title: messages.successTitle,
                })
            }

            onSuccess?.(...args)
        },
        onError: (errors, ...args) => {
            if (showError) {
                ErrorAlert({
                    title: messages.errorTitle,
                    message: getReadableRequestError(errors) ?? messages.errorMessage,
                })
            }

            onError?.(errors, ...args)
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
    ...modalOptions
} = {}) {
    const action = getModalActionName(mode)
    const actionText = actionErrorLabels[action] ?? 'continuar'

    return UniversalActionModal({
        title: title ?? `${actionText.charAt(0).toUpperCase()}${actionText.slice(1)} ${entityName}`,
        message: message ?? `¿Deseas ${actionText} este ${entityName}?`,
        confirmText: confirmText ?? `Sí, ${actionText}`,
        cancelText,
        confirmButtonColor: confirmButtonColor ?? (action === 'delete' ? '#ef4444' : '#1f2937'),
        ...modalOptions,
    })
}
