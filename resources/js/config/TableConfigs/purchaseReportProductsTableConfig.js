export function getPurchaseReportProductsTableConfig() {
    return {
        columns: [
            {
                key: "primary_code",
                label: "Codigo",
                format: "text",
                width: "110px",
                fallback: "Sin codigo",
                mobileLabel: "Codigo",
            },
            {
                key: "name",
                label: "Producto",
                format: "text",
                fallback: "Producto sin nombre",
                mobileLabel: "Producto",
                mobileSecondary: true,
                formatOptions: {
                    multiline: true,
                },
                subKey: "category_name",
            },
            {
                key: "stock",
                label: "Stock",
                format: "number",
                mobileLabel: "Stock",
                subKey: "stock_status",
                formatOptions: {
                    multiline: true,
                    decimals: 0,
                    fallback: "0",
                },
            },
        ],
        actions: [
            {
                id: "add",
                label: "Agregar",
                icon: "add_shopping_cart",
                variant: "green",
                hidden: (row) => Boolean(row.in_purchase_list),
                mobile: "button",
            },
            {
                id: "remove",
                label: "Quitar",
                icon: "remove_shopping_cart",
                variant: "red",
                hidden: (row) => !row.in_purchase_list,
                mobile: "button",
            },
        ],
        mobileCardHeaderField: "name",
        noDataMessage: "No se encontraron productos para abastecimiento",
        rowKey: "id",
    };
}
