export const physicalCountComparisonTableConfig = {
    mobileCardHeaderField: "product_name",
    noDataMessage: "No hay comparativo de inventario.",
    columns: [
        {
            key: "product_name",
            label: "Producto",
        },
        {
            key: "system_stock",
            label: "Stock sistema",
            format: "number",
        },
        {
            key: "counted_stock",
            label: "Conteo físico",
            format: "number",
        },
        {
            key: "difference",
            label: "Diferencia",
            format: "number",
        },
    ],
    actions: [],
};
