export function getBranchToolbarConfig({ canCreate }) {
    return {
        title: "Registro de sucursales",
        subtitle: "Administra las sucursales y asigna su color de identificacion.",
        showSearch: true,
        searchPlaceholder: "Buscar sucursal por nombre, slug o color...",
        showRecordsPerPage: false,
        actions: [
            {
                id: "create",
                label: "Nueva sucursal",
                icon: "add_circle",
                variant: "primary",
                hidden: () => !canCreate,
            },
        ],
    }
}
