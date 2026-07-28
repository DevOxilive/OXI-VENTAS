export const inventoryMovementsTableConfig = {
    columns: [
        {
            key: "branch_product.product.name",
            label: "Producto",
            format: "text",
            fallback: "Producto sin nombre",
            mobileSecondary: true,
        },
        {
            key: "branch_product.branch.name",
            label: "Sucursal",
            format: "text",
            fallback: "Sucursal no disponible",
        },
        {
            key: "type",
            label: "Tipo",
            format: "badge",
            formatOptions: {
                labelMap: {
                    IN: "Entrada",
                    OUT: "Salida",
                    ADJUSTMENT: "Ajuste",
                },
                statusMap: {
                    IN: "green",
                    OUT: "red",
                    ADJUSTMENT: "blue",
                },
            },
            mobileBadge: true,
        },
        {
            key: "reason",
            label: "Motivo",
            format: "badge",
            formatOptions: {
                labelMap: {
                    PURCHASE: "Compra",
                    SALE: "Venta",
                    DAMAGED: "Producto dañado",
                    STOLEN: "Producto robado",
                    EXPIRED: "Producto caducado",
                    OTHER: "Otros",
                    TRANSFER: "Transferencia",
                    MANUAL: "Ajuste manual",
                    INVENTORY_DIFFERENCE: "Diferencia de inventario",
                },
            },
        },
        {
            key: "quantity",
            label: "Cantidad",
            format: "number",
            formatOptions: {
                decimals: 3,
            },
        },
        {
            key: "previous_stock",
            label: "Stock anterior",
            format: "number",
            formatOptions: {
                decimals: 3,
            },
        },
        {
            key: "new_stock",
            label: "Nuevo stock",
            format: "number",
            formatOptions: {
                decimals: 3,
            },
        },
        {
            key: "created_at",
            label: "Fecha",
            format: "date",
            formatOptions: {
                format: "datetime",
            },
        },
    ],
    actions: [],
    mobileCardHeaderField: "branch_product.product.name",
    noDataMessage: "No hay movimientos registrados.",
    rowKey: "id",
    striped: true,
    hoverEffect: true,
}
