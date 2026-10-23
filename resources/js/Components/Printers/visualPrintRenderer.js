import Konva from "konva";
import { elementText, resolveVisualGeometry } from "@/config/visualPrintLayout";
import { code128Svg } from "@/config/labelTemplate";

export const PX_PER_MM = 4;
const p = (value) => value * PX_PER_MM;
const textConfig = (e) => ({
    text: e.textValue ?? e.text,
    width: p(e.width),
    fontSize: (e.font_size * 25.4 * PX_PER_MM) / 72,
    fontFamily: "Arial",
    fontStyle: e.bold ? "bold" : "normal",
    align: e.align,
    lineHeight: 1.15,
    fill: e.fill,
    wrap: "word",
});
export function measureElement(e) {
    const node = new Konva.Text(textConfig(e));
    const h = node.height() / PX_PER_MM;
    node.destroy();
    return h;
}
function loadImage(src) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () =>
            reject(new Error("No se pudo cargar una imagen del diseño."));
        image.src = src;
    });
}
let logoPromise;
export async function visualAssets(layout, data) {
    const images = {};
    await Promise.all(
        layout.elements
            .filter((e) => e.visible)
            .map(async (e) => {
                if (e.type === "logo") {
                    logoPromise ||= loadImage(
                        "/icons/super-kay-ticket-bw.png",
                    ).catch((error) => {
                        logoPromise = null;
                        throw error;
                    });
                    images[e.id] = await logoPromise;
                }
                if (e.type === "barcode") {
                    const value = String(data[e.binding] ?? "");
                    if (!value || /[^\x20-\x7e]/.test(value))
                        throw new Error(
                            "El código de barras está vacío o contiene caracteres no compatibles.",
                        );
                    images[e.id] = await loadImage(
                        `data:image/svg+xml;charset=utf-8,${encodeURIComponent(code128Svg(value))}`,
                    );
                }
            }),
    );
    return images;
}
export function drawVisualStage(
    container,
    layout,
    data,
    images,
    {
        zoom = 1,
        grid = false,
        editing = false,
        onSelect = () => {},
        onChange = () => {},
        selectedId = null,
    } = {},
) {
    const geometry = resolveVisualGeometry(layout, data, measureElement);
    if (geometry.height > 2000)
        throw new Error(
            "El documento supera los 2 metros. Reduce el contenido o divide la impresión.",
        );
    const stage = new Konva.Stage({
        container,
        width: p(geometry.width) * zoom,
        height: p(geometry.height) * zoom,
        scaleX: zoom,
        scaleY: zoom,
    });
    const background = new Konva.Layer();
    stage.add(background);
    background.add(
        new Konva.Rect({
            width: p(geometry.width),
            height: p(geometry.height),
            fill: "white",
        }),
    );
    if (grid) {
        for (let x = 0; x < geometry.width; x += 5)
            background.add(
                new Konva.Line({
                    points: [p(x), 0, p(x), p(geometry.height)],
                    stroke: "#e5e7eb",
                    strokeWidth: 0.5,
                    listening: false,
                }),
            );
        for (let y = 0; y < geometry.height; y += 5)
            background.add(
                new Konva.Line({
                    points: [0, p(y), p(geometry.width), p(y)],
                    stroke: "#e5e7eb",
                    strokeWidth: 0.5,
                    listening: false,
                }),
            );
    }
    const layer = new Konva.Layer();
    stage.add(layer);
    for (const element of geometry.elements) {
        const base = layout.elements.find((e) => e.id === element.id);
        const dynamicOffset = element.y - base.y;
        const common = {
            id: element.id,
            x: p(element.x),
            y: p(element.y),
            width: p(element.width),
            height: p(element.height),
            draggable: editing && !element.locked,
        };
        let node;
        if (element.type === "logo" || element.type === "barcode")
            node = new Konva.Image({ ...common, image: images[element.id] });
        else if (element.type === "rect")
            node = new Konva.Rect({
                ...common,
                stroke: element.fill,
                strokeWidth: p(element.stroke),
                fillEnabled: false,
            });
        else if (element.type === "line")
            node = new Konva.Rect({
                ...common,
                height: Math.max(1, p(element.stroke)),
                fill: element.fill,
            });
        else
            node = new Konva.Text({
                ...common,
                ...textConfig(element),
                height: p(element.height),
                ellipsis: true,
            });
        node.on("click tap", () => onSelect(element.id));
        node.on("dragend transformend", (event) => {
            const width = Math.min(
                layout.width,
                Math.max(
                    1,
                    event.type === "dragend"
                        ? base.width
                        : (node.width() * node.scaleX()) / PX_PER_MM,
                ),
            );
            const height = Math.min(
                layout.height,
                Math.max(
                    1,
                    event.type === "dragend"
                        ? base.height
                        : base.height + element.height * (node.scaleY() - 1),
                ),
            );
            node.scale({ x: 1, y: 1 });
            onChange(element.id, {
                x: Math.max(
                    0,
                    Math.min(layout.width - width, node.x() / PX_PER_MM),
                ),
                y: Math.max(
                    0,
                    Math.min(
                        layout.height - height,
                        node.y() / PX_PER_MM - dynamicOffset,
                    ),
                ),
                width,
                height,
            });
        });
        layer.add(node);
    }
    if (editing) {
        const selected = layout.elements.find((e) => e.id === selectedId);
        const node = layer.findOne(`#${selectedId}`);
        if (node && !selected?.locked) {
            const transformer = new Konva.Transformer({
                nodes: [node],
                rotateEnabled: false,
                keepRatio: false,
                enabledAnchors: [
                    "top-left",
                    "top-right",
                    "bottom-left",
                    "bottom-right",
                    "middle-left",
                    "middle-right",
                ],
                borderStroke: "#2563eb",
                anchorStroke: "#2563eb",
                anchorFill: "#fff",
                anchorSize: 9,
                padding: 3,
                flipEnabled: false,
                boundBoxFunc: (oldBox, newBox) =>
                    newBox.width < 4 || newBox.height < 4 ? oldBox : newBox,
            });
            layer.add(transformer);
        }
        background.on("click tap", () => onSelect(null));
    }
    stage.draw();
    return { stage, geometry };
}
export async function renderVisualPrint(
    layout,
    data,
    { dpi = layout.dpi || 203 } = {},
) {
    const assets = await visualAssets(layout, data);
    const container = document.createElement("div");
    const { stage, geometry } = drawVisualStage(
        container,
        layout,
        data,
        assets,
    );
    try {
        if (geometry.overflow)
            throw new Error(
                "El contenido no cabe en el papel. Aumenta la longitud o activa el crecimiento automático.",
            );
        const base64 = stage
            .toDataURL({ pixelRatio: dpi / (25.4 * PX_PER_MM) })
            .split(",")[1];
        return { base64, width: geometry.width, height: geometry.height };
    } finally {
        stage.destroy();
        container.remove();
    }
}
