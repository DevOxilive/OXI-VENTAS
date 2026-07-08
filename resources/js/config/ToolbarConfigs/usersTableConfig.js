// resources/js/config/TableConfigs/usersTableConfig.js

export function getUsersTableConfig({
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
                fallback: "-",
            },
            {
                key: "displayUsername",
                label: "Usuario",
                format: "badge",
                fallback: "Sin usuario",
            },
            {
                key: "displayRole",
                label: "Rol",
                format: "badge",
                fallback: "Sin rol",
            },
            {
                key: "displayStatus",
                label: "Estado",
                format: "badge",
                fallback: "Activo",
                formatOptions: {
                    colorMap: {
                        Activo: "green",
                        Inactivo: "red",
                    },
                },
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
                hidden: (row) => !row?.has_user || !can("users.view"),
                mobile: "button",
            },
            {
                id: "create-user",
                label: "Crear usuario",
                icon: "person_add",
                variant: "blue",
                handler: onCreateUser,
                hidden: (row) => row?.has_user || row?.displayStatus === "Inactivo" || !can("users.create"),
                mobile: "button",
            },
            {
                id: "edit",
                label: "Editar",
                icon: "edit",
                variant: "amber",
                handler: onEditUser,
                hidden: (row) => !row?.has_user || !can("users.update"),
                mobile: "button",
            },
            {
                id: "delete",
                label: "Eliminar",
                icon: "delete",
                variant: "red",
                handler: onDeleteUser,
                hidden: (row) => !row?.has_user || !can("users.delete"),
                mobile: "button",
            },
        ],

        mobileCardHeaderField: "displayName",
        noDataMessage: "No se encontraron registros.",
        rowKey: "row_id",
    };
}
