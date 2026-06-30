/**
 * Configuración para ProductTable
 * Usada en: Components/Inventory/ProductTable.vue
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
            key: "name",
            label: "Producto",
            format: "text",
            formatOptions: {
                multiline: true,
            },
            subKey: "presentation",
            mobileLabel: "Producto",
            mobileSecondary: true,
            fallback: "Producto sin nombre",
        },
        {
            key: "category",
            label: "Categoría",
            format: "badge",
            mobileLabel: "Cat.",
            mobileDisplay: false,
            fallback: "Sin categorÃ­a",
        },
        {
            key: "unit",
            label: "Unidad de medida",
            format: "text",
            mobileDisplay: false,
            fallback: "Sin unidad",
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
            permission: "inventory.products.view",
            mobile: "button",
        },
        {
            id: "edit",
            label: "Editar",
            icon: "edit",
            variant: "amber",
            permission: "inventory.products.update",
            mobile: "button",
        },
        {
            id: "delete",
            label: "Eliminar",
            icon: "delete",
            variant: "red",
            permission: "inventory.products.delete",
            mobile: "button",
        },
    ],

    mobileCardHeaderField: "name",
    noDataMessage: "No se encontraron productos",
    rowKey: "id",
};
