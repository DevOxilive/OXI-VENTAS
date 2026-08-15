export function getPurchaseReportToolbarConfig({
    branchName = '',
    editingFolio = '',
    cycleFolio = '',
    cycleSubmitted = false,
    hasProducts = false,
    canCreate = false,
    canClear = false,
    perPage = 25,
    total = 0,
} = {}) {
    return {
        icon: 'playlist_add',
        title: editingFolio ? `Editando ${editingFolio}` : 'Generar lista de compra',
        subtitle: cycleSubmitted
            ? `${branchName || 'La sucursal'} ya tiene solicitudes enviadas en ${cycleFolio || 'el ciclo actual'}, pero puede crear otra lista.`
            : editingFolio
                ? `Actualiza los productos y cantidades de la lista creada desde ${branchName || 'Sin sucursal'}.`
                : `Solicitud de ${branchName || 'Sin sucursal'} para ${cycleFolio || 'el ciclo actual'}.`,
        backButton: false,
        showSearch: false,
        showRecordsPerPage: true,
        recordsPerPage: Number(perPage),
        showCounter: true,
        filteredRecords: Number(total),
        totalRecords: Number(total),
        filters: [],
        actions: [
            ...(canCreate ? [{
                id: 'submit-empty',
                label: cycleSubmitted ? 'Solicitud enviada' : 'Sin productos',
                icon: cycleSubmitted ? 'task_alt' : 'playlist_remove',
                variant: 'secondary',
                disabled: cycleSubmitted || hasProducts,
            }] : []),
            ...(canClear ? [{
                id: 'clear',
                label: 'Limpiar',
                icon: 'delete_sweep',
                variant: 'red',
            }] : []),
        ],
        tabs: [],
    }
}
