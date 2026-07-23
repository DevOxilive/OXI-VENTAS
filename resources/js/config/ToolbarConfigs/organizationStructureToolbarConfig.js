export function getOrganizationTableToolbarConfig({
    entity = "department",
    canCreate = false,
} = {}) {
    const isDepartment = entity === "department";

    return {
        title: isDepartment ? "Departamentos" : "Puestos",
        subtitle: isDepartment
            ? "Departamentos y puestos relacionados"
            : "Puestos disponibles por departamento",
        searchPlaceholder: isDepartment
            ? "Buscar departamento..."
            : "Buscar puesto o departamento...",
        showSearch: true,
        showRecordsPerPage: false,
        actions: [
            {
                id: "create",
                label: isDepartment ? "Nuevo departamento" : "Nuevo puesto",
                icon: isDepartment ? "domain_add" : "person_add",
                variant: "primary",
                hidden: () => !canCreate,
            },
        ],
    };
}
