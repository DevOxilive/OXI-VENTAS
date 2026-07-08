// resources/js/Config/Toolbars/employeeToolbarConfig.js

export function getEmployeeToolbarConfig({
    canCreate,
    canExport,
    activeFilters = {},
    positions = [],
    departments = [],
    statuses = [],
}) {
    return {
        showSearch: true,
        showRecordsPerPage: true,
        searchPlaceholder: "Buscar empleado por nombre, correo o puesto...",
        compactFilters: true,
        filters: [
            {
                key: "position",
                label: "Puesto",
                placeholder: "Todos los puestos",
                value: activeFilters.position ?? "",
                emptyValue: "",
                options: positions,
                optionLabel: "label",
                optionValue: "value",
            },
            {
                key: "department",
                label: "Departamento",
                placeholder: "Todos los departamentos",
                value: activeFilters.department ?? "",
                emptyValue: "",
                options: departments,
                optionLabel: "label",
                optionValue: "value",
            },
            {
                key: "employmentStatus",
                label: "Estado",
                placeholder: "Todos los estados",
                value: activeFilters.employmentStatus ?? "",
                emptyValue: "",
                options: statuses,
                optionLabel: "label",
                optionValue: "value",
            },
            {
                key: "startDateFrom",
                type: "date",
                label: "Desde",
                value: activeFilters.startDateFrom ?? "",
            },
            {
                key: "startDateTo",
                type: "date",
                label: "Hasta",
                value: activeFilters.startDateTo ?? "",
            },
        ],

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
