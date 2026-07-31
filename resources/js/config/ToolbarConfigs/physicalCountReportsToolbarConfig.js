export function getPhysicalCountReportsToolbarConfig({
    branch,
    form,
    pagination,
} = {}) {
    return {
        icon: 'assessment',
        title: 'Reportes de auditoría',
        subtitle: branch?.name
            ? `Sucursal ${branch.name}`
            : 'Todas las sucursales accesibles',
        backButton: true,
        backLabel: 'Centro de reportes',
        showSearch: true,
        searchPlaceholder: 'Buscar auditoría por nombre o folio...',
        recordsPerPage: Number(form?.per_page || 25),
        recordsPerPageOptions: [10, 25, 50, 100],
        showRecordsPerPage: true,
        totalRecords: Number(pagination?.total || 0),
        filteredRecords: Number(pagination?.total || 0),
        showCounter: true,
        filters: [],
        actions: [],
        tabs: [],
    }
}
