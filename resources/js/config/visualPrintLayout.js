export const DOCUMENT_KINDS = [
    { key: "sales", label: "Ventas" },
    { key: "statement", label: "Estados de cuenta" },
    { key: "closure", label: "Cortes" },
    { key: "sale-return", label: "Devoluciones" },
    { key: "credit-payment", label: "Abonos" },
    { key: "label", label: "Etiquetas" },
];
export const FIELD_LABELS = {
    name: "Nombre del producto",
    price: "Precio",
    barcode: "Código de barras",
    category: "Categoría",
    sku: "Clave",
    stock: "Existencia",
    branch: "Sucursal de producto",
    date: "Fecha",
    folio: "Folio",
    branch_name: "Sucursal / titular",
    user_name: "Atendió",
    cash_box_text: "Caja",
    document_title: "Título del documento",
    total: "Total",
    cash_received: "Recibido",
    change_due: "Cambio",
    reason: "Motivo",
    original_sale_folio: "Venta original",
    balance_before: "Adeudo anterior",
    balance_after: "Adeudo pendiente",
    payment_amount: "Abono",
    account_balance_before: "Adeudo antes de devolución",
    account_balance_after: "Adeudo después de devolución",
    payment_method: "Forma de pago",
    expected_cash: "Efectivo esperado",
    counted_cash: "Efectivo contado",
    cash_left: "Fondo de caja",
    cash_withdrawal: "Retiro",
    cash_difference: "Diferencia efectivo",
    card_total: "Tarjeta registrada",
    counted_card: "Tarjeta contada",
    card_difference: "Diferencia tarjeta",
    sales_count: "Cantidad de ventas",
    sales_total: "Ventas totales",
    notes: "Observaciones",
};
const MONEY = new Set([
    "price",
    "total",
    "cash_received",
    "change_due",
    "balance_before",
    "balance_after",
    "payment_amount",
    "account_balance_before",
    "account_balance_after",
    "expected_cash",
    "counted_cash",
    "cash_left",
    "cash_withdrawal",
    "cash_difference",
    "card_total",
    "counted_card",
    "card_difference",
    "sales_total",
]);
export function fieldText(binding, data) {
    const value = data?.[binding];
    if (value === null || value === undefined) return "";
    return MONEY.has(binding) ? `$${Number(value).toFixed(2)}` : String(value);
}
export function elementText(element, data) {
    if (element.type === "field")
        return `${element.text || ""}${fieldText(element.binding, data)}`;
    if (element.type === "items") return itemLines(data).join("\n");
    return String(element.text || "");
}
export function itemLines(data = {}) {
    if (data.type === "cash_closure")
        return Object.entries(data.denomination_breakdown || {})
            .filter(([, q]) => Number(q) > 0)
            .map(([value, q]) => `${value}: ${q}`);
    if (data.type === "employee_credit_payment")
        return (data.paid_charges || []).map(
            (row) => `${row.folio} · $${Number(row.amount || 0).toFixed(2)}`,
        );
    if (data.type === "employee_credit_statement") {
        const rows = (data.credit_groups || []).flatMap((group) => [
            group.label || "",
            ...(group.charges || []).flatMap((charge) => [
                `${charge.folio} · $${Number(charge.total || 0).toFixed(2)}`,
                ...(charge.items || []).map(
                    (item) =>
                        `${item.quantity_label || ""} ${item.product || ""} · $${Number(item.subtotal || 0).toFixed(2)}`,
                ),
            ]),
        ]);
        return [
            ...rows,
            ...(data.credit_returns || []).map(
                (row) =>
                    `${row.folio || "Devolución"} · $${Number(row.amount || 0).toFixed(2)}`,
            ),
        ];
    }
    return (data.items || []).flatMap((item) => [
        String(item.product_name || "Producto"),
        `${item.quantity ?? 1} × $${Number(item.unit_price || 0).toFixed(2)}   $${Number(item.subtotal || 0).toFixed(2)}`,
    ]);
}
export function createVisualElement(type, overrides = {}) {
    return {
        id:
            globalThis.crypto?.randomUUID?.() ||
            `node-${Date.now()}-${Math.random().toString(36).slice(2)}`,
        type,
        x: 4,
        y: 4,
        width: 40,
        height: 8,
        text: type === "text" ? "Texto libre" : "",
        binding: type === "barcode" ? "barcode" : "folio",
        font_size: 10,
        bold: false,
        align: "left",
        fill: "#000000",
        stroke: type === "line" || type === "rect" ? 0.3 : 0,
        visible: true,
        locked: false,
        ...overrides,
    };
}
export function createVisualLayout(kind = "sales") {
    const label = kind === "label";
    const width = label ? 62 : 80;
    const titles = {
        sales: "TICKET DE VENTA",
        statement: "ESTADO DE CUENTA",
        closure: "CORTE DE CAJA",
        "sale-return": "COMPROBANTE DE DEVOLUCIÓN",
        "credit-payment": "COMPROBANTE DE ABONO",
    };
    const e = (type, settings) => createVisualElement(type, settings);
    if (label)
        return {
            version: 2,
            dpi: label ? 300 : 203,
            width,
            height: 40,
            auto_height: false,
            elements: [
                e("field", {
                    binding: "name",
                    x: 3,
                    y: 3,
                    width: 56,
                    height: 12,
                    font_size: 11,
                    bold: true,
                    align: "center",
                }),
                e("field", {
                    binding: "price",
                    x: 3,
                    y: 16,
                    width: 56,
                    height: 10,
                    font_size: 19,
                    bold: true,
                    align: "center",
                }),
                e("barcode", {
                    x: 6,
                    y: 28,
                    width: 50,
                    height: 9,
                    binding: "barcode",
                }),
            ],
        };
    return {
        version: 2,
        dpi: label ? 300 : 203,
        width,
        height: 120,
        auto_height: true,
        elements: [
            e("logo", { x: 4, y: 4, width: 14, height: 14 }),
            e("text", {
                text: "SUPER KAY",
                x: 20,
                y: 7,
                width: 38,
                height: 8,
                bold: true,
                font_size: 13,
                align: "center",
            }),
            e("field", {
                binding: "cash_box_text",
                x: 60,
                y: 7,
                width: 16,
                height: 7,
                font_size: 8,
                align: "right",
            }),
            e("text", {
                text: titles[kind],
                x: 4,
                y: 21,
                width: 72,
                height: 8,
                bold: true,
                align: "center",
            }),
            e("field", {
                binding: "folio",
                x: 4,
                y: 32,
                width: 30,
                height: 6,
                font_size: 9,
            }),
            e("field", {
                binding: "date",
                x: 35,
                y: 32,
                width: 41,
                height: 6,
                font_size: 8,
                align: "right",
            }),
            e("line", { x: 4, y: 41, width: 72, height: 1 }),
            e("items", { x: 4, y: 45, width: 72, height: 35, font_size: 9 }),
            e("field", {
                binding: kind === "closure" ? "counted_cash" : "total",
                text: kind === "closure" ? "CONTADO: " : "TOTAL: ",
                x: 4,
                y: 84,
                width: 72,
                height: 9,
                bold: true,
                font_size: 14,
                align: "right",
            }),
            e("text", {
                text: "Conserva este comprobante",
                x: 4,
                y: 103,
                width: 72,
                height: 8,
                font_size: 9,
                align: "center",
            }),
        ],
    };
}
export function sampleVisualData(kind, count = 3) {
    return {
        type:
            {
                statement: "employee_credit_statement",
                closure: "cash_closure",
                "sale-return": "sale_return",
                "credit-payment": "employee_credit_payment",
            }[kind] || "sale",
        name: "Café molido de la casa 250 g",
        price: 74.5,
        barcode: "7501234567890",
        category: "Abarrotes",
        sku: "CAF-250",
        stock: 12,
        branch: "Sucursal ejemplo",
        folio: "EJEMPLO-001",
        date: "15/10/2026 12:30",
        branch_name: "Sucursal ejemplo",
        user_name: "Usuario de ejemplo",
        cash_box_text: "CAJA #1",
        total: count * 74.5,
        cash_received: 500,
        change_due: 500 - count * 74.5,
        payment_method: "Efectivo",
        reason: "Producto devuelto",
        original_sale_folio: "V-000001",
        balance_before: 300,
        balance_after: 150,
        payment_amount: 150,
        counted_cash: 1450,
        cash_left: 500,
        cash_withdrawal: 950,
        sales_count: 10,
        notes: "Documento de ejemplo",
        items: Array.from({ length: count }, (_, i) => ({
            product_name: `${i + 1}. Producto de ejemplo`,
            quantity: 1,
            unit_price: 74.5,
            subtotal: 74.5,
        })),
        paid_charges: Array.from({ length: count }, (_, i) => ({
            folio: `V-${i + 1}`,
            amount: 74.5,
        })),
        credit_groups: [
            {
                label: "Compras pendientes",
                charges: Array.from({ length: count }, (_, i) => ({
                    folio: `V-${i + 1}`,
                    total: 74.5,
                    items: [
                        {
                            product: "Producto de ejemplo",
                            quantity_label: "1 pza",
                            subtotal: 74.5,
                        },
                    ],
                })),
            },
        ],
        denomination_breakdown: { 500: 2, 200: 2, 50: 1 },
    };
}
// Grow only repeating content. Elements below it move together, preserving gaps.
export function resolveVisualGeometry(layout, data, measure) {
    const elements = layout.elements
        .filter((e) => e.visible)
        .map((e) => ({ ...e, textValue: elementText(e, data) }));
    const growing = elements
        .filter((e) => e.type === "items")
        .map((e) => ({
            id: e.id,
            bottom: e.y + e.height,
            extra: Math.max(0, measure(e) - e.height),
        }));
    const resolved = elements.map((e) => ({
        ...e,
        y:
            e.y +
            growing
                .filter((g) => g.id !== e.id && e.y >= g.bottom)
                .reduce((n, g) => n + g.extra, 0),
        height: e.type === "items" ? Math.max(e.height, measure(e)) : e.height,
    }));
    const contentBottom = Math.max(0, ...resolved.map((e) => e.y + e.height));
    const height = layout.auto_height
        ? Math.max(layout.height, contentBottom + 4)
        : layout.height;
    return {
        elements: resolved,
        width: layout.width,
        height,
        overflow:
            contentBottom > height + 0.01 ||
            resolved.some(
                (e) =>
                    e.x < 0 || e.y < 0 || e.x + e.width > layout.width + 0.01,
            ),
    };
}
