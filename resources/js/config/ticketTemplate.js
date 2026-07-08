const BLOCK_CATALOG = [
  { key: "cash_box", label: "Caja #" },
  { key: "brand_title", label: "Marca" },
  { key: "divider_header", label: "Linea despues de marca" },
  { key: "folio", label: "Folio" },
  { key: "date", label: "Fecha" },
  { key: "divider_folio", label: "Linea despues de folio/fecha" },
  { key: "document_title", label: "Titulo" },
  { key: "seller_user", label: "Usuario / Atendio" },
  { key: "payment_method", label: "Pago" },
  { key: "divider_items", label: "Linea antes de articulos" },
  { key: "items", label: "Articulos" },
  { key: "divider_totals", label: "Linea antes de totales" },
  { key: "totals", label: "Totales" },
  { key: "divider_footer", label: "Linea antes del pie" },
  { key: "footer_text", label: "Pie" },
];

const DEFAULT_POSITIONS = {
  cash_box: 100,
  brand_title: 50,
  divider_header: 0,
  folio: 0,
  date: 0,
  divider_folio: 0,
  document_title: 50,
  seller_user: 0,
  payment_method: 0,
  divider_items: 0,
  items: 0,
  divider_totals: 0,
  totals: 0,
  divider_footer: 0,
  footer_text: 50,
};

const DEFAULT_SIZES = {
  cash_box: 104,
  brand_title: 118,
  divider_header: 90,
  folio: 104,
  date: 104,
  divider_folio: 90,
  document_title: 104,
  seller_user: 90,
  payment_method: 90,
  divider_items: 90,
  items: 90,
  divider_totals: 90,
  totals: 100,
  divider_footer: 90,
  footer_text: 104,
};

const DEFAULT_TEMPLATE = {
  paper_width: 48,
  print_engine: "raw",
  feed_lines: 1,
  auto_cut: true,
  open_cash_drawer: true,
  header_text: "SUPER KAY",
  subheader_text: "TICKET DE VENTA",
  cash_box_text: "CAJA",
  footer_text: "Gracias por tu compra",
  show_dividers: true,
  blocks: BLOCK_CATALOG.map((block) => ({
    key: block.key,
    enabled: ![
      "payment_method",
    ].includes(block.key),
    position_percent: DEFAULT_POSITIONS[block.key] ?? 0,
    size_percent: DEFAULT_SIZES[block.key] ?? 100,
  })),
};

const DEFAULT_ENABLED = Object.fromEntries(
  DEFAULT_TEMPLATE.blocks.map((block) => [block.key, block.enabled])
);

const TICKET_TEMPLATE_STORAGE_KEY = "ticket_template_settings";

export function getTicketBlockCatalog() {
  return BLOCK_CATALOG.map((block) => ({ ...block }));
}

export function createDefaultTicketTemplate() {
  return JSON.parse(JSON.stringify(DEFAULT_TEMPLATE));
}

export function getStoredTicketTemplateSettings() {
  if (typeof window === "undefined") {
    return null;
  }

  try {
    const raw = window.localStorage.getItem(TICKET_TEMPLATE_STORAGE_KEY);

    if (!raw) {
      return null;
    }

    return normalizeTicketTemplate(JSON.parse(raw));
  } catch (error) {
    return null;
  }
}

export function saveStoredTicketTemplateSettings(settings) {
  if (typeof window === "undefined") {
    return;
  }

  window.localStorage.setItem(
    TICKET_TEMPLATE_STORAGE_KEY,
    JSON.stringify(normalizeTicketTemplate(settings))
  );
}

export function normalizeTicketTemplate(input = {}) {
  const base = createDefaultTicketTemplate();
  const incomingBlocks = Array.isArray(input.blocks) ? input.blocks : [];

  const normalizedBlocks = [];

  incomingBlocks.forEach((block) => {
    const catalogItem = BLOCK_CATALOG.find((item) => item.key === block.key);

    if (!catalogItem) {
      return;
    }

    normalizedBlocks.push({
      key: catalogItem.key,
      enabled: block.enabled !== false,
      position_percent: forcedBlockPosition(catalogItem.key, clampPercent(block.position_percent, DEFAULT_POSITIONS[catalogItem.key] ?? 0, 0, 100)),
      size_percent: clampPercent(block.size_percent, DEFAULT_SIZES[catalogItem.key] ?? 100, 60, 180),
    });
  });

  BLOCK_CATALOG.forEach((catalogItem) => {
    if (normalizedBlocks.some((block) => block.key === catalogItem.key)) {
      return;
    }

    normalizedBlocks.push({
      key: catalogItem.key,
      enabled: DEFAULT_ENABLED[catalogItem.key] !== false,
      position_percent: forcedBlockPosition(catalogItem.key, DEFAULT_POSITIONS[catalogItem.key] ?? 0),
      size_percent: DEFAULT_SIZES[catalogItem.key] ?? 100,
    });
  });

  normalizedBlocks.sort((left, right) => (
    BLOCK_CATALOG.findIndex((item) => item.key === left.key)
    - BLOCK_CATALOG.findIndex((item) => item.key === right.key)
  ));

  return {
    paper_width: 48,
    print_engine: "raw",
    feed_lines: 1,
    auto_cut: Boolean(input.auto_cut),
    open_cash_drawer: input.open_cash_drawer !== false,
    header_text: nonBlankText(input.header_text, base.header_text),
    subheader_text: nonBlankText(input.subheader_text, base.subheader_text),
    cash_box_text: nonBlankText(input.cash_box_text, base.cash_box_text),
    footer_text: nonBlankText(input.footer_text, base.footer_text),
    show_dividers: true,
    blocks: normalizedBlocks,
  };
}

export function reorderTicketBlocks(blocks, fromIndex, toIndex) {
  if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0 || fromIndex >= blocks.length || toIndex >= blocks.length) {
    return blocks.map((block) => ({ ...block }));
  }

  const clone = blocks.map((block) => ({ ...block }));
  const [moved] = clone.splice(fromIndex, 1);
  clone.splice(toIndex, 0, moved);
  return clone;
}

function clampPercent(value, fallback, min, max) {
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

function forcedBlockPosition(blockKey, fallback) {
  if (blockKey === "cash_box") {
    return 100;
  }

  if (["folio", "date"].includes(blockKey)) {
    return 0;
  }

  return fallback;
}

function normalizeText(value) {
  return String(value || "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .replace(/[^\x20-\x7E]/g, " ")
    .replace(/\s+/g, " ")
    .trim();
}

function padRight(value, width) {
  const text = normalizeText(value);
  return text.length >= width ? text.slice(0, width) : text + " ".repeat(width - text.length);
}

function padLeft(value, width) {
  const text = String(value ?? "");
  return text.length >= width ? text.slice(text.length - width) : " ".repeat(width - text.length) + text;
}

function scaledTextWidth(value, sizePercent = 100, blockKey = "", bold = false) {
  return normalizeText(value).length * resolveEscPosTextStyle(sizePercent, blockKey, bold).widthScale;
}

function fitScaledText(value, maxColumns, sizePercent = 100, blockKey = "", bold = false) {
  const text = normalizeText(value);
  const widthScale = resolveEscPosTextStyle(sizePercent, blockKey, bold).widthScale;
  const maxChars = Math.max(0, Math.floor(maxColumns / widthScale));

  return text.slice(0, maxChars);
}

function wrapText(value, maxWidth) {
  const text = normalizeText(value);

  if (!text) {
    return [];
  }

  const words = text.split(" ");
  const lines = [];
  let currentLine = "";

  words.forEach((word) => {
    if (word.length > maxWidth) {
      if (currentLine) {
        lines.push(currentLine);
        currentLine = "";
      }

      for (let index = 0; index < word.length; index += maxWidth) {
        lines.push(word.slice(index, index + maxWidth));
      }

      return;
    }

    const nextLine = currentLine ? `${currentLine} ${word}` : word;

    if (nextLine.length > maxWidth) {
      lines.push(currentLine);
      currentLine = word;
      return;
    }

    currentLine = nextLine;
  });

  if (currentLine) {
    lines.push(currentLine);
  }

  return lines;
}

function formatDocumentTitle(template, printJob) {
  const title = normalizeText(template.subheader_text || "TICKET DE VENTA");
  const branch = normalizeText(printJob.branch_name);

  return branch ? `${title}: ${branch}` : title;
}

function cashBoxText(template, printJob) {
  const dynamicText = normalizeText(printJob.cash_box_text);

  if (dynamicText) {
    return dynamicText;
  }

  const cashBoxNumber = normalizeText(printJob.cash_box_number);

  if (cashBoxNumber) {
    return `CAJA #${cashBoxNumber}`;
  }

  return normalizeText(template.cash_box_text || "CAJA");
}

function centerRightLine(centerValue, rightValue, width) {
  const centerText = normalizeText(centerValue).slice(0, width);
  const rightText = normalizeText(rightValue).slice(0, width);
  const chars = Array.from({ length: width }, () => " ");
  const rightStart = Math.max(0, width - rightText.length);
  let centerStart = Math.max(0, Math.round((width - centerText.length) / 2));

  if (centerText.length && rightText.length && centerStart + centerText.length >= rightStart) {
    centerStart = Math.max(0, rightStart - centerText.length - 1);
  }

  [...centerText].forEach((char, index) => {
    const position = centerStart + index;

    if (position >= 0 && position < rightStart) {
      chars[position] = char;
    }
  });

  [...rightText].forEach((char, index) => {
    const position = rightStart + index;

    if (position >= 0 && position < width) {
      chars[position] = char;
    }
  });

  return chars.join("");
}

function centerRightSegments(row, width) {
  const rightSize = Number(row.right_size_percent || row.size_percent || 100);
  const centerSize = Number(row.center_size_percent || row.size_percent || 100);
  const rightBlockKey = row.right_block_key || row.block_key;
  const centerBlockKey = row.center_block_key || row.block_key;
  const rawRight = normalizeText(row.right);
  const rawCenter = normalizeText(row.center);
  const rightScale = resolveEscPosTextStyle(rightSize, rightBlockKey, Boolean(row.right_bold ?? row.bold)).widthScale;
  const minRightZone = rawRight ? Math.ceil(Math.min(rawRight.length * rightScale, width * 0.42)) : 0;
  const rightZone = rawRight ? Math.max(10, Math.min(Math.ceil(width * 0.38), minRightZone + 1)) : 0;
  const centerZone = Math.max(0, width - rightZone);
  const right = fitScaledText(rawRight, rightZone, rightSize, rightBlockKey, Boolean(row.right_bold ?? row.bold));
  const center = fitScaledText(rawCenter, centerZone, centerSize, centerBlockKey, Boolean(row.center_bold ?? row.bold));
  const centerVisualWidth = scaledTextWidth(center, centerSize, centerBlockKey, Boolean(row.center_bold ?? row.bold));
  const rightVisualWidth = scaledTextWidth(right, rightSize, rightBlockKey, Boolean(row.right_bold ?? row.bold));
  const fullCenterLeft = Math.max(0, Math.floor((width - centerVisualWidth) / 2));
  const maxCenterLeft = Math.max(0, centerZone - centerVisualWidth - 1);
  const centerLeft = Math.min(fullCenterLeft, maxCenterLeft);
  const rightLeft = Math.max(centerZone, width - rightVisualWidth);

  return {
    center,
    right,
    centerLeft,
    rightLeft,
    centerSize,
    rightSize,
    centerBlockKey,
    rightBlockKey,
  };
}

function alignLineByPercent(value, width, positionPercent = 0) {
  const text = normalizeText(value);

  if (text.length >= width) {
    return text.slice(0, width);
  }

  const freeSpace = Math.max(width - text.length, 0);
  const leftPadding = Math.round((freeSpace * Number(positionPercent || 0)) / 100);

  return " ".repeat(leftPadding) + text;
}

function isTopHeaderBlock(blockKey = "") {
  return ["brand_title", "document_title", "folio"].includes(blockKey);
}

function resolveEscPosTextStyle(sizePercent = 100, blockKey = "", forceBoldOverride = false) {
  const size = Number(sizePercent || 100);
  const isHeader = isTopHeaderBlock(blockKey);
  const forceBold = forceBoldOverride || ["brand_title", "cash_box", "folio", "date", "footer_text"].includes(blockKey);

  if (isHeader) {
    if (size >= 160) {
      return { command: "\x1B\x45\x01\x1D\x21\x11", widthScale: 2 };
    }

    if (size >= 136) {
      return { command: "\x1B\x45\x01\x1D\x21\x01", widthScale: 1 };
    }

    if (size >= 118) {
      return { command: "\x1B\x45\x01\x1D\x21\x01", widthScale: 1 };
    }

    if (size >= 104) {
      return { command: "\x1B\x45\x01\x1D\x21\x00", widthScale: 1 };
    }

    return { command: `${forceBold ? "\x1B\x45\x01" : "\x1B\x45\x00"}\x1D\x21\x00`, widthScale: 1 };
  }

  if (size >= 140) {
    return { command: "\x1B\x45\x01\x1D\x21\x11", widthScale: 2 };
  }

  if (size >= 120) {
    return { command: "\x1B\x45\x01\x1D\x21\x01", widthScale: 1 };
  }

  if (size >= 104) {
    return { command: "\x1B\x45\x01\x1D\x21\x00", widthScale: 1 };
  }

  return { command: `${forceBold ? "\x1B\x45\x01" : "\x1B\x45\x00"}\x1D\x21\x00`, widthScale: 1 };
}

function alignPrintLine(value, width, positionPercent = 0, sizePercent = 100, blockKey = "") {
  const text = normalizeText(value);
  const style = resolveEscPosTextStyle(sizePercent, blockKey);
  const visualLength = text.length * style.widthScale;

  if (visualLength >= width) {
    const maxChars = Math.max(1, Math.floor(width / style.widthScale));
    return text.slice(0, maxChars);
  }

  const freeSpace = Math.max(width - visualLength, 0);
  const leftPadding = Math.round((freeSpace * Number(positionPercent || 0)) / 100);

  return " ".repeat(leftPadding) + text;
}

function sizePercentToEsc(sizePercent) {
  return resolveEscPosTextStyle(sizePercent).command;
}

function lineWidthForPaper(width) {
  if (Number(width) === 32) return 32;
  if (Number(width) === 48) return 48;
  return 42;
}

function blockGapLines(blockKey) {
  return 0;
}

function enabledBlocks(template) {
  return normalizeTicketTemplate(template).blocks.filter((block) => block.enabled);
}

function isBlockEnabled(template, key) {
  return normalizeTicketTemplate(template).blocks.some((block) => block.key === key && block.enabled);
}

function buildRowsForBlock(template, printJob, block) {
  const rows = [];
  const width = lineWidthForPaper(template.paper_width);
  const brandEnabled = isBlockEnabled(template, "brand_title");
  const cashBoxEnabled = isBlockEnabled(template, "cash_box");
  const folioEnabled = isBlockEnabled(template, "folio");
  const dateEnabled = isBlockEnabled(template, "date");
  const cashBoxBlock = normalizeTicketTemplate(template).blocks.find((candidate) => candidate.key === "cash_box");
  const dateBlock = normalizeTicketTemplate(template).blocks.find((candidate) => candidate.key === "date");

  switch (block.key) {
    case "brand_title":
      rows.push({
        type: "center_right",
        center: template.header_text,
        right: cashBoxEnabled ? cashBoxText(template, printJob) : "",
        block_key: block.key,
        center_block_key: block.key,
        right_block_key: "cash_box",
        position_percent: block.position_percent,
        size_percent: block.size_percent,
        center_size_percent: block.size_percent,
        right_size_percent: cashBoxBlock?.size_percent ?? block.size_percent,
      });
      break;
    case "document_title":
      rows.push({ type: "text", text: formatDocumentTitle(template, printJob), block_key: block.key, position_percent: block.position_percent, size_percent: block.size_percent });
      break;
    case "seller_user": {
      const sellerName = normalizeText(printJob.user_name || printJob.employee_name || printJob.seller_name);

      if (sellerName) {
        rows.push({
          type: "pair",
          label: "Atendio",
          value: sellerName,
          block_key: block.key,
          position_percent: block.position_percent,
          size_percent: block.size_percent,
          bold: true,
        });
      }
      break;
    }
    case "cash_box":
      if (!brandEnabled) {
        rows.push({
          type: "center_right",
          center: "",
          right: cashBoxText(template, printJob),
          block_key: block.key,
          center_block_key: block.key,
          right_block_key: block.key,
          position_percent: block.position_percent,
          size_percent: block.size_percent,
          center_size_percent: block.size_percent,
          right_size_percent: block.size_percent,
        });
      }
      break;
    case "folio":
      rows.push({
        type: "columns",
        left: printJob.folio,
        right: dateEnabled ? `Fecha: ${printJob.date}` : "",
        block_key: block.key,
        position_percent: block.position_percent,
        size_percent: block.size_percent,
        right_size_percent: dateBlock?.size_percent ?? block.size_percent,
        right_block_key: "date",
        right_bold: true,
        right_width: 25,
      });
      break;
    case "divider_header":
      if (template.show_dividers) {
        rows.push({ type: "divider", text: ".".repeat(width), block_key: block.key, position_percent: block.position_percent, size_percent: block.size_percent });
      }
      break;
    case "divider_items":
      if (template.show_dividers) {
        rows.push({ type: "divider", text: ".".repeat(width), block_key: block.key, position_percent: block.position_percent, size_percent: block.size_percent });
      }
      break;
    case "divider_folio":
      if (template.show_dividers) {
        rows.push({ type: "divider", text: ".".repeat(width), block_key: block.key, position_percent: block.position_percent, size_percent: block.size_percent });
      }
      break;
    case "divider_totals":
      if (template.show_dividers) {
        rows.push({ type: "divider", text: ".".repeat(width), block_key: block.key, position_percent: block.position_percent, size_percent: block.size_percent });
      }
      break;
    case "divider_footer":
      if (template.show_dividers) {
        rows.push({ type: "divider", text: ".".repeat(width), block_key: block.key, position_percent: block.position_percent, size_percent: block.size_percent });
      }
      break;
    case "date":
      if (!folioEnabled) {
        rows.push({
          type: "columns",
          left: "",
          right: `Fecha: ${printJob.date}`,
          block_key: block.key,
          position_percent: block.position_percent,
          size_percent: block.size_percent,
          bold: true,
          right_width: 25,
        });
      }
      break;
    case "payment_method":
      rows.push({ type: "pair", label: "Pago", value: printJob.payment_method, block_key: block.key, position_percent: block.position_percent, size_percent: block.size_percent });
      break;
    case "items":
      (printJob.items || []).forEach((item) => {
        rows.push({
          type: "text",
          text: `${normalizeText(item.product_name)}:`,
          block_key: block.key,
          position_percent: block.position_percent,
          size_percent: Math.max(70, block.size_percent - 10),
          bold: true,
        });
        rows.push({
          type: "columns",
          left: `${Number(item.quantity || 0).toFixed(2)} x ${Number(item.unit_price || 0).toFixed(2)}`,
          right: Number(item.subtotal || 0).toFixed(2),
          block_key: block.key,
          position_percent: block.position_percent,
          size_percent: block.size_percent,
        });

        if (Number(item.discount_percentage || 0) > 0) {
          rows.push({
            type: "text",
            text: `Desc ${Number(item.discount_percentage || 0).toFixed(0)}% - ${Number(item.discount_amount || 0).toFixed(2)}`,
            block_key: block.key,
            position_percent: block.position_percent,
            size_percent: Math.max(70, block.size_percent - 12),
          });
        }
      });
      break;
    case "totals":
      rows.push({ type: "columns", left: "Recibido:", right: `$${Number(printJob.cash_received || 0).toFixed(2)}`, block_key: block.key, position_percent: block.position_percent, size_percent: Math.max(80, block.size_percent - 15), bold: true });
      rows.push({ type: "columns", left: "Cambio:", right: `$${Number(printJob.change_due || 0).toFixed(2)}`, block_key: block.key, position_percent: block.position_percent, size_percent: Math.max(80, block.size_percent - 15), bold: true });
      rows.push({ type: "columns", left: "TOTAL:", right: `$${Number(printJob.total || 0).toFixed(2)}`, block_key: block.key, position_percent: block.position_percent, size_percent: block.size_percent, bold: true });
      break;
    case "footer_text":
      wrapText(template.footer_text, Math.max(24, width - 8)).forEach((line) => {
        rows.push({ type: "text", text: line, block_key: block.key, position_percent: 50, size_percent: block.size_percent });
      });
      break;
    default:
      break;
  }

  return rows;
}

export function buildTicketPreviewBlocks(template, printJob) {
  const resolved = normalizeTicketTemplate(template);

  return enabledBlocks(resolved)
    .map((block) => ({
      key: block.key,
      label: BLOCK_CATALOG.find((item) => item.key === block.key)?.label || block.key,
      position_percent: block.position_percent,
      size_percent: block.size_percent,
      rows: buildRowsForBlock(resolved, printJob, block),
    }))
    .filter((block) => block.rows.length > 0);
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function previewFontSize(sizePercent = 100) {
  return Math.max(10, Math.round(Number(sizePercent || 100) * 0.1));
}

function previewPadding(positionPercent = 0) {
  return Math.max(0, Math.min(78, Number(positionPercent || 0))) * 0.65;
}

const CSS_DPI = 96;
const CSS_PX_PER_MM = CSS_DPI / 25.4;

function mmToPx(mm) {
  return Number((Number(mm || 0) * CSS_PX_PER_MM).toFixed(2));
}

function pxToMm(px) {
  return Number((Number(px || 0) / CSS_PX_PER_MM).toFixed(2));
}

function paperWidthMm(width) {
  if (Number(width) === 32) return 58;
  return 80;
}

function printableWidthMm(width) {
  return Number(width) === 32 ? 48 : 68;
}

function printPaddingPercent(positionPercent = 0) {
  return Number(previewPadding(positionPercent).toFixed(2));
}

function visualFontSizePx(sizePercent = 100) {
  return previewFontSize(sizePercent);
}

function estimateRowHeightPx(row) {
  const baseFont = row.type === "center_right"
    ? Math.max(visualFontSizePx(row.center_size_percent), visualFontSizePx(row.right_size_percent))
    : visualFontSizePx(row.size_percent);

  if (row.type === "divider") {
    return 14;
  }

  if (row.type === "columns") {
    return Math.round(baseFont * 1.55 + 5);
  }

  return Math.round(baseFont * 1.5 + 5);
}

function estimateTicketHeightMm(previewBlocks) {
  const contentHeightPx = previewBlocks.reduce((height, block) => {
    const rowsHeight = block.rows.reduce((sum, row) => sum + estimateRowHeightPx(row), 0);
    const blockGap = block.rows.length ? 6 : 0;

    return height + rowsHeight + blockGap;
  }, 0);

  return Math.max(pxToMm(contentHeightPx + 32), 40);
}

export function resolveTicketPrintDimensions(template, printJob) {
  const resolved = normalizeTicketTemplate(template);
  const previewBlocks = buildTicketPreviewBlocks(resolved, printJob);
  const widthMm = paperWidthMm(resolved.paper_width);
  const contentWidthMm = printableWidthMm(resolved.paper_width);
  const heightMm = estimateTicketHeightMm(previewBlocks);

  return {
    width: widthMm,
    height: heightMm,
    contentWidth: contentWidthMm,
    widthPx: mmToPx(widthMm),
    heightPx: mmToPx(heightMm),
    contentWidthPx: mmToPx(contentWidthMm),
  };
}

function normalizePreviewText(value) {
  return String(value ?? "")
    .replace(/\s+/g, " ")
    .trim();
}

export function buildTicketHtmlMarkup(template, printJob) {
  const resolved = normalizeTicketTemplate(template);
  const previewBlocks = buildTicketPreviewBlocks(resolved, printJob);
  const dimensions = resolveTicketPrintDimensions(resolved, printJob);
  const paperWidth = dimensions.width;
  const paperHeight = dimensions.height;
  const outerWidthPx = Math.round(dimensions.widthPx);
  const outerHeightPx = Math.round(dimensions.heightPx);
  const contentWidthPx = Math.round(dimensions.contentWidthPx);

  const rowsMarkup = previewBlocks
    .map((block) => {
      const blockRows = block.rows
        .map((row) => {
          const fontSize = visualFontSizePx(row.size_percent);
          const color = "#000000";
          const leftPadding = printPaddingPercent(row.position_percent);

          if (row.type === "divider") {
            return `<div style="margin:4px 0 6px 0; color:#94a3b8; font-size:10px; line-height:1;">${escapeHtml(row.text)}</div>`;
          }

          if (row.type === "columns") {
            return `
              <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin:0 0 6px 0; font-size:${fontSize}px; line-height:1.2; font-weight:${row.bold ? 700 : 500}; color:${color};">
                <div style="min-width:0; flex:1; padding-left:${leftPadding}%; box-sizing:border-box; word-break:break-word; overflow-wrap:anywhere;">${escapeHtml(normalizePreviewText(row.left))}</div>
                <div style="flex-shrink:0; min-width:56px; text-align:right;">${escapeHtml(normalizePreviewText(row.right))}</div>
              </div>
            `;
          }

          if (row.type === "center_right") {
            const centerFontSize = visualFontSizePx(row.center_size_percent || row.size_percent);
            const rightFontSize = visualFontSizePx(row.right_size_percent || row.size_percent);
            const rowMinHeight = Math.round(Math.max(centerFontSize, rightFontSize) * 1.35);

            return `
              <div style="position:relative; margin:0 0 6px 0; line-height:1.2; font-weight:700; color:${color}; min-height:${rowMinHeight}px;">
                <div style="font-size:${centerFontSize}px; text-align:center; padding:0 96px 0 0; box-sizing:border-box; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(normalizePreviewText(row.center))}</div>
                <div style="font-size:${rightFontSize}px; position:absolute; right:0; top:0; max-width:44%; overflow:hidden; text-overflow:ellipsis; text-align:right; white-space:nowrap;">${escapeHtml(normalizePreviewText(row.right))}</div>
              </div>
            `;
          }

          const text = row.type === "pair"
            ? `${normalizePreviewText(row.label)}: ${normalizePreviewText(row.value)}`
            : normalizePreviewText(row.text);
          const isCenteredText = row.type === "text" && row.block_key === "document_title";

          return `
            <div style="padding-left:${isCenteredText ? 0 : leftPadding}%; margin:0 0 6px 0; font-size:${fontSize}px; line-height:1.2; font-weight:${row.bold || row.type === "text" ? 700 : 500}; color:${color}; word-break:break-word; overflow-wrap:anywhere; text-align:${isCenteredText ? "center" : "left"};">
              ${escapeHtml(text)}
            </div>
          `;
        })
        .join("");

      return `<div style="margin-bottom:6px;">${blockRows}</div>`;
    })
    .join("");

  return `
    <html>
      <head>
        <meta charset="utf-8">
        <style>
          @page {
            size: ${paperWidth}mm ${paperHeight}mm;
            margin: 0;
          }

          html, body {
            width: ${paperWidth}mm;
            min-height: ${paperHeight}mm;
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #000000;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
          }
        </style>
      </head>
      <body>
        <div style="width:${outerWidthPx}px; min-height:${outerHeightPx}px; box-sizing:border-box; padding:0 ${Math.round((outerWidthPx - contentWidthPx) / 2)}px 16px ${Math.round((outerWidthPx - contentWidthPx) / 2)}px; background:#ffffff; color:#000000;">
          <div style="width:${contentWidthPx}px; box-sizing:border-box; overflow:hidden;">
            ${rowsMarkup}
          </div>
        </div>
      </body>
    </html>
  `;
}

export function buildEscPosTicketData(template, printJob) {
  const resolved = normalizeTicketTemplate(template);
  const width = lineWidthForPaper(resolved.paper_width);
  const lines = [
    "\x1B\x40",
    "\x1B\x33\x00",
  ];
  const previewBlocks = buildTicketPreviewBlocks(resolved, printJob);

  previewBlocks.forEach((block) => {
    block.rows.forEach((row) => {
      const sizePercent = Number(row.size_percent || 100);
      const positionPercent = Number(row.position_percent || 0);

      if (row.type === "divider") {
        lines.push(sizePercentToEsc(100));
        lines.push(`${normalizeText(row.text) || "-".repeat(width)}\n`);
        return;
      }

      if (row.type === "text") {
        lines.push(resolveEscPosTextStyle(sizePercent, row.block_key, Boolean(row.bold)).command);
        lines.push(`${alignPrintLine(normalizeText(row.text), width, positionPercent, sizePercent, row.block_key)}\n`);
        lines.push(sizePercentToEsc(100));
        return;
      }

      if (row.type === "pair") {
        lines.push(resolveEscPosTextStyle(sizePercent, row.block_key, Boolean(row.bold)).command);
        lines.push(
          `${alignPrintLine(`${normalizeText(row.label)}: ${normalizeText(row.value)}`, width, positionPercent, sizePercent, row.block_key)}\n`
        );
        lines.push(sizePercentToEsc(100));
        return;
      }

      if (row.type === "center_right") {
        const segments = centerRightSegments(row, width);
        const centerWidth = scaledTextWidth(
          segments.center,
          segments.centerSize,
          segments.centerBlockKey,
          Boolean(row.center_bold ?? row.bold)
        );
        const currentAfterCenter = segments.centerLeft + centerWidth;

        lines.push(sizePercentToEsc(100));
        lines.push(" ".repeat(segments.centerLeft));

        if (segments.center) {
          lines.push(resolveEscPosTextStyle(segments.centerSize, segments.centerBlockKey, Boolean(row.center_bold ?? row.bold)).command);
          lines.push(segments.center);
          lines.push(sizePercentToEsc(100));
        }

        lines.push(" ".repeat(Math.max(0, segments.rightLeft - currentAfterCenter)));

        if (segments.right) {
          lines.push(resolveEscPosTextStyle(segments.rightSize, segments.rightBlockKey, Boolean(row.right_bold ?? row.bold)).command);
          lines.push(segments.right);
          lines.push(sizePercentToEsc(100));
        }

        lines.push("\n");
        lines.push(sizePercentToEsc(100));
        return;
      }

      if (row.type === "columns") {
        const rightText = normalizeText(row.right);
        const leftSize = Number(row.left_size_percent || row.size_percent || 100);
        const rightSize = Number(row.right_size_percent || row.size_percent || 100);
        const leftBlockKey = row.left_block_key || row.block_key;
        const rightBlockKey = row.right_block_key || row.block_key;
        const leftBold = Boolean(row.left_bold ?? row.bold);
        const rightBold = Boolean(row.right_bold ?? row.bold);
        const leftPrefix = " ".repeat(
          Math.round((Math.max(width - 10, 0) * positionPercent) / 100)
        );
        const rightWidth = Number(row.right_width || 10);
        const contentWidth = Math.max(width - leftPrefix.length, rightWidth + 10);
        const leftZone = Math.max(0, contentWidth - rightWidth);
        const leftText = fitScaledText(row.left, leftZone, leftSize, leftBlockKey, leftBold);
        const fittedRightText = fitScaledText(rightText, rightWidth, rightSize, rightBlockKey, rightBold);
        const leftVisualWidth = scaledTextWidth(leftText, leftSize, leftBlockKey, leftBold);
        const rightVisualWidth = scaledTextWidth(fittedRightText, rightSize, rightBlockKey, rightBold);
        const rightLeft = leftPrefix.length + Math.max(leftZone, contentWidth - rightVisualWidth);
        const currentAfterLeft = leftPrefix.length + leftVisualWidth;

        lines.push(sizePercentToEsc(100));
        lines.push(leftPrefix);

        if (leftText) {
          lines.push(resolveEscPosTextStyle(leftSize, leftBlockKey, leftBold).command);
          lines.push(leftText);
          lines.push(sizePercentToEsc(100));
        }

        lines.push(" ".repeat(Math.max(0, rightLeft - currentAfterLeft)));

        if (fittedRightText) {
          lines.push(resolveEscPosTextStyle(rightSize, rightBlockKey, rightBold).command);
          lines.push(fittedRightText);
          lines.push(sizePercentToEsc(100));
        }

        lines.push("\n");
        lines.push(sizePercentToEsc(100));
      }
    });

    for (let index = 0; index < blockGapLines(block.key); index += 1) {
      lines.push("\n");
    }
  });

  if (resolved.open_cash_drawer) {
    lines.push("\x1B\x70\x00\x19\xFA");
  }

  if (resolved.auto_cut) {
    const cutFeedDots = Math.min(255, Math.max(1, Number(resolved.feed_lines || 6) * 16));
    lines.push(`\x1D\x56\x41${String.fromCharCode(cutFeedDots)}`);
  } else {
    lines.push("\x1B\x32");

    for (let index = 0; index < resolved.feed_lines; index += 1) {
      lines.push("\n");
    }
  }

  return lines;
}
