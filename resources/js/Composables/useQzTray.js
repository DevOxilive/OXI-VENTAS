import qz from "qz-tray";

const PRINTER_STORAGE_KEY = "ventas_printer_name";

let securityConfigured = false;

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

export async function connectQzTray() {
  configureSecurity();

  if (qz.websocket.isActive()) {
    return qz;
  }

  await qz.websocket.connect({
    retries: 2,
    delay: 1,
    keepAlive: 60,
  });

  return qz;
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

  if (!isQzTrayActive()) {
    if (options.connectIfNeeded === false) {
      throw new Error("QZ Tray no esta conectado. La venta se guardo sin imprimir ticket.");
    }

    await connectQzTray();
  }

  const config = qz.configs.create(printerName, {
    encoding: "Cp1252",
    copies: 1,
  });

  const payload = (Array.isArray(printData) ? printData.join("") : String(printData || ""))
    .replace(/\r?\n/g, "\r\n");

  return qz.print(config, [{
    type: "raw",
    format: "command",
    flavor: "plain",
    data: payload,
  }]);
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
