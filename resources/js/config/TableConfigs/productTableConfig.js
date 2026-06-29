/**
 * Configuración para ProductTable
 * Usada en: Pages/Inventory/Products.vue
 */

export const productTableConfig = {
    columns: [
        {
            key: "barcode",
            label: "Código barras",
            format: "text",
            width: "140px",
            mobileLabel: "Código",
            mobileDisplay: true,
            fallback: "Sin código",
        },
        {
            key: "image",
            label: "Imagen",
            format: "badge",
            formatOptions: {
                statusMap: {
                    [true]: "Con imagen",
                    [false]: "Sin imagen",
                },
                colorMap: {
                    true: "green",
                    false: "slate",
                },
            },
            mobileDisplay: false,
        },
        {
            key: "name",
            label: "Producto",
            format: "text",
            formatOptions: {
                multiline: true,
            },
            subKey: "presentation",
            mobileLabel: "Producto",
            mobileSecondary: true,
        },
        {
            key: "category",
            label: "Categoría",
            format: "badge",
            mobileLabel: "Cat.",
            mobileDisplay: false,
        },
        {
            key: "unit",
            label: "Unidad de medida",
            format: "text",
            mobileDisplay: false,
        },
        {
            key: "cost",
            label: "Precio inicial",
            format: "currency",
            formatOptions: {
                decimals: 2,
                fallback: "$0.00",
            },
            mobileDisplay: false,
        },
        {
            key: "price",
            label: "Precio venta",
            format: "currency",
            formatOptions: {
                decimals: 2,
                fallback: "$0.00",
            },
            mobileLabel: "Precio",
            mobileDisplay: true,
        },
    ],

    actions: [
        {
            id: "view",
            label: "Ver",
            icon: "visibility",
            variant: "blue",
            permission: "inventory.view",
        },
        {
            id: "edit",
            label: "Editar",
            icon: "edit",
            variant: "amber",
            permission: "inventory.update",
        },
        {
            id: "delete",
            label: "Eliminar",
            icon: "delete",
            variant: "red",
            permission: "inventory.delete",
        },
    ],

    mobileCardHeaderField: "name",
    noDataMessage: "No se encontraron productos",
};
