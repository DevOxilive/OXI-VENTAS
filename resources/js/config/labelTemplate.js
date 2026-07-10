const LABEL_BLOCK_CATALOG = [
  { key: "brand_title", label: "Marca", variable: "header_text" },
  { key: "product_name", label: "Nombre del producto", variable: "name" },
  { key: "category", label: "Categoria", variable: "category" },
  { key: "barcode", label: "Codigo de barras", variable: "barcode" },
  { key: "barcode_text", label: "Numero de codigo", variable: "barcode" },
  { key: "price", label: "Precio", variable: "price" },
  { key: "sku", label: "SKU / clave", variable: "sku" },
  { key: "stock", label: "Existencia", variable: "stock" },
  { key: "branch", label: "Sucursal", variable: "branch" },
  { key: "date", label: "Fecha", variable: "date" },
  { key: "custom_text", label: "Texto libre", variable: "custom_text" },
  { key: "footer_text", label: "Pie", variable: "footer_text" },
];
const DEFAULT_POSITIONS = {
  brand_title: 50,
  product_name: 50,
  category: 50,
  barcode: 50,
  barcode_text: 50,
  price: 50,
  sku: 50,
  stock: 50,
  branch: 50,
  date: 50,
  custom_text: 50,
  footer_text: 50,
};

const FIXED_LABEL_WIDTH_MM = 75;
const FIXED_LABEL_HEIGHT_MM = 29;

const DEFAULT_SIZES = {
  brand_title: 100,
  product_name: 800,
  category: 72,
  barcode: 100,
  barcode_text: 74,
  price: 800,
  sku: 72,
  stock: 72,
  branch: 72,
  date: 72,
  custom_text: 80,
  footer_text: 70,
};

const DEFAULT_ENABLED = {
  brand_title: false,
  product_name: true,
  category: false,
  barcode: false,
  barcode_text: false,
  price: true,
  sku: false,
  stock: false,
  branch: false,
  date: false,
  custom_text: false,
  footer_text: false,
};

const DEFAULT_TEMPLATE = {
  label_width_mm: FIXED_LABEL_WIDTH_MM,
  label_height_mm: FIXED_LABEL_HEIGHT_MM,
  print_engine: "visual",
  barcode_height_mm: 6,
  barcode_width_percent: 100,
  show_border: true,
  header_text: "SUPER KAY",
  footer_text: "Precio sujeto a cambio",
  custom_text: "",
  blocks: LABEL_BLOCK_CATALOG.map((block) => ({
    key: block.key,
    enabled: DEFAULT_ENABLED[block.key] !== false,
    position_percent: DEFAULT_POSITIONS[block.key] ?? 50,
    size_percent: DEFAULT_SIZES[block.key] ?? 100,
  })),
};

const LABEL_TEMPLATE_STORAGE_KEY = "product_label_template_settings";

export function getLabelBlockCatalog() {
  return LABEL_BLOCK_CATALOG.map((block) => ({ ...block }));
}

export function createDefaultLabelTemplate() {
  return JSON.parse(JSON.stringify(DEFAULT_TEMPLATE));
}

export function getStoredLabelTemplateSettings() {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    const raw = window.localStorage.getItem(LABEL_TEMPLATE_STORAGE_KEY);

    return raw ? normalizeLabelTemplate(JSON.parse(raw)) : null;
  } catch {
    return null;
  }
}

export function saveStoredLabelTemplateSettings(settings) {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(
    LABEL_TEMPLATE_STORAGE_KEY,
    JSON.stringify(normalizeLabelTemplate(settings))
  );
}

export function normalizeLabelTemplate(input = {}) {
  const base = createDefaultLabelTemplate();
  const incomingBlocks = Array.isArray(input.blocks) ? input.blocks : [];
  const normalizedBlocks = [];

  incomingBlocks.forEach((block) => {
    const catalogItem = LABEL_BLOCK_CATALOG.find((item) => item.key === block.key);

    if (!catalogItem) {
      return;
    }

    normalizedBlocks.push({
      key: catalogItem.key,
      enabled: block.enabled !== false,
      position_percent: clampNumber(block.position_percent, DEFAULT_POSITIONS[catalogItem.key] ?? 50, 0, 100),
     size_percent: clampNumber(block.size_percent, DEFAULT_SIZES[catalogItem.key] ?? 100, 50, 2000),
    });
  });

  LABEL_BLOCK_CATALOG.forEach((catalogItem) => {
    if (normalizedBlocks.some((block) => block.key === catalogItem.key)) {
      return;
    }

    normalizedBlocks.push({
      key: catalogItem.key,
      enabled: DEFAULT_ENABLED[catalogItem.key] !== false,
      position_percent: DEFAULT_POSITIONS[catalogItem.key] ?? 50,
      size_percent: DEFAULT_SIZES[catalogItem.key] ?? 100,
    });
  });

  return {
    label_width_mm: FIXED_LABEL_WIDTH_MM,
    label_height_mm: FIXED_LABEL_HEIGHT_MM,
    print_engine: "visual",
    barcode_height_mm: clampNumber(input.barcode_height_mm, base.barcode_height_mm, 6, 28),
    barcode_width_percent: clampNumber(input.barcode_width_percent, base.barcode_width_percent, 45, 100),
    show_border: Boolean(input.show_border),
    header_text: nonBlankText(input.header_text, base.header_text),
    footer_text: nonBlankText(input.footer_text, base.footer_text),
    custom_text: String(input.custom_text ?? ""),
    blocks: normalizedBlocks,
  };
}

export function reorderLabelBlocks(blocks, fromIndex, toIndex) {
  if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0 || fromIndex >= blocks.length || toIndex >= blocks.length) {
    return blocks.map((block) => ({ ...block }));
  }

  const clone = blocks.map((block) => ({ ...block }));
  const [moved] = clone.splice(fromIndex, 1);
  clone.splice(toIndex, 0, moved);
  return clone;
}

export function buildLabelPreviewBlocks(template, product) {
  const resolved = normalizeLabelTemplate(template);

  return resolved.blocks
    .filter((block) => block.enabled)
    .map((block) => ({
      key: block.key,
      label: LABEL_BLOCK_CATALOG.find((item) => item.key === block.key)?.label || block.key,
      position_percent: block.position_percent,
      size_percent: block.size_percent,
      row: buildRowForBlock(resolved, product, block),
    }))
    .filter((block) => block.row);
}
function blockFontScale(row) {
  if (row.block_key === "price") return 14;
  if (row.block_key === "product_name") return 10.5;
  return 8.5;
}

function requestedRowHeightMm(row) {
  if (row.type === "barcode") {
    return Math.max(8, Number(row.height_mm || 6));
  }

  const scale = blockFontScale(row);
  const fontPx = Math.max(8, (Number(row.size_percent || 100) / 100) * scale);

  // conversión aproximada de px a mm
  const fontMm = fontPx * 0.2646;

  if (row.block_key === "product_name") {
    return Math.max(6, fontMm * 2.2); // permite hasta dos líneas
  }

  if (row.block_key === "price") {
    return Math.max(8, fontMm * 1.7);
  }

  return Math.max(4, fontMm * 1.5);
}

function resolveDynamicLabelHeightMm(template, product) {
  const resolved = normalizeLabelTemplate(template);
  const rows = buildLabelPreviewBlocks(resolved, product).map((block) => block.row);

  if (!rows.length) {
    return resolved.label_height_mm;
  }

  const topBottomPaddingMm = 4;
  const gapMm = 0.8;

  const contentHeightMm = rows.reduce((sum, row) => {
    return sum + requestedRowHeightMm(row);
  }, 0);

  const totalHeightMm = contentHeightMm + topBottomPaddingMm + (Math.max(0, rows.length - 1) * gapMm);

  return Math.max(resolved.label_height_mm, Math.ceil(totalHeightMm));
}export function resolveLabelPrintDimensions(template) {
  const resolved = normalizeLabelTemplate(template);

  return {
    width: resolved.label_width_mm,
    height: resolved.label_height_mm,
  };
}

export function buildLabelHtmlMarkup(template, product) {
  const resolved = normalizeLabelTemplate(template);
const printDimensions = resolveLabelPrintDimensions(resolved, product);
  const blocks = buildLabelPreviewBlocks(resolved, product);
  const rowsMarkup = blocks.map((block) => rowHtml(block.row)).join("");

  return `
    <html>
      <head>
        <meta charset="utf-8">
        <style>
          @page {
            size: ${printDimensions.width}mm ${printDimensions.height}mm;
            margin: 0;
          }

          html, body {
            width: ${printDimensions.width}mm;
            height: ${printDimensions.height}mm;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background: #ffffff;
            color: #000000;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }

          body {
            position: fixed;
            inset: 0;
          }
        </style>
      </head>
      <body>
        <div style="width:100%; height:100%; box-sizing:border-box; padding:1.6mm 2mm; overflow:hidden; display:flex; flex-direction:column; justify-content:center; border:${resolved.show_border ? "0.25mm solid #000" : "0"};">
          ${rowsMarkup}
        </div>
      </body>
    </html>
  `;
}

export function buildLabelPngBase64(template, product, dpi = 300) {
  const resolved = normalizeLabelTemplate(template);
const dimensions = resolveLabelPrintDimensions(resolved);
  const pixelsPerMm = dpi / 25.4;

  const width = Math.max(1, Math.round(dimensions.width * pixelsPerMm));
  const height = Math.max(1, Math.round(dimensions.height * pixelsPerMm));

  const canvas = document.createElement("canvas");
  const context = canvas.getContext("2d");
  const rows = buildLabelPreviewBlocks(resolved, product).map((block) => block.row);

const borderWidth = Math.max(2, Math.round(0.25 * pixelsPerMm));

// margen seguro, pero no exagerado
const safeInsetX = Math.round(1.2 * pixelsPerMm);
const safeInsetY = Math.round(1.2 * pixelsPerMm);

const frameLeft = safeInsetX;
const frameTop = safeInsetY;
const frameWidth = Math.max(1, width - safeInsetX * 2);
const frameHeight = Math.max(1, height - safeInsetY * 2);

// padding interno más pequeño para que el diseño use más espacio
const innerPaddingX = Math.round(0.6 * pixelsPerMm);
const innerPaddingY = Math.round(0.4 * pixelsPerMm);

const contentLeft = frameLeft + innerPaddingX;
const contentTop = frameTop + innerPaddingY;
const contentWidth = Math.max(1, frameWidth - innerPaddingX * 2);
const availableHeight = Math.max(1, frameHeight - innerPaddingY * 2);
  const requestedHeights = rows.map((row) => (
    row.type === "barcode"
      ? row.height_mm * pixelsPerMm
      : Math.max(24, (row.size_percent / 100) * 34)
  ));

  const totalRequestedHeight = requestedHeights.reduce((sum, value) => sum + value, 0);
  const heightScale = Math.min(1.35, availableHeight / Math.max(1, totalRequestedHeight));

  canvas.width = width;
  canvas.height = height;

  context.fillStyle = "#ffffff";
  context.fillRect(0, 0, width, height);

  context.fillStyle = "#000000";
  context.imageSmoothingEnabled = false;

  if (resolved.show_border) {
    context.lineWidth = borderWidth;
    context.strokeRect(frameLeft, frameTop, frameWidth, frameHeight);
  }

  if (rows.length > 0 && rows.every((row) => row.type !== "barcode")) {
    drawTextOnlyLabelRows(context, rows, {
      contentLeft,
      contentTop,
      contentWidth,
      availableHeight,
    });

    return canvas.toDataURL("image/png").split(",")[1];
  }

  let y = contentTop + Math.max(0, (availableHeight - totalRequestedHeight * heightScale) / 2);

  rows.forEach((row, index) => {
    const rowHeight = requestedHeights[index] * heightScale;

    if (row.type === "barcode") {
      drawBarcodeOnCanvas(
        context,
        row.value,
        alignedX(
          contentLeft,
          contentWidth,
          contentWidth * (row.width_percent / 100),
          row.position_percent
        ),
        y,
        contentWidth * (row.width_percent / 100),
        rowHeight
      );
    } else {
      const text = row.type === "pair"
        ? `${cleanText(row.label)}: ${cleanText(row.value)}`
        : cleanText(row.text);

      let fontSize = Math.max(12, rowHeight * 0.82);

      context.font = `${row.bold ? 800 : 600} ${fontSize}px Arial, sans-serif`;

      while (fontSize > 8 && context.measureText(text).width > contentWidth) {
        fontSize -= 1;
        context.font = `${row.bold ? 800 : 600} ${fontSize}px Arial, sans-serif`;
      }

      context.textBaseline = "middle";
      context.textAlign = canvasTextAlign(row.position_percent);

      context.fillText(
        text,
        textX(contentLeft, contentWidth, row.position_percent),
        y + rowHeight / 2,
        contentWidth
      );
    }

    y += rowHeight;
  });

  return canvas.toDataURL("image/png").split(",")[1];
}
function drawTextOnlyLabelRows(context, rows, bounds) {
  const priceRow = rows.find((row) => row.block_key === "price");
  const textRows = rows.filter((row) => row.block_key !== "price");

  const gap = Math.max(1, Math.round(bounds.availableHeight * 0.015));

  const priceHeight = priceRow
    ? Math.round(bounds.availableHeight * 0.50)
    : 0;

  const textHeight = priceRow
    ? Math.max(1, bounds.availableHeight - priceHeight - gap)
    : bounds.availableHeight;

  let y = bounds.contentTop;

  textRows.forEach((row) => {
    const rowHeight = textHeight / Math.max(1, textRows.length);
    const text = row.type === "pair"
      ? `${cleanText(row.label)}: ${cleanText(row.value)}`
      : cleanText(row.text);

  drawFreeTextBlock(context, text, {
  x: textX(bounds.contentLeft, bounds.contentWidth, row.position_percent),
  clipX: bounds.contentLeft,
  y,
  width: bounds.contentWidth,
  height: rowHeight,
  align: canvasTextAlign(row.position_percent),
  bold: row.bold,
  fontSize: (row.size_percent / 100) * (
    row.block_key === "product_name" ? 10.5 : 8.5
  ),
  maxLines: row.block_key === "product_name" ? 2 : 1,
});

    y += rowHeight;
  });

  if (priceRow) {
    const text = priceRow.type === "pair"
      ? `${cleanText(priceRow.label)}: ${cleanText(priceRow.value)}`
      : cleanText(priceRow.text);
drawFreeTextBlock(context, text, {
  x: textX(bounds.contentLeft, bounds.contentWidth, priceRow.position_percent),
  clipX: bounds.contentLeft,
  y: bounds.contentTop + bounds.availableHeight - priceHeight,
  width: bounds.contentWidth,
  height: priceHeight,
  align: canvasTextAlign(priceRow.position_percent),
  bold: true,
  fontSize: (priceRow.size_percent / 100) * 14,
  maxLines: 1,
});
  }
}
function drawFreeTextBlock(context, text, options) {
  const fontSize = Math.max(8, options.fontSize);
  const lineHeight = fontSize * 0.9;

  context.save();

  context.beginPath();
  context.rect(options.clipX ?? 0, options.y, options.width, options.height);
  context.clip();

  context.font = `${options.bold ? 900 : 700} ${fontSize}px Arial, sans-serif`;
  context.textBaseline = "middle";
  context.textAlign = options.align;

  const lines = wrapCanvasText(context, text, options.width, options.maxLines)
    .slice(0, options.maxLines);

  const totalHeight = lines.length * lineHeight;
  const startY = options.y + (options.height - totalHeight) / 2 + lineHeight / 2;

  lines.forEach((line, index) => {
    context.fillText(line, options.x, startY + index * lineHeight);
  });

  context.restore();
}

function wrapCanvasText(context, text, maxWidth, maxLines = 1) {
  const clean = cleanText(text);
  const words = clean.split(" ").filter(Boolean);

  if (!clean) {
    return [""];
  }

  // Si cabe en una sola línea, no lo parte.
  if (context.measureText(clean).width <= maxWidth) {
    return [clean];
  }

  // Si solo se permite una línea, se queda en una línea.
  if (maxLines <= 1 || words.length <= 1) {
    return [clean];
  }

  const lines = [];
  let currentLine = "";

  words.forEach((word) => {
    const nextLine = currentLine ? `${currentLine} ${word}` : word;

    if (context.measureText(nextLine).width <= maxWidth || !currentLine) {
      currentLine = nextLine;
      return;
    }

    lines.push(currentLine);
    currentLine = word;
  });

  if (currentLine) {
    lines.push(currentLine);
  }

  if (lines.length <= maxLines) {
    return lines;
  }

  return [
    ...lines.slice(0, maxLines - 1),
    lines.slice(maxLines - 1).join(" "),
  ];
}
function splitTextIntoBalancedLines(context, words, maxWidth) {
  let bestLines = [words.join(" ")];
  let bestScore = Number.POSITIVE_INFINITY;

  for (let index = 1; index < words.length; index += 1) {
    const firstLine = words.slice(0, index).join(" ");
    const secondLine = words.slice(index).join(" ");
    const firstWidth = context.measureText(firstLine).width;
    const secondWidth = context.measureText(secondLine).width;
    const overflow = Math.max(0, firstWidth - maxWidth) + Math.max(0, secondWidth - maxWidth);
    const balance = Math.abs(firstWidth - secondWidth);
    const score = overflow * 100 + balance;

    if (score < bestScore) {
      bestScore = score;
      bestLines = [firstLine, secondLine];
    }
  }

  return bestLines;
}

function buildRowForBlock(template, product, block) {
  const common = {
    block_key: block.key,
    position_percent: block.position_percent,
    size_percent: block.size_percent,
  };

  switch (block.key) {
    case "brand_title":
      return { ...common, type: "text", text: template.header_text, bold: true };
    case "product_name":
      return { ...common, type: "text", text: product.name, bold: true };
    case "category":
      return { ...common, type: "text", text: product.category };
    case "barcode":
      return {
        ...common,
        type: "barcode",
        value: product.barcode,
        height_mm: template.barcode_height_mm,
        width_percent: template.barcode_width_percent,
      };
    case "barcode_text":
      return { ...common, type: "text", text: product.barcode };
    case "price":
      return { ...common, type: "text", text: currency(product.price), bold: true };
    case "sku":
      return { ...common, type: "pair", label: "SKU", value: product.sku };
    case "stock":
      return { ...common, type: "pair", label: "Stock", value: product.stock };
    case "branch":
      return { ...common, type: "pair", label: "Sucursal", value: product.branch };
    case "date":
      return { ...common, type: "pair", label: "Fecha", value: product.date };
    case "custom_text":
      return { ...common, type: "text", text: template.custom_text };
    case "footer_text":
      return { ...common, type: "text", text: template.footer_text };
    default:
      return null;
  }
}

function rowHtml(row) {
  const align = alignFromPercent(row.position_percent);
  const fontSize = fontSizePx(row.size_percent);
  const margin = row.type === "barcode" ? "0.4mm 0 0.2mm" : "0.15mm 0";

  if (row.type === "barcode") {
    return `
      <div style="display:flex; justify-content:${align}; margin:${margin}; line-height:1;">
        ${barcodeMarkup(row.value, row.width_percent, row.height_mm)}
      </div>
    `;
  }

  const text = row.type === "pair"
    ? `${cleanText(row.label)}: ${cleanText(row.value)}`
    : cleanText(row.text);

  return `
    <div style="text-align:${align}; margin:${margin}; font-size:${fontSize}px; line-height:1.05; font-weight:${row.bold ? 800 : 600}; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
      ${escapeHtml(text)}
    </div>
  `;
}

function barcodeMarkup(value, widthPercent = 82, heightMm = 11) {
  return `
    <div style="width:${widthPercent}%; height:${heightMm}mm; display:flex; align-items:stretch; justify-content:center; background:#fff;">
      ${code128Svg(value)}
    </div>
  `;
}

function code128Svg(value) {
  const text = cleanBarcodeText(value);
  const codes = [104];

  Array.from(text).forEach((char) => {
    codes.push(char.charCodeAt(0) - 32);
  });

  const checksum = codes.reduce((sum, code, index) => (
    index === 0 ? code : sum + code * index
  ), 0) % 103;
  const resolvedCodes = [...codes, checksum, 106];
  const modules = resolvedCodes.map((code) => CODE_128_PATTERNS[code]).join("");
  const width = modules.split("").reduce((sum, module) => sum + Number(module), 0);
  let x = 0;

  const rects = modules
    .split("")
    .map((module, index) => {
      const moduleWidth = Number(module);
      const rect = index % 2 === 0
        ? `<rect x="${x}" y="0" width="${moduleWidth}" height="60" fill="#000" />`
        : "";

      x += moduleWidth;
      return rect;
    })
    .join("");

  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} 60" preserveAspectRatio="none" style="display:block; width:100%; height:100%;">${rects}</svg>`;
}

function drawBarcodeOnCanvas(context, value, x, y, width, height) {
  const text = cleanBarcodeText(value);
  const codes = [104, ...Array.from(text).map((char) => char.charCodeAt(0) - 32)];
  const checksum = codes.reduce((sum, code, index) => (
    index === 0 ? code : sum + code * index
  ), 0) % 103;
  const modules = [...codes, checksum, 106]
    .map((code) => CODE_128_PATTERNS[code])
    .join("")
    .split("")
    .map(Number);
  const moduleTotal = modules.reduce((sum, moduleWidth) => sum + moduleWidth, 0);
  const scale = width / moduleTotal;
  let cursor = x;

  modules.forEach((moduleWidth, index) => {
    const nextCursor = cursor + moduleWidth * scale;

    if (index % 2 === 0) {
      context.fillRect(Math.round(cursor), Math.round(y), Math.max(1, Math.round(nextCursor) - Math.round(cursor)), Math.max(1, Math.round(height)));
    }

    cursor = nextCursor;
  });
}

function textX(left, width, positionPercent) {
  if (Number(positionPercent) <= 25) return left;
  if (Number(positionPercent) >= 75) return left + width;
  return left + width / 2;
}

function canvasTextAlign(positionPercent) {
  if (Number(positionPercent) <= 25) return "left";
  if (Number(positionPercent) >= 75) return "right";
  return "center";
}

function alignedX(left, availableWidth, elementWidth, positionPercent) {
  if (Number(positionPercent) <= 25) return left;
  if (Number(positionPercent) >= 75) return left + availableWidth - elementWidth;
  return left + (availableWidth - elementWidth) / 2;
}

function clampNumber(value, fallback, min, max) {
  const numeric = Number(value);

  if (!Number.isFinite(numeric)) {
    return fallback;
  }

  return Math.max(min, Math.min(max, Math.round(numeric)));
}

function nonBlankText(value, fallback) {
  const text = String(value ?? "").trim();

  return text === "" ? fallback : text;
}

function currency(value) {
  const amount = Number(value || 0);

  return `$${amount.toFixed(2)}`;
}

function fontSizePx(sizePercent = 100) {
  return Math.max(7, Math.round((Number(sizePercent || 100) / 100) * 12));
}

function alignFromPercent(positionPercent = 50) {
  const position = Number(positionPercent || 0);

  if (position <= 25) return "left";
  if (position >= 75) return "right";
  return "center";
}

function cleanText(value) {
  return String(value ?? "")
    .replace(/\s+/g, " ")
    .trim();
}

function cleanBarcodeText(value) {
  const text = cleanText(value).replace(/[^\x20-\x7E]/g, "");

  return text || "0000000000000";
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

const CODE_128_PATTERNS = [
  "212222", "222122", "222221", "121223", "121322", "131222", "122213", "122312", "132212", "221213",
  "221312", "231212", "112232", "122132", "122231", "113222", "123122", "123221", "223211", "221132",
  "221231", "213212", "223112", "312131", "311222", "321122", "321221", "312212", "322112", "322211",
  "212123", "212321", "232121", "111323", "131123", "131321", "112313", "132113", "132311", "211313",
  "231113", "231311", "112133", "112331", "132131", "113123", "113321", "133121", "313121", "211331",
  "231131", "213113", "213311", "213131", "311123", "311321", "331121", "312113", "312311", "332111",
  "314111", "221411", "431111", "111224", "111422", "121124", "121421", "141122", "141221", "112214",
  "112412", "122114", "122411", "142112", "142211", "241211", "221114", "413111", "241112", "134111",
  "111242", "121142", "121241", "114212", "124112", "124211", "411212", "421112", "421211", "212141",
  "214121", "412121", "111143", "111341", "131141", "114113", "114311", "411113", "411311", "113141",
  "114131", "311141", "411131", "211412", "211214", "211232", "2331112",
];
