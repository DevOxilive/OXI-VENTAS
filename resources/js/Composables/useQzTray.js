import qz from "qz-tray";

const TICKET_PRINTER_STORAGE_KEY = "ventas_ticket_printer_name";
const LABEL_PRINTER_STORAGE_KEY = "ventas_label_printer_name";
// QZ Tray puede tardar varios segundos en iniciar cuando Windows acaba de abrirlo.
// Mantener el reintento aqui hace que todas las pantallas que imprimen esperen al
// mismo proceso, en vez de pedir al usuario que recargue la pagina.
const QZ_CONNECTION_RETRIES = 8;
const QZ_CONNECTION_DELAY_SECONDS = 1;
const QZ_STARTUP_TIMEOUT_MS = 60000;
const QZ_STARTUP_RETRY_DELAY_MS = 1500;
const QZ_PRINTER_DISCOVERY_TIMEOUT_MS = 30000;
const QZ_PRINTER_DISCOVERY_RETRY_DELAY_MS = 1000;

let securityConfigured = false;
let connectionPromise = null;
let printQueue = Promise.resolve();
const printerDiscoveryPromises = new Map();

function csrfToken() {
  if (typeof document === "undefined") {
    return "";
  }

  return document.querySelector('meta[name="csrf-token"]')?.content || "";
}

function responseTextOrError(response) {
  return response.text().then((text) => {
    if (!response.ok) {
      throw new Error(text || `QZ respondio con error ${response.status}.`);
    }

    return text;
  });
}

function configureSecurity() {
  if (securityConfigured) {
    return;
  }

  qz.security.setCertificatePromise((resolve, reject) => {
    fetch("/qz/certificate", {
      credentials: "same-origin",
      headers: {
        Accept: "text/plain",
      },
    })
      .then(responseTextOrError)
      .then(resolve)
      .catch(reject);
  }, { rejectOnFailure: true });

  qz.security.setSignatureAlgorithm("SHA256");
  qz.security.setSignaturePromise((dataToSign) => (resolve, reject) => {
    fetch("/qz/sign", {
      method: "POST",
      credentials: "same-origin",
      headers: {
        Accept: "text/plain",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken(),
      },
      body: JSON.stringify({ data: dataToSign }),
    })
      .then(responseTextOrError)
      .then(resolve)
      .catch(reject);
  });

  securityConfigured = true;
}

export function getStoredTicketPrinterName() {
  if (typeof window === "undefined") {
    return "";
  }

  return window.localStorage.getItem(TICKET_PRINTER_STORAGE_KEY) || "";
}

export function saveStoredTicketPrinterName(printerName) {
  if (typeof window === "undefined") {
    return;
  }

  if (!printerName) {
    window.localStorage.removeItem(TICKET_PRINTER_STORAGE_KEY);
    return;
  }

  window.localStorage.setItem(TICKET_PRINTER_STORAGE_KEY, printerName);
}

export function getStoredLabelPrinterName() {
  if (typeof window === "undefined") {
    return "";
  }

  return window.localStorage.getItem(LABEL_PRINTER_STORAGE_KEY) || "";
}

export function saveStoredLabelPrinterName(printerName) {
  if (typeof window === "undefined") {
    return;
  }

  if (!printerName) {
    window.localStorage.removeItem(LABEL_PRINTER_STORAGE_KEY);
    return;
  }

  window.localStorage.setItem(LABEL_PRINTER_STORAGE_KEY, printerName);
}

// Los tickets no deben caer en la impresora predeterminada ni en la primera
// cola de Windows: una caja puede tener tambien una impresora de etiquetas.
export function findTicketPrinter(printers = []) {
  const availablePrinters = Array.isArray(printers) ? printers : [];
  const storedTicketPrinter = getStoredTicketPrinterName();

  return storedTicketPrinter && availablePrinters.includes(storedTicketPrinter)
    ? storedTicketPrinter
    : "";
}

// Las etiquetas usan una asignación propia. Nunca se toma la impresora
// predeterminada de Windows ni la primera cola disponible.
export function findLabelPrinter(printers = []) {
  const availablePrinters = Array.isArray(printers) ? printers : [];
  const storedLabelPrinter = getStoredLabelPrinterName();

  return storedLabelPrinter && availablePrinters.includes(storedLabelPrinter)
    ? storedLabelPrinter
    : "";
}

export async function connectQzTray() {
  configureSecurity();

  if (qz.websocket.isActive()) {
    return qz;
  }

  if (!connectionPromise) {
    connectionPromise = connectQzTrayDuringStartup()
      .finally(() => {
        connectionPromise = null;
      });
  }

  return connectionPromise;
}

export function isQzTrayActive() {
  configureSecurity();

  return qz.websocket.isActive();
}

export async function disconnectQzTray() {
  if (!qz.websocket.isActive()) {
    return;
  }

  await qz.websocket.disconnect();
}

function wait(ms) {
  return new Promise((resolve) => {
    window.setTimeout(resolve, ms);
  });
}

async function connectQzTrayDuringStartup() {
  const deadline = Date.now() + QZ_STARTUP_TIMEOUT_MS;
  let lastError = null;

  while (Date.now() < deadline) {
    if (qz.websocket.isActive()) {
      return qz;
    }

    try {
      await qz.websocket.connect({
        retries: QZ_CONNECTION_RETRIES,
        delay: QZ_CONNECTION_DELAY_SECONDS,
        keepAlive: 60,
      });

      return qz;
    } catch (error) {
      lastError = error;

      const remainingMs = deadline - Date.now();
      if (remainingMs <= 0) {
        break;
      }

      await wait(Math.min(QZ_STARTUP_RETRY_DELAY_MS, remainingMs));
    }
  }

  throw lastError || new Error("QZ Tray no estuvo disponible durante el arranque.");
}

function withTimeout(promise, ms, message) {
  let timeoutId = null;

  const timeout = new Promise((_, reject) => {
    timeoutId = window.setTimeout(() => {
      reject(new Error(message));
    }, ms);
  });

  return Promise.race([promise, timeout]).finally(() => {
    if (timeoutId) {
      window.clearTimeout(timeoutId);
    }
  });
}

async function reconnectQzTray() {
  configureSecurity();
  connectionPromise = null;

  try {
    if (qz.websocket.isActive()) {
      await qz.websocket.disconnect();
    }
  } catch (error) {
    // Si QZ ya se quedo en un estado inconsistente, forzamos una conexion nueva abajo.
  }

  return connectQzTray();
}

function shouldRetryQzPrint(error) {
  const message = String(error?.message || error || "");

  return /sendData is not a function|connection attempt has not returned|open connection with QZ Tray already exists|websocket|closed|not connected|tiempo de espera/i.test(message);
}

function normalizeRawPrintPayload(printData) {
  if (!Array.isArray(printData)) {
    return [{
      type: "raw",
      format: "command",
      flavor: "plain",
      data: String(printData || "").replace(/\r?\n/g, "\r\n"),
    }];
  }

  const payload = [];
  let commandBuffer = "";

  const flushCommandBuffer = () => {
    if (!commandBuffer) {
      return;
    }

    payload.push({
      type: "raw",
      format: "command",
      flavor: "plain",
      data: commandBuffer.replace(/\r?\n/g, "\r\n"),
    });
    commandBuffer = "";
  };

  printData.forEach((item) => {
    if (typeof item === "string") {
      commandBuffer += item;
      return;
    }

    flushCommandBuffer();
    payload.push(item);
  });

  flushCommandBuffer();

  return payload;
}

async function sendRawPrint(printerName, payload) {
  const config = qz.configs.create(printerName, {
    encoding: "Cp1252",
    copies: 1,
  });

  return qz.print(config, payload);
}

function normalizeExpectedPrinterNames(printerNames = []) {
  return [...new Set(
    (Array.isArray(printerNames) ? printerNames : [printerNames])
      .map((printerName) => String(printerName || "").trim())
      .filter(Boolean)
  )].sort();
}

async function findPrintersDuringStartup(expectedPrinterNames = []) {
  const deadline = Date.now() + QZ_PRINTER_DISCOVERY_TIMEOUT_MS;
  let lastPrinters = [];
  let lastError = null;
  let receivedPrinterList = false;

  while (Date.now() < deadline) {
    try {
      const printers = await qz.printers.find();
      lastPrinters = Array.isArray(printers) ? printers : [];
      receivedPrinterList = true;
      lastError = null;

      const discoveryComplete = expectedPrinterNames.length
        ? expectedPrinterNames.every((printerName) => lastPrinters.includes(printerName))
        : lastPrinters.length > 0;

      if (discoveryComplete) {
        return lastPrinters;
      }
    } catch (error) {
      lastError = error;

      if (!qz.websocket.isActive()) {
        await connectQzTray();
      }
    }

    const remainingMs = deadline - Date.now();
    if (remainingMs <= 0) {
      break;
    }

    await wait(Math.min(QZ_PRINTER_DISCOVERY_RETRY_DELAY_MS, remainingMs));
  }

  if (!receivedPrinterList && lastError) {
    throw lastError;
  }

  return lastPrinters;
}

export async function getQzPrinters({ expectedPrinterNames = [] } = {}) {
  await connectQzTray();

  const expectedNames = normalizeExpectedPrinterNames(expectedPrinterNames);
  const discoveryKey = expectedNames.join("\u0000") || "__any_printer__";

  if (!printerDiscoveryPromises.has(discoveryKey)) {
    const discoveryPromise = findPrintersDuringStartup(expectedNames)
      .finally(() => {
        printerDiscoveryPromises.delete(discoveryKey);
      });

    printerDiscoveryPromises.set(discoveryKey, discoveryPromise);
  }

  return printerDiscoveryPromises.get(discoveryKey);
}

export async function getQzVersion() {
  await connectQzTray();

  return qz.api.getVersion();
}

export async function getQzPrinterDetails() {
  await connectQzTray();

  return qz.printers.details();
}

export async function printEscPosTicket(printerName, printData = [], options = {}) {
  if (!printerName) {
    throw new Error("No hay una impresora seleccionada.");
  }

  if (printData.length === 1 && printData[0]?.type === "visual-layout") {
    const { renderVisualPrint } = await import("@/Components/Printers/visualPrintRenderer");
    const { layout, data, settings } = printData[0];
    const output = await renderVisualPrint(layout, data);
    printData = ["\x1B\x40", "\x1B\x33\x00", {
      type: "raw", format: "image", flavor: "base64", data: output.base64,
      options: { language: "escpos", dotDensity: "double", imageEncoding: "esc_asterisk" },
    }, "\n"];
    if (data.open_cash_drawer === true || (settings.open_cash_drawer && data.open_cash_drawer !== false)) {
      printData.push("\x1B\x70\x00\x19\xFA", "\x1B\x70\x01\x19\xFA", "\x10\x14\x01\x00\x05");
    }
    const feed = Math.max(1, Math.min(8, Number(settings.feed_lines || 1)));
    if (settings.auto_cut) printData.push(`\x1D\x56\x41${String.fromCharCode(feed * 16)}`);
    else printData.push("\x1B\x32", "\n".repeat(feed));
  }
  const payload = normalizeRawPrintPayload(printData);

  const runPrint = async () => {
    if (options.freshConnection) {
      await reconnectQzTray();
    } else if (!isQzTrayActive() || options.reconnectBeforePrint) {
      await connectQzTray();
    }

    try {
      return await withTimeout(
        sendRawPrint(printerName, payload),
        Number(options.timeoutMs || 8000),
        "QZ Tray agoto el tiempo de espera al imprimir."
      );
    } catch (error) {
      if (!shouldRetryQzPrint(error)) {
        throw error;
      }

      await reconnectQzTray();

      return withTimeout(
        sendRawPrint(printerName, payload),
        Number(options.timeoutMs || 8000),
        "QZ Tray agoto el tiempo de espera al reintentar impresion."
      );
    } finally {
      if (options.disconnectAfterPrint) {
        await wait(Number(options.disconnectDelayMs || 350));

        try {
          await disconnectQzTray();
        } catch (error) {
          // La siguiente impresion abrira una conexion nueva si esta ya no existe.
        }
      }
    }
  };

  const queuedPrint = printQueue.then(runPrint, runPrint);
  printQueue = queuedPrint.catch(() => {});

  return queuedPrint;
}

export async function printHtmlTicket(printerName, html, dimensions = {}) {
  if (!printerName) {
    throw new Error("No hay una impresora seleccionada.");
  }

  await connectQzTray();

  const widthMm = Number(dimensions.width || 58);
  const heightMm = Number(dimensions.height || 120);
  const config = qz.configs.create(printerName, {
    copies: 1,
    margins: 0,
    rasterize: true,
    scaleContent: false,
    units: "mm",
    size: {
      width: widthMm,
      height: heightMm,
    },
  });

  return qz.print(config, [
    {
      type: "pixel",
      format: "html",
      flavor: "plain",
      data: html,
    },
  ]);
}

export async function printImageLabel(printerName, base64Image, dimensions = {}, copies = 1) {
  if (!printerName) {
    throw new Error("No hay una impresora seleccionada.");
  }

  await connectQzTray();

  const widthMm = Number(dimensions.width);
  const heightMm = Number(dimensions.height);
  if (!Number.isFinite(widthMm) || !Number.isFinite(heightMm) || widthMm <= 0 || heightMm <= 0) {
    throw new Error("Configura un ancho y una longitud de papel válidos.");
  }
  const copyCount = Math.max(1, Math.min(100, Math.trunc(Number(copies) || 1)));
  const config = qz.configs.create(printerName, {
    copies: copyCount,
    margins: 0,
    scaleContent: true,
    units: "mm",
    colorType: "blackwhite",
    interpolation: "nearest-neighbor",
    size: {
      width: widthMm,
      height: heightMm,
    },
  });

  return qz.print(config, [{
    type: "pixel",
    format: "image",
    flavor: "base64",
    data: base64Image,
  }]);
}
