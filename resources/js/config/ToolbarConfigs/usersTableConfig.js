// resources/js/config/TableConfigs/usersTableConfig.js

export function getUsersTableConfig({
    viewMode,
    can,
    onViewUser,
    onCreateUser,
    onEditUser,
    onDeleteUser,
}) {
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
                id: "view",
                label: "Visualizar",
                icon: "visibility",
                variant: "slate",
                handler: onViewUser,
                hidden: () => viewMode !== "users" || !can("users.view"),
                mobile: "button",
            },
            {
                id: "create-user",
                label: "Crear usuario",
                icon: "person_add",
                variant: "blue",
                handler: onCreateUser,
                hidden: () => viewMode !== "employees" || !can("users.create"),
                mobile: "button",
            },
            {
                id: "edit",
                label: "Editar",
                icon: "edit",
                variant: "amber",
                handler: onEditUser,
                hidden: () => viewMode !== "users" || !can("users.update"),
                mobile: "button",
            },
            {
                id: "delete",
                label: "Eliminar",
                icon: "delete",
                variant: "red",
                handler: onDeleteUser,
                hidden: () => viewMode !== "users" || !can("users.delete"),
                mobile: "button",
            },
        ],

        mobileCardHeaderField: "displayName",
        noDataMessage: "No se encontraron registros.",
        rowKey: "id",
    };
}
