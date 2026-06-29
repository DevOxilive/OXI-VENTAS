export const modalSizeStyles = {
    sm: { maxWidth: '32rem' },
    md: { maxWidth: '42rem' },
    lg: { maxWidth: '56rem' },
    xl: { maxWidth: '72rem' },
    '2xl': { maxWidth: '80rem' },
    full: { width: '94vw', maxWidth: '1480px' },
}

export const modalHeightStyles = {
    auto: { height: 'auto', maxHeight: 'calc(100dvh - 2rem)' },
    full: { height: 'min(90dvh, calc(100dvh - 2rem))', maxHeight: 'calc(100dvh - 2rem)' },
}

export const modalBackdropStyles = {
    default: { backgroundColor: 'rgba(0, 0, 0, 0.7)' },
    soft: {
        backgroundColor: 'rgba(2, 6, 23, 0.6)',
        backdropFilter: 'blur(4px)',
    },
}

export function getModalSizeStyle(size = 'xl') {
    return modalSizeStyles[size] ?? modalSizeStyles.xl
}

export function getModalHeightStyle(height = 'full') {
    return modalHeightStyles[height] ?? modalHeightStyles.full
}

export function getModalPanelStyle({ size = 'xl', height = 'full' } = {}) {
    return {
        ...getModalSizeStyle(size),
        ...getModalHeightStyle(height),
    }
}

export function getModalBackdropStyle(backdrop = 'default') {
    return modalBackdropStyles[backdrop] ?? modalBackdropStyles.default
}
