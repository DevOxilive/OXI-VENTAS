import { modalPresets } from './modalPresets'

export function getPurchaseReportDraftModalConfig({ report } = {}) {
    return {
        mode: 'view',
        title: `Borrador #${report?.id ?? ''}`,
        subtitle: report?.notes || 'Sin notas generales',
        totalErrors: 0,
        processing: false,
        closeButtonText: 'Cerrar',
        ...modalPresets.standard,
        size: 'xl',
        height: 'auto',
        showSave: false,
    }
}
