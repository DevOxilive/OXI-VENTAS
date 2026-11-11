// Widths are physical media sizes; ticket canvas widths use the documented
// Epson TM-T20III printable area as a reference, not a universal device limit.
export const PRINT_PAPER_FORMATS = [
    {
        id: "receipt-80",
        kind: "ticket",
        label: "Rollo de tickets · 80 mm",
        paperWidth: 80,
        width: 72,
        continuous: true,
        note: "Rollo de 80 mm. Área de diseño de referencia: 72 mm (Epson TM-T20III). Confirma el área imprimible de tu modelo.",
    },
    {
        id: "receipt-58",
        kind: "ticket",
        label: "Rollo de tickets · 58 mm",
        paperWidth: 58,
        width: 52.5,
        continuous: true,
        note: "Rollo de 58 mm. Área de diseño de referencia: 52.5 mm (Epson TM-T20III). Confirma el área imprimible de tu modelo.",
    },
    {
        id: "continuous-62",
        kind: "label",
        label: "Cinta blanca continua · 62 mm (Brother)",
        paperWidth: 62,
        width: 62,
        continuous: true,
        note: "Cinta blanca continua de 62 mm. No tiene un largo fijo: se corta según el diseño. Los márgenes imprimibles dependen del controlador; selecciona 62 mm en sus preferencias.",
    },
];
export function paperFormatsFor(kind) {
    return PRINT_PAPER_FORMATS.filter(
        (format) => format.kind === (kind === "label" ? "label" : "ticket"),
    );
}
export function applyPaperFormat(layout, id) {
    const next = JSON.parse(JSON.stringify(layout));
    const format = PRINT_PAPER_FORMATS.find((format) => format.id === id);
    next.paper_format = format?.id || "custom";
    if (!format) return next;
    const ratio = format.width / next.width;
    next.elements.forEach((element) => {
        element.width = Math.min(
            format.width,
            Math.max(1, Math.round(element.width * ratio * 100) / 100),
        );
        element.x = Math.max(
            0,
            Math.min(
                format.width - element.width,
                Math.round(element.x * ratio * 100) / 100,
            ),
        );
    });
    next.width = format.width;
    next.auto_height = format.continuous;
    return next;
}
