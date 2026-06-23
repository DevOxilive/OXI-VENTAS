import { modalPresets } from './modalPresets'

export function getInventoryAlertsModalConfig({
    title = '',
    subtitle = '',
} = {}) {
    return {
        mode: 'view',
        title,
        subtitle,
        totalErrors: 0,
        processing: false,
        closeButtonText: 'Cerrar',
        ...modalPresets.workspace,
        size: 'xl',
        showSave: false,
    }
}
