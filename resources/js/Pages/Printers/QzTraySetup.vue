<script setup>
import { computed, onMounted, ref } from "vue";

import SelectField from "@/Components/Forms/SelectField.vue";
import { ErrorAlert, ToastAlert, WarningAlert } from "@/Components/Modales/UniversalActionModal";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import {
  buildLabelPngBase64,
  createDefaultLabelTemplate,
  resolveLabelPrintDimensions,
} from "@/config/labelTemplate";
import {
  findLabelPrinter,
  findTicketPrinter,
  getQzPrinters,
  getQzVersion,
  getStoredLabelPrinterName,
  getStoredTicketPrinterName,
  printEscPosTicket,
  printImageLabel,
  saveStoredLabelPrinterName,
  saveStoredTicketPrinterName,
} from "@/Composables/useQzTray";

defineOptions({
  layout: AdminLayout,
});

const installerUrl = route("printers.qz-setup.download");
const ticketDriverUrl = "https://3nstar.com/wp-content/uploads/2023/07/RPT-Drivers.zip";
const labelDriverUrl = "https://support.brother.com/g/b/downloadtop.aspx?c=mx&lang=es&prod=lpql1110nwbeus";
const qzTrayDownloadUrl = "https://qz.io/download/";
const availablePrinters = ref([]);
const selectedTicketPrinter = ref(getStoredTicketPrinterName());
const selectedLabelPrinter = ref(getStoredLabelPrinterName());
const savedTicketPrinter = ref(getStoredTicketPrinterName());
const savedLabelPrinter = ref(getStoredLabelPrinterName());
const qzReady = ref(false);
const qzVersion = ref("");
const bridgeMessage = ref("Comprobando QZ Tray y las impresoras de esta computadora...");
const refreshing = ref(false);
const printingTicket = ref(false);
const printingLabel = ref(false);

const printerOptions = computed(() => availablePrinters.value.map((printerName) => ({
  label: printerName,
  value: printerName,
})));

const ticketAvailable = computed(() => (
  savedTicketPrinter.value && availablePrinters.value.includes(savedTicketPrinter.value)
));
const labelAvailable = computed(() => (
  savedLabelPrinter.value && availablePrinters.value.includes(savedLabelPrinter.value)
));

onMounted(() => {
  void refreshPrinterBridge();
});

async function refreshPrinterBridge({ showError = false } = {}) {
  refreshing.value = true;

  try {
    const [printers, version] = await Promise.all([
      getQzPrinters({
        expectedPrinterNames: [savedTicketPrinter.value, savedLabelPrinter.value],
      }),
      getQzVersion(),
    ]);

    availablePrinters.value = Array.isArray(printers) ? printers : [];
    qzVersion.value = String(version || "");
    qzReady.value = true;
    selectedTicketPrinter.value = findTicketPrinter(availablePrinters.value) || selectedTicketPrinter.value;
    selectedLabelPrinter.value = findLabelPrinter(availablePrinters.value) || selectedLabelPrinter.value;
    bridgeMessage.value = availablePrinters.value.length
      ? `QZ Tray conectado. Se encontraron ${availablePrinters.value.length} impresoras.`
      : "QZ Tray está conectado, pero Windows no reportó impresoras disponibles.";
  } catch (error) {
    qzReady.value = false;
    availablePrinters.value = [];
    bridgeMessage.value = error?.message || "No se pudo conectar con QZ Tray.";

    if (showError) {
      ErrorAlert({
        title: "No se pudo conectar QZ Tray",
        message: bridgeMessage.value,
      });
    }
  } finally {
    refreshing.value = false;
  }
}

function samePrinterForBothRoles(role, printerName) {
  if (!printerName) {
    return false;
  }

  return role === "ticket"
    ? printerName === savedLabelPrinter.value
    : printerName === savedTicketPrinter.value;
}

function savePrinterRole(role) {
  const printerName = role === "ticket"
    ? selectedTicketPrinter.value
    : selectedLabelPrinter.value;

  if (!printerName || !availablePrinters.value.includes(printerName)) {
    WarningAlert({
      title: "Selecciona una impresora disponible",
      message: "Actualiza la lista y elige una cola que Windows tenga disponible en esta computadora.",
    });
    return;
  }

  if (samePrinterForBothRoles(role, printerName)) {
    WarningAlert({
      title: "Cada función necesita su propia impresora",
      message: "La misma cola no puede utilizarse para tickets y etiquetas. Selecciona la impresora correspondiente.",
    });
    return;
  }

  if (role === "ticket") {
    saveStoredTicketPrinterName(printerName);
    savedTicketPrinter.value = printerName;
  } else {
    saveStoredLabelPrinterName(printerName);
    savedLabelPrinter.value = printerName;
  }

  ToastAlert({
    title: role === "ticket"
      ? "Impresora de tickets guardada"
      : "Impresora de etiquetas guardada",
  });
}

async function printTicketTest() {
  const printerName = savedTicketPrinter.value;

  if (!printerName || !availablePrinters.value.includes(printerName)) {
    WarningAlert({
      title: "Configura la impresora de tickets",
      message: "Guarda una impresora disponible antes de realizar la prueba.",
    });
    return;
  }

  printingTicket.value = true;

  try {
    await printEscPosTicket(printerName, [
      "\x1B\x40",
      "\x1B\x61\x01",
      "\x1B\x45\x01",
      "SUPER KAY\n",
      "\x1B\x45\x00",
      "PRUEBA DE IMPRESORA DE TICKETS\n",
      `${new Date().toLocaleString("es-MX")}\n`,
      `Cola: ${printerName}\n`,
      "Configuracion correcta\n\n\n",
      "\x1D\x56\x00",
    ], { timeoutMs: 10000 });

    ToastAlert({ title: "Ticket de prueba enviado" });
  } catch (error) {
    ErrorAlert({
      title: "No se pudo imprimir el ticket",
      message: error?.message || "QZ Tray no pudo enviar la prueba.",
    });
  } finally {
    printingTicket.value = false;
  }
}

async function printLabelTest() {
  const printerName = savedLabelPrinter.value;

  if (!printerName || !availablePrinters.value.includes(printerName)) {
    WarningAlert({
      title: "Configura la impresora de etiquetas",
      message: "Guarda una impresora disponible antes de realizar la prueba.",
    });
    return;
  }

  printingLabel.value = true;

  try {
    const template = createDefaultLabelTemplate();
    const product = {
      name: "PRUEBA SUPER KAY",
      price: 10,
      barcode: "750000000001",
      sku: "PRUEBA",
      stock: 1,
      branch: "CONFIGURACION",
      category: "PRUEBA",
      date: new Date().toLocaleDateString("es-MX"),
    };
    const image = buildLabelPngBase64(template, product);

    await printImageLabel(
      printerName,
      image,
      resolveLabelPrintDimensions(template),
      1
    );

    ToastAlert({ title: "Etiqueta de prueba enviada" });
  } catch (error) {
    ErrorAlert({
      title: "No se pudo imprimir la etiqueta",
      message: error?.message || "QZ Tray no pudo enviar la prueba.",
    });
  } finally {
    printingLabel.value = false;
  }
}
</script>

<template>
  <PageLayout>
    <section class="mx-auto w-full max-w-5xl space-y-5">
      <header class="rounded-3xl border border-secondary bg-background p-6 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div class="flex items-start gap-4">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary text-2xl text-white">
              <span class="material-symbols-outlined">print_connect</span>
            </div>
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">Impresoras</p>
              <h1 class="mt-1 text-2xl font-black text-text">Configurar impresoras</h1>
              <p class="mt-2 max-w-2xl text-sm leading-6 text-text opacity-75">
                Prepara QZ Tray y asigna una cola diferente para tickets y etiquetas en esta computadora.
              </p>
            </div>
          </div>

          <button
            type="button"
            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-2xl border border-secondary px-4 py-2 text-sm font-black text-text transition hover:border-primary disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="refreshing"
            @click="refreshPrinterBridge({ showError: true })"
          >
            <span class="material-symbols-outlined text-lg">refresh</span>
            {{ refreshing ? "Comprobando..." : "Actualizar impresoras" }}
          </button>
        </div>

        <div
          class="mt-5 flex items-start gap-3 rounded-2xl border p-4"
          :class="qzReady ? 'border-emerald-500/30 bg-emerald-500/10' : 'border-amber-500/30 bg-amber-500/10'"
        >
          <span class="material-symbols-outlined" :class="qzReady ? 'text-emerald-600' : 'text-amber-600'">
            {{ qzReady ? "check_circle" : "warning" }}
          </span>
          <div>
            <p class="text-sm font-black text-text">
              {{ qzReady ? `QZ Tray conectado${qzVersion ? ` · versión ${qzVersion}` : ""}` : "QZ Tray pendiente" }}
            </p>
            <p class="mt-1 text-xs leading-5 text-text opacity-75">{{ bridgeMessage }}</p>
          </div>
        </div>
      </header>

      <article class="rounded-3xl border border-secondary bg-background p-6 shadow-sm">
        <div>
          <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary">Paso 1</p>
          <h2 class="mt-1 text-lg font-black text-text">Preparar esta computadora</h2>
          <p class="mt-2 max-w-2xl text-sm leading-6 text-text opacity-75">
            Primero instala los drivers de las impresoras y QZ Tray. El certificado de Super Kay se instala hasta el final, cuando QZ Tray ya se encuentre instalado.
          </p>
        </div>

        <div class="mt-5">
          <h3 class="text-sm font-black text-text">Descargas necesarias</h3>
          <p class="mt-1 text-xs leading-5 text-text opacity-70">
            Instala únicamente los programas que falten en esta computadora. Los enlaces abren el sitio del fabricante.
          </p>

          <div class="mt-3 grid gap-3 md:grid-cols-3">
            <a
              :href="ticketDriverUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="flex min-h-20 items-center gap-3 rounded-2xl border border-secondary p-4 text-text transition hover:border-primary hover:bg-secondary"
            >
              <span class="material-symbols-outlined text-primary">receipt_long</span>
              <span>
                <strong class="block text-sm">Driver 3nStar RPT004</strong>
                <small class="mt-1 block opacity-70">Impresora de tickets</small>
              </span>
              <span class="material-symbols-outlined ml-auto text-lg opacity-60">open_in_new</span>
            </a>

            <a
              :href="labelDriverUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="flex min-h-20 items-center gap-3 rounded-2xl border border-secondary p-4 text-text transition hover:border-primary hover:bg-secondary"
            >
              <span class="material-symbols-outlined text-primary">barcode</span>
              <span>
                <strong class="block text-sm">Driver Brother QL-1110NWB</strong>
                <small class="mt-1 block opacity-70">Impresora de etiquetas</small>
              </span>
              <span class="material-symbols-outlined ml-auto text-lg opacity-60">open_in_new</span>
            </a>

            <a
              :href="qzTrayDownloadUrl"
              target="_blank"
              rel="noopener noreferrer"
              class="flex min-h-20 items-center gap-3 rounded-2xl border border-secondary p-4 text-text transition hover:border-primary hover:bg-secondary"
            >
              <span class="material-symbols-outlined text-primary">download</span>
              <span>
                <strong class="block text-sm">Descargar QZ Tray</strong>
                <small class="mt-1 block opacity-70">Comunicación con las impresoras</small>
              </span>
              <span class="material-symbols-outlined ml-auto text-lg opacity-60">open_in_new</span>
            </a>
          </div>
        </div>

        <ol class="mt-5 grid gap-3 text-sm leading-6 text-text md:grid-cols-3">
          <li class="rounded-2xl bg-secondary p-4">
            <strong class="block">1. Instala los drivers</strong>
            <span class="mt-1 block opacity-75">Instala los controladores 3nStar y Brother que correspondan a esta computadora.</span>
          </li>
          <li class="rounded-2xl bg-secondary p-4">
            <strong class="block">2. Instala QZ Tray</strong>
            <span class="mt-1 block opacity-75">Completa la instalación de QZ Tray antes de continuar con el certificado.</span>
          </li>
          <li class="rounded-2xl border border-primary/20 bg-primary/5 p-4">
            <strong class="block">3. Al final, instala el certificado</strong>
            <span class="mt-1 block opacity-75">Descarga el paquete y ejecuta <strong>CONFIGURAR-IMPRESORAS.bat</strong>.</span>
            <a
              :href="installerUrl"
              class="mt-3 inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-center text-sm font-black text-white transition hover:brightness-95"
            >
              <span class="material-symbols-outlined text-lg">verified_user</span>
              Descargar certificado de Super Kay
            </a>
          </li>
        </ol>

        <p class="mt-4 text-xs leading-5 text-text opacity-70">
          Cuando termines los tres pasos, regresa a esta pantalla, presiona <strong>Actualizar impresoras</strong> y asigna cada cola.
        </p>
      </article>

      <div class="grid gap-5 lg:grid-cols-2">
        <article class="rounded-3xl border border-secondary bg-background p-6 shadow-sm">
          <div class="flex items-start gap-3">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-secondary text-primary">
              <span class="material-symbols-outlined">receipt_long</span>
            </div>
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary">Paso 2</p>
              <h2 class="mt-1 text-lg font-black text-text">Impresora de tickets</h2>
              <p class="mt-1 text-sm leading-5 text-text opacity-70">Ventas, cortes, abonos y apertura de cajón usarán esta cola.</p>
            </div>
          </div>

          <div class="mt-5 space-y-4">
            <SelectField
              v-model="selectedTicketPrinter"
              label="Cola de tickets"
              field="ticket_printer_setup"
              :options="printerOptions"
              :disabled="!qzReady || !printerOptions.length"
              placeholder="Selecciona la impresora de tickets"
            />

            <div class="rounded-2xl bg-secondary p-4 text-xs leading-5 text-text">
              <strong>Configurada:</strong>
              <span class="ml-1">{{ savedTicketPrinter || "Ninguna" }}</span>
              <p v-if="savedTicketPrinter" class="mt-1 font-semibold" :class="ticketAvailable ? 'text-emerald-600' : 'text-amber-600'">
                {{ ticketAvailable ? "Disponible en Windows" : "Guardada, pero no disponible actualmente" }}
              </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
              <button
                type="button"
                class="min-h-11 flex-1 rounded-2xl bg-primary px-4 py-3 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!selectedTicketPrinter"
                @click="savePrinterRole('ticket')"
              >
                Guardar tickets
              </button>
              <button
                type="button"
                class="min-h-11 flex-1 rounded-2xl border border-primary px-4 py-3 text-sm font-black text-primary disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="printingTicket || !ticketAvailable"
                @click="printTicketTest"
              >
                {{ printingTicket ? "Imprimiendo..." : "Probar ticket" }}
              </button>
            </div>
          </div>
        </article>

        <article class="rounded-3xl border border-secondary bg-background p-6 shadow-sm">
          <div class="flex items-start gap-3">
            <div class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-secondary text-primary">
              <span class="material-symbols-outlined">barcode</span>
            </div>
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary">Paso 3</p>
              <h2 class="mt-1 text-lg font-black text-text">Impresora de etiquetas</h2>
              <p class="mt-1 text-sm leading-5 text-text opacity-70">Las etiquetas de productos se enviarán únicamente a esta cola.</p>
            </div>
          </div>

          <div class="mt-5 space-y-4">
            <SelectField
              v-model="selectedLabelPrinter"
              label="Cola de etiquetas"
              field="label_printer_setup"
              :options="printerOptions"
              :disabled="!qzReady || !printerOptions.length"
              placeholder="Selecciona la impresora de etiquetas"
            />

            <div class="rounded-2xl bg-secondary p-4 text-xs leading-5 text-text">
              <strong>Configurada:</strong>
              <span class="ml-1">{{ savedLabelPrinter || "Ninguna" }}</span>
              <p v-if="savedLabelPrinter" class="mt-1 font-semibold" :class="labelAvailable ? 'text-emerald-600' : 'text-amber-600'">
                {{ labelAvailable ? "Disponible en Windows" : "Guardada, pero no disponible actualmente" }}
              </p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
              <button
                type="button"
                class="min-h-11 flex-1 rounded-2xl bg-primary px-4 py-3 text-sm font-black text-white disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="!selectedLabelPrinter"
                @click="savePrinterRole('label')"
              >
                Guardar etiquetas
              </button>
              <button
                type="button"
                class="min-h-11 flex-1 rounded-2xl border border-primary px-4 py-3 text-sm font-black text-primary disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="printingLabel || !labelAvailable"
                @click="printLabelTest"
              >
                {{ printingLabel ? "Imprimiendo..." : "Probar etiqueta" }}
              </button>
            </div>
          </div>
        </article>
      </div>

      <article class="rounded-3xl border border-primary/20 bg-secondary p-5 text-sm leading-6 text-text">
        <p class="font-black">Esta configuración pertenece a esta computadora y a este navegador.</p>
        <p class="mt-1 opacity-75">
          Permanece después de reiniciar Windows. Si se borran los datos del navegador o se usa otro usuario, será necesario volver a elegir las dos impresoras.
        </p>
      </article>
    </section>
  </PageLayout>
</template>
