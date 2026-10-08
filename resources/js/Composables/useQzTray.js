import qz from "qz-tray";

const PRINTER_STORAGE_KEY = "ventas_printer_name";
const TICKET_PRINTER_STORAGE_KEY = "ventas_ticket_printer_name";
const TICKET_PRINTER_IDENTIFIERS = ["3nstar", "rpt006", "pos-58", "pos58", "pos 58"];
// QZ Tray puede tardar varios segundos en iniciar cuando Windows acaba de abrirlo.
// Mantener el reintento aqui hace que todas las pantallas que imprimen esperen al
// mismo proceso, en vez de pedir al usuario que recargue la pagina.
const QZ_CONNECTION_RETRIES = 8;
const QZ_CONNECTION_DELAY_SECONDS = 1;

let securityConfigured = false;
let connectionPromise = null;
let printQueue = Promise.resolve();

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

export function getStoredPrinterName() {
  if (typeof window === "undefined") {
    return "";
  }

  return window.localStorage.getItem(PRINTER_STORAGE_KEY) || "";
}

export function saveStoredPrinterName(printerName) {
  if (typeof window === "undefined") {
    return;
  }

  if (!printerName) {
    window.localStorage.removeItem(PRINTER_STORAGE_KEY);
    return;
  }

  window.localStorage.setItem(PRINTER_STORAGE_KEY, printerName);
}

export function getStoredTicketPrinterName() {
  if (typeof window === "undefined") {
    return "";
  }

  return window.localStorage.getItem(TICKET_PRINTER_STORAGE_KEY) || "";
}

function saveStoredTicketPrinterName(printerName) {
  if (typeof window === "undefined") {
    return;
  }

  if (!printerName) {
    window.localStorage.removeItem(TICKET_PRINTER_STORAGE_KEY);
    return;
  }

  window.localStorage.setItem(TICKET_PRINTER_STORAGE_KEY, printerName);
}

// Los tickets no deben caer en la impresora predeterminada ni en la primera
// cola de Windows: una caja puede tener tambien una impresora de etiquetas.
export function findTicketPrinter(printers = []) {
  const availablePrinters = Array.isArray(printers) ? printers : [];
  const storedPrinter = getStoredTicketPrinterName();

  if (storedPrinter && availablePrinters.includes(storedPrinter)) {
    return storedPrinter;
  }

  const detectedPrinter = availablePrinters.find((printerName) => {
    const normalizedName = String(printerName || "").toLowerCase();

    return TICKET_PRINTER_IDENTIFIERS.some((identifier) => normalizedName.includes(identifier));
  }) || "";

  saveStoredTicketPrinterName(detectedPrinter);

  return detectedPrinter;
}

export async function connectQzTray() {
  configureSecurity();

  if (qz.websocket.isActive()) {
    return qz;
  }

  if (!connectionPromise) {
    connectionPromise = qz.websocket.connect({
      retries: QZ_CONNECTION_RETRIES,
      delay: QZ_CONNECTION_DELAY_SECONDS,
      keepAlive: 60,
    }).then(() => qz)
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

export async function getQzPrinters() {
  await connectQzTray();

  return qz.printers.find();
}

export async function getDefaultQzPrinter() {
  await connectQzTray();

  return qz.printers.getDefault();
}

export async function printEscPosTicket(printerName, printData = [], options = {}) {
  if (!printerName) {
    throw new Error("No hay una impresora seleccionada.");
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
  const usesBrother62mmContinuousTape = /brother\s+ql-1110/i.test(printerName);
  const mediaWidthMm = usesBrother62mmContinuousTape ? 62 : widthMm;
  const copyCount = Math.max(1, Math.min(100, Math.trunc(Number(copies) || 1)));
  const config = qz.configs.create(printerName, {
    copies: copyCount,
    margins: 0,
    scaleContent: true,
    units: "mm",
    colorType: "blackwhite",
    interpolation: "nearest-neighbor",
    size: {
      width: mediaWidthMm,
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
