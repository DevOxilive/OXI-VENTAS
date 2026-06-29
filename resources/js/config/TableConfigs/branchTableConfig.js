export const branchTableConfig = {
    columns: [
        {
            key: "name",
            label: "Sucursal",
            format: "text",
            subKey: "slug",
            formatOptions: {
                multiline: true,
            },
            mobileSecondary: true,
        },
        {
            key: "color",
            label: "Color",
            format: "swatch",
            mobileLabel: "Color",
            mobileDisplay: true,
        },
    ],

    actions: [
        {
            id: "view",
            label: "Ver",
            icon: "visibility",
            variant: "blue",
            permission: "branches.view",
            mobile: "button",
        },
        {
            id: "edit",
            label: "Editar",
            icon: "edit",
            variant: "amber",
            permission: "branches.update",
            mobile: "button",
        },
        {
            id: "delete",
            label: "Eliminar",
            icon: "delete",
            variant: "red",
            permission: "branches.delete",
            mobile: "button",
        },
    ],

    mobileCardHeaderField: "name",
    noDataMessage: "No hay sucursales registradas",
    rowKey: "id",
}
