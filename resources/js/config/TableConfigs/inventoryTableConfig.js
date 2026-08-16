export const inventoryTableConfig = {
    columns: [
        {
            key: "code",
            label: "Codigo",
            format: "text",
            width: "13%",
            mobileLabel: "Codigo",
            fallback: "Sin codigo",
        },
        {
            key: "name",
            label: "Producto",
            format: "text",
            width: "24%",
            mobileSecondary: true,
        },
        {
            key: "product_department_name",
            label: "Departamento",
            format: "text",
            width: "16%",
            mobileDisplay: false,
            fallback: "Sin departamento",
        },
        {
            key: "category_name",
            label: "Categoria",
            format: "text",
            width: "16%",
            mobileDisplay: false,
            fallback: "Sin categoria",
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
                    "Producto sin rotacion": "purple",
                },
            },
            mobileBadge: true,
        },
    ],

    actions: [
        {
            id: "entry",
            label: "Ingresar lotes",
            icon: "add",
            variant: "green",
            permission: "inventory.branches.stock-in",
        },
        {
            id: "exit",
            label: "Sacar lotes",
            icon: "remove",
            variant: "red",
            permission: "inventory.branches.stock-out",
        },
        {
            id: "batches",
            label: "Editar lotes",
            icon: "inventory_2",
            variant: "blue",
            permission: "inventory.branches.batches.update",
            hidden: (row) =>
                Number(row.activeBatchesCount ?? row.batches?.length ?? 0) <= 0,
        },
        {
            id: "movements",
            label: "Ver historial",
            icon: "history",
            variant: "slate",
            permission: "inventory.branches.view",
        },
    ],

    mobileCardHeaderField: "name",
    noDataMessage: "No se encontraron productos.",
    rowKey: "id",
};
