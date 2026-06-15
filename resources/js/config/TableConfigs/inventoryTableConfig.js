export const inventoryTableConfig = {
    columns: [
        {
            key: "code",
            label: "Código",
            format: "text",
            width: "13%",
            mobileLabel: "Código",
            fallback: "Sin código",
        },
        {
            key: "name",
            label: "Producto",
            format: "text",
            width: "28%",
            mobileSecondary: true,
        },
        {
            key: "category_name",
            label: "Categoría",
            format: "text",
            width: "18%",
            mobileDisplay: false,
            fallback: "Sin categoría",
        },
        {
            key: "stockLabel",
            label: "Stock",
            format: "text",
            width: "15%",
            mobileLabel: "Stock",
            fallback: "0",
            subKey: "minStockLabel",
        },
        {
            key: "status",
            label: "Estado",
            format: "badge",
            width: "13%",
            formatOptions: {
                statusMap: {
                    Disponible: "green",
                    "Stock bajo": "amber",
                    Agotado: "red",
                    "Sin surtir": "purple",
                },
            },
            mobileBadge: true,
        },
    ],

    actions: [
        {
            id: "entry",
            label: "Entrada",
            icon: "add",
            variant: "green",
            permission: "inventory.update",
        },
        {
            id: "exit",
            label: "Salida",
            icon: "remove",
            variant: "red",
            permission: "inventory.update",
        },
        {
            id: "batches",
            label: "Lotes",
            icon: "inventory_2",
            variant: "blue",
            permission: "inventory.view",
            hidden: (row) => !row.batches?.length,
        },
        {
            id: "movements",
            label: "Historial",
            icon: "history",
            variant: "slate",
            permission: "inventory.view",
        },
    ],

    mobileCardHeaderField: "name",
    noDataMessage: "No se encontraron productos.",
    rowKey: "id",
};
