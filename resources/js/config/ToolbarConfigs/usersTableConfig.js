// resources/js/config/TableConfigs/usersTableConfig.js

export function getUsersTableConfig({ viewMode, can }) {
    return {
        columns: [
            {
                key: "displayName",
                label: "Nombre",
                format: "text",
                mobileSecondary: true,
            },
            {
                key: "displayEmail",
                label: "Correo",
                format: "text",
                fallback: "—",
            },
            {
                key: "displayRole",
                label: "Rol",
                format: "badge",
                fallback: "Sin rol",
                mobileBadge: true,
            },
        ],

        actions: [
            {
                id: "create-user",
                label: "Crear usuario",
                icon: "person_add",
                variant: "blue",
                hidden: () => viewMode !== "employees" || !can("users.create"),
                mobile: "button",
            },
            {
                id: "edit",
                label: "Editar",
                icon: "edit",
                variant: "amber",
                hidden: () => viewMode !== "users" || !can("users.update"),
                mobile: "button",
            },
            {
                id: "delete",
                label: "Eliminar",
                icon: "delete",
                variant: "red",
                hidden: () => viewMode !== "users" || !can("users.delete"),
                mobile: "button",
            },
        ],

        mobileCardHeaderField: "displayName",
        noDataMessage: "No se encontraron registros.",
        rowKey: "id",
    };
}
