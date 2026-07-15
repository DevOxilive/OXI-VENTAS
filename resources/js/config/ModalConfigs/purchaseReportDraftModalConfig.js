import { modalPresets } from './modalPresets'

export function getPurchaseReportDraftModalConfig({
    report,
    branchName = '',
    itemCount = 0,
    estimatedTotal = '',
    actualTotal = '',
} = {}) {
    const details = [
        report?.status === 'DRAFT' ? 'Borrador' : report?.status === 'GENERATED' ? 'Generada' : 'Completada',
        `${itemCount} producto${itemCount === 1 ? '' : 's'}`,
        report?.status === 'DRAFT' ? `Estimado ${estimatedTotal}` : `Total ${actualTotal}`,
        branchName ? `Creada desde ${branchName}` : '',
    ].filter(Boolean)

    return {
        mode: 'view',
        title: report?.folio || `Orden #${report?.id ?? ''}`,
        subtitle: details.join(' | '),
        totalErrors: 0,
        processing: false,
        closeButtonText: 'Cerrar',
        ...modalPresets.standard,
        size: '2xl',
        showSave: false,
    }
}
