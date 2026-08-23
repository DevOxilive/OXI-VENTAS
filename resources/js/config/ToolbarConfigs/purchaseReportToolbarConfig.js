export function getPurchaseReportToolbarConfig({
    branchName = '',
    editingFolio = '',
    cycleFolio = '',
    cycleSubmitted = false,
    filters = {},
    departments = [],
    categories = [],
    products = [],
    stockOptions = [],
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
        filters: [
            {
                key: 'departmentIds',
                label: 'Departamentos',
                placeholder: 'Todos los departamentos',
                type: 'multiselect',
                value: filters.departmentIds ?? [],
                options: departments,
                optionLabel: 'name',
                optionValue: 'id',
            },
            {
                key: 'categoryIds',
                label: 'Categorias',
                placeholder: 'Todas las categorias',
                type: 'multiselect',
                value: filters.categoryIds ?? [],
                options: categories,
                optionLabel: 'name',
                optionValue: 'id',
            },
            {
                key: 'productIds',
                label: 'Productos',
                placeholder: 'Todos los productos',
                type: 'multiselect',
                value: filters.productIds ?? [],
                options: products,
                optionLabel: 'name',
                optionValue: 'id',
            },
            {
                key: 'stock',
                label: 'Stock',
                type: 'select',
                placeholder: 'Todo el stock',
                value: filters.stock ?? '',
                options: stockOptions,
            },
        ],
        actions: [],
        tabs: [],
    }
}
