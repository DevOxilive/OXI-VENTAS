export const inventoryReportTableConfig = {
    columns: [
        {
            key: "product",
            label: "Producto",
            format: "text",
            mobileLabel: "Producto",
            mobileSecondary: true,
        },
        {
            key: "category",
            label: "Categoría",
            format: "text",
            mobileLabel: "Categoría",
            mobileDisplay: true,
        },
        {
            key: "lot_number",
            label: "Lote",
            format: "text",
            mobileLabel: "Lote",
            mobileDisplay: true,
        },
        {
            key: "quantity",
            label: "Cantidad",
            format: "number",
            mobileLabel: "Cantidad",
            mobileDisplay: true,
        },
        {
            key: "expiration_date",
            label: "Fecha",
            format: "date",
            mobileLabel: "Fecha",
            mobileDisplay: true,
        },
        {
            key: "days",
            label: "Días",
            format: "number",
            mobileLabel: "Días",
            mobileDisplay: true,
        },
        {
            key: "unit_cost",
            label: "Costo unitario",
            format: "currency",
        },
        {
            key: "estimated_loss",
            label: "Impacto estimado",
            format: "currency",
        },
    ],

    actions: [],

    mobileCardHeaderField: "product",
    noDataMessage: "No se encontraron registros para este reporte",
    rowKey: "id",
    striped: true,
    hoverEffect: true,
    showPagination: false,
};
