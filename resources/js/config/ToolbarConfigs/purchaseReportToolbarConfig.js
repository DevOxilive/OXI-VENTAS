export function getPurchaseReportToolbarConfig({ branchName = '', editingFolio = '' } = {}) {
    return {
        title: editingFolio ? `Editando ${editingFolio}` : 'Generar lista de compra',
        subtitle: editingFolio
            ? `Actualiza los productos y cantidades de la lista creada desde ${branchName || 'Sin sucursal'}.`
            : `Creada desde ${branchName || 'Sin sucursal'}. Busca por codigo, codigo alterno, nombre o categoria.`,
        backButton: false,
        showSearch: false,
        showRecordsPerPage: false,
        showCounter: false,
        filters: [],
        actions: [
            {
                id: 'clear',
                label: 'Limpiar',
                icon: 'delete_sweep',
                variant: 'red',
            },
        ],
        tabs: [],
    }
}
