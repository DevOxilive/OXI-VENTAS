export const expirationsReportTableConfig = {
    columns: [
        {
            key: "product",
            label: "Producto",
            format: "text",
            width: "25%",
            mobileSecondary: true,
            fallback: "Sin producto",
        },
        {
            key: "category",
            label: "Categoría",
            format: "text",
            width: "16%",
            mobileDisplay: false,
            fallback: "Sin categoría",
        },
        {
            key: "lot_number",
            label: "Lote",
            format: "text",
            width: "13%",
            mobileLabel: "Lote",
            fallback: "Sin lote",
        },
        {
            key: "quantity",
            label: "Cantidad",
            format: "text",
            width: "10%",
            mobileLabel: "Cantidad",
            fallback: "0",
        },
        {
            key: "expiration_date",
            label: "Caducidad",
            format: "text",
            width: "13%",
            mobileLabel: "Caducidad",
            fallback: "Sin fecha",
        },
        {
            key: "days_to_expire",
            label: "Días",
            format: "text",
            width: "8%",
            mobileDisplay: false,
            fallback: "-",
        },
        {
            key: "expiration_status",
            label: "Estado",
            format: "badge",
            width: "15%",
            formatOptions: {
                statusMap: {
                    EXPIRED: "red",
                    NEAR_EXPIRATION: "amber",
                    VALID: "green",
                    NO_EXPIRATION: "slate",
                },
            },
            mobileBadge: true,
            fallback: "N/A",
        },
    ],

    actions: [],

    mobileCardHeaderField: "product",
    noDataMessage: "No hay lotes caducados o próximos a caducar.",
    rowKey: "id",
};
