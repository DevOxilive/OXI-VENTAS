export function getPurchaseReportToolbarConfig({ branchName = '', editingFolio = '' } = {}) {
    return {
        title: editingFolio ? `Editando ${editingFolio}` : 'Lista de abastecimiento',
        subtitle: editingFolio
            ? `Actualiza productos y cantidades de la orden creada desde ${branchName || 'Sin sucursal'}.`
            : `Creada desde ${branchName || 'Sin sucursal'}. Busca por codigo, codigo alterno, nombre o categoria.`,
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
