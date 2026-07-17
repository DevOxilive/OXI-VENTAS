export function getGeneralPurchaseOrderModalConfig({ order = {}, branchCount = 0 } = {}) {
    return {
        title: order.folio || 'Orden general de compra',
        subtitle: `${order.items?.length || 0} productos · ${branchCount} sucursales`,
        mode: 'view',
        columns: 1,
        size: '2xl',
        height: 'auto',
        scrollMode: 'auto',
        showSave: false,
        closeButtonText: 'Cerrar',
    }
}
