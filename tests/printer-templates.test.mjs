import test from "node:test";
import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import {
    normalizeLabelTemplate,
    resolveLabelPrintDimensions,
    buildLabelPngBase64,
} from "../resources/js/config/labelTemplate.js";
import {
    normalizeTicketTemplate,
    buildEscPosTicketData,
} from "../resources/js/config/ticketTemplate.js";

test("label sizes survive normalization without model-specific overrides", () => {
    for (const [width, height] of [
        [62, 29],
        [50, 30],
        [100, 150],
    ]) {
        const template = normalizeLabelTemplate({
            label_width_mm: width,
            label_height_mm: height,
        });
        assert.deepEqual(resolveLabelPrintDimensions(template), {
            width,
            height,
        });
        assert.deepEqual(normalizeLabelTemplate(template), template);
    }
});

test("ticket width, feed and separators affect the generated commands", () => {
    for (const width of [32, 48, 56]) {
        const settings = normalizeTicketTemplate({
            paper_width: width,
            feed_lines: 4,
            show_dividers: false,
            auto_cut: false,
            open_cash_drawer: false,
            blocks: [{ key: "logo", enabled: false }],
        });
        assert.equal(settings.paper_width, width);
        assert.equal(settings.feed_lines, 4);
        const output = buildEscPosTicketData(settings, {
            items: [],
            folio: "V-1",
            date: "15/10/2026",
        }).join("");
        assert.ok(!output.includes(".".repeat(width)));
        assert.ok(output.endsWith("\n".repeat(4)));
        assert.ok(!output.includes("\x1B\x70"));
    }
});

test("PNG respects paper dimensions and configured price/name order", () => {
    const drawn = [];
    const context = {
        fillRect() {},
        strokeRect() {},
        save() {},
        restore() {},
        beginPath() {},
        rect() {},
        clip() {},
        measureText(text) {
            return { width: text.length * 5 };
        },
        fillText(text) {
            drawn.push(text);
        },
    };
    const canvas = {
        getContext: () => context,
        toDataURL: () => "data:image/png;base64,TEST",
    };
    globalThis.document = { createElement: () => canvas };
    try {
        const template = normalizeLabelTemplate({
            label_width_mm: 62,
            label_height_mm: 29,
            blocks: [
                { key: "price", enabled: true, size_percent: 200 },
                { key: "product_name", enabled: true, size_percent: 200 },
            ],
        });
        assert.equal(
            buildLabelPngBase64(template, { name: "Producto", price: 10 }),
            "TEST",
        );
        assert.equal(canvas.width, Math.round((62 * 300) / 25.4));
        assert.equal(canvas.height, Math.round((29 * 300) / 25.4));
        assert.ok(drawn.indexOf("$10.00") < drawn.indexOf("Producto"));
    } finally {
        delete globalThis.document;
    }
});

test("label transport sends selected dimensions for every printer name", async () => {
    const sent = [];
    globalThis.__qzMock = {
        security: {
            setCertificatePromise() {},
            setSignatureAlgorithm() {},
            setSignaturePromise() {},
        },
        websocket: { isActive: () => true },
        configs: { create: (name, options) => ({ name, ...options }) },
        print: async (config, payload) => {
            sent.push({ config, payload });
        },
    };
    try {
        const source = (
            await readFile(
                new URL(
                    "../resources/js/Composables/useQzTray.js",
                    import.meta.url,
                ),
                "utf8",
            )
        ).replace(
            'import qz from "qz-tray";',
            "const qz = globalThis.__qzMock;",
        );
        const { printImageLabel } = await import(
            `data:text/javascript;base64,${Buffer.from(source).toString("base64")}`
        );
        for (const name of ["Brother QL-1110NWB", "Otra impresora"]) {
            await printImageLabel(name, "TEST", { width: 100, height: 150 }, 3);
        }
        assert.equal(sent.length, 2);
        for (const job of sent) {
            assert.deepEqual(job.config.size, { width: 100, height: 150 });
            assert.equal(job.config.copies, 3);
            assert.equal(job.payload[0].data, "TEST");
        }
        await assert.rejects(
            printImageLabel("Otra", "TEST", { width: NaN, height: 29 }),
            /válidos/,
        );
    } finally {
        delete globalThis.__qzMock;
    }
});

test("ticket header follows the selected width and configured brand", async () => {
    const { renderTicketHeaderImage } =
        await import("../resources/js/config/ticketTemplate.js");
    const texts = [];
    const context = {
        fillRect() {},
        scale() {},
        drawImage() {},
        fillText(text) {
            texts.push(text);
        },
    };
    const canvas = {
        getContext: () => context,
        toDataURL: () => "data:image/png;base64,HEADER",
    };
    globalThis.document = { createElement: () => canvas };
    try {
        renderTicketHeaderImage(
            { paper_width: 32, header_text: "MI TIENDA" },
            {},
            "CAJA #2",
        );
        assert.equal(canvas.width, 384);
        assert.ok(texts.includes("MI TIENDA"));
        renderTicketHeaderImage({ paper_width: 48 }, {}, "CAJA #2");
        assert.equal(canvas.width, 576);
    } finally {
        delete globalThis.document;
    }
});

test("saving a closure template cannot overwrite the sales fallback", async () => {
    const {
        saveStoredTicketTemplateSettings,
        getStoredTicketTemplateSettings,
    } = await import("../resources/js/config/ticketTemplate.js");
    const store = new Map();
    globalThis.window = {
        localStorage: {
            getItem: (key) => store.get(key) || null,
            setItem: (key, value) => store.set(key, value),
        },
    };
    try {
        saveStoredTicketTemplateSettings(
            { header_text: "VENTA" },
            "sales-ticket",
        );
        saveStoredTicketTemplateSettings(
            { header_text: "CORTE" },
            "cash-closure-ticket",
        );
        assert.equal(getStoredTicketTemplateSettings().header_text, "VENTA");
        assert.equal(
            getStoredTicketTemplateSettings("cash-closure-ticket").header_text,
            "CORTE",
        );
    } finally {
        delete globalThis.window;
    }
});

test("document print jobs use their own design instead of the page template", () => {
    const job = {
        type: "sale_return",
        original_sale_folio: "V-1",
        reason: "Cambio",
        items: [],
        total: 10,
        template_settings: {
            paper_width: 32,
            header_text: "DEVOLUCIONES",
            footer_text: "PIE DEVOLUCION",
            open_cash_drawer: false,
            blocks: [{ key: "logo", enabled: false }],
        },
    };
    const output = buildEscPosTicketData(
        { footer_text: "PIE VENTA", open_cash_drawer: true },
        job,
    ).join("");
    assert.ok(output.includes("PIE DEVOLUCION"));
    assert.ok(!output.includes("PIE VENTA"));
    assert.ok(!output.includes("\x1B\x70"));
});

test("printer menu has setup, tickets and labels with permission-aware entry", async () => {
    const { generateMenu } =
        await import("../resources/js/config/menuConfig.js");
    globalThis.route = (name, params) => `/route/${name}/${params?.kind || ""}`;
    try {
        const menu = generateMenu("", [
            "systems.qz.setup",
            "systems.tickets.view",
            "systems.cash-closure-tickets.view",
            "systems.labels.view",
        ]);
        assert.deepEqual(
            menu
                .find((item) => item.key === "printers")
                .children.map((item) => item.text),
            ["Configurar impresoras", "Tickets", "Etiquetas"],
        );
        const limited = generateMenu("", [
            "systems.cash-closure-tickets.view",
        ]).find((item) => item.key === "printers");
        assert.equal(limited.children.length, 1);
        assert.ok(limited.children[0].url.includes("cash-closure-tickets"));
    } finally {
        delete globalThis.route;
    }
});
