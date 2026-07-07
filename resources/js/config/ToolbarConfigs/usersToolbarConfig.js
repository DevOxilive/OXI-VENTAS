// resources/js/Config/Toolbars/usersToolbarConfig.js

export function getUsersToolbarConfig({ roles = [], activeFilters = {} }) {
    return {
        showSearch: true,
        showRecordsPerPage: true,
        searchPlaceholder: "Buscar nombre, correo, usuario o rol...",
        filters: [
            {
                key: "user_status",
                label: "Usuario",
                placeholder: "Todos",
                value: activeFilters.userStatus ?? "",
                emptyValue: "",
                options: [
                    { id: "with_user", name: "Con usuario" },
                    { id: "without_user", name: "Sin usuario" },
                ],
                optionLabel: "name",
                optionValue: "id",
            },
            {
                key: "status",
                label: "Estado",
                placeholder: "Todos los estados",
                value: activeFilters.status ?? "",
                emptyValue: "",
                options: [
                    { id: "active", name: "Activo" },
                    { id: "inactive", name: "Inactivo" },
                ],
                optionLabel: "name",
                optionValue: "id",
            },
            {
                key: "role",
                label: "Rol",
                placeholder: "Todos los roles",
                value: activeFilters.role ?? "",
                emptyValue: "",
                options: [
                    { id: "without_role", name: "Sin rol" },
                    ...roles,
                ],
                optionLabel: "name",
                optionValue: "id",
            },
        ],
    };
}
