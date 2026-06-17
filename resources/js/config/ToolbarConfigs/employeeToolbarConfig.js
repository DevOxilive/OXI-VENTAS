// resources/js/Config/Toolbars/employeeToolbarConfig.js

export function getEmployeeToolbarConfig({ canCreate, canExport }) {
    return {
        showSearch: false,
        showRecordsPerPage: true,
        searchPlaceholder: "Buscar empleado...",

        actions: [
            {
                id: "create",
                label: "Nuevo empleado",
                icon: "add_circle",
                variant: "primary",
                hidden: () => !canCreate,
            },
            {
                id: "export",
                label: "Exportar Excel",
                icon: "download",
                variant: "slate",
                hidden: () => !canExport,
            },
        ],
    };
}
