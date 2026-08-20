<script setup>
import { computed, reactive, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import { GlobalTable } from "@/Components/Tables";
import { GlobalToolbar } from "@/Components/Toolbars";
import { GlobalModal, getModalRequestOptions } from "@/Components/Modales";
import TextareaField from "@/Components/Forms/TextareaField.vue";
import MetricCard from "@/Components/Cards/MetricCard.vue";
import {
  ErrorAlert,
  ToastAlert,
} from "@/Components/Modales/UniversalActionModal";
import {
  connectQzTray,
  getDefaultQzPrinter,
  getQzPrinters,
  getStoredPrinterName,
  printEscPosTicket,
  saveStoredPrinterName,
} from "@/Composables/useQzTray";
import {
  buildEscPosTicketData,
  getStoredTicketTemplateSettings,
  normalizeTicketTemplate,
} from "@/config/ticketTemplate";

defineOptions({
  layout: AdminLayout,
});

const props = defineProps({
  sales: { type: Object, default: () => ({ data: [] }) },
  branchesDB: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  ticketTemplate: { type: Object, default: null },
});

const page = usePage();
const selectedSale = ref(null);
const cancellationSale = ref(null);
const availablePrinters = ref([]);
const selectedPrinterName = ref(getStoredPrinterName());
const printerBridgeReady = ref(false);
const printerBridgeMessage = ref("Conecta QZ Tray para reimprimir tickets.");
const TICKET_LOGO_URL = "/icons/super-kay-ticket-bw.png";
let ticketLogoDataUrlPromise = null;
const ticketHeaderDataUrlPromises = new Map();
let filterRefreshTimeout = null;

const filtersState = reactive({
  search: props.filters?.search || "",
  branchId: props.filters?.branch_id || "",
  status: props.filters?.status || "",
  dateFrom: props.filters?.date_from || "",
  dateTo: props.filters?.date_to || "",
});

const cancellationForm = useForm({
  reason: "",
});

const columns = [
  { key: "folio", label: "Folio", format: "text", mobileSecondary: true },
  { key: "date_display", label: "Fecha", format: "text", mobileDisplay: true },
  { key: "branch", label: "Sucursal", format: "text", mobileDisplay: true },
  { key: "seller", label: "Vendedor", format: "text", mobileDisplay: true },
  { key: "payment_method", label: "Pago", format: "text", mobileDisplay: true },
  { key: "cash_box_number", label: "Caja", format: "text", mobileDisplay: true },
  { key: "status_label", label: "Estado", format: "text", mobileDisplay: true },
  { key: "total", label: "Total", format: "currency", mobileDisplay: true },
];

const detailColumns = [
  { key: "product", label: "Producto", format: "text", mobileSecondary: true },
  { key: "code", label: "Codigo", format: "text", mobileDisplay: true },
  { key: "quantity_display", label: "Cantidad", format: "text", mobileDisplay: true },
  { key: "unit_price", label: "Precio", format: "currency", mobileDisplay: true },
  { key: "discount_amount", label: "Descuento", format: "currency", mobileDisplay: true },
  { key: "subtotal", label: "Subtotal", format: "currency", mobileDisplay: true },
];

const actions = [
  { id: "view", label: "Ver", icon: "visibility", variant: "blue", permission: "sales.view" },
  { id: "reprint", label: "Reimprimir", icon: "print", variant: "green", permission: "sales.view" },
  {
    id: "cancel",
    label: "Cancelar",
    icon: "undo",
    variant: "red",
    permission: "sales.update",
    hidden: (row) => !row.can_cancel,
  },
];

const statusOptions = [
  { value: "completed", label: "Completadas" },
  { value: "cancelled", label: "Canceladas" },
];

const resolvedTicketTemplate = computed(() =>
  normalizeTicketTemplate(
    props.ticketTemplate?.settings || getStoredTicketTemplateSettings() || {}
  )
);

const toolbarConfig = computed(() => ({
  icon: "receipt_long",
  title: "Historial de ventas",
  subtitle: "Consulta tickets, revisa detalle, reimprime o cancela ventas autorizadas.",
  search: filtersState.search,
  searchPlaceholder: "Buscar folio, producto o vendedor",
  showSearch: true,
  compactFilters: false,
  filters: [
    {
      key: "branchId",
      label: "Sucursal",
      placeholder: "Todas las sucursales",
      value: filtersState.branchId,
      options: props.branchesDB,
      optionLabel: "name",
      optionValue: "id",
    },
    {
      key: "status",
      label: "Estado",
      placeholder: "Todos los estados",
      value: filtersState.status,
      options: statusOptions,
    },
    {
      key: "dateFrom",
      label: "Desde",
      type: "date",
      value: filtersState.dateFrom,
    },
    {
      key: "dateTo",
      label: "Hasta",
      type: "date",
      value: filtersState.dateTo,
    },
  ],
  actions: [
    {
      id: "clear",
      label: "Limpiar",
      icon: "restart_alt",
      variant: "slate",
    },
  ],
  totalRecords: props.sales?.total || 0,
  filteredRecords: props.sales?.total || 0,
  showCounter: true,
}));

function money(value) {
  return new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
  }).format(Number(value || 0));
}

function currentFilterPayload() {
  return {
    search: filtersState.search || undefined,
    branch_id: filtersState.branchId || undefined,
    status: filtersState.status || undefined,
    date_from: filtersState.dateFrom || undefined,
    date_to: filtersState.dateTo || undefined,
  };
}

function applyFilters() {
  router.get(route("ventas.history"), currentFilterPayload(), {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
}

function scheduleFilterRefresh() {
  if (filterRefreshTimeout) {
    window.clearTimeout(filterRefreshTimeout);
  }

  filterRefreshTimeout = window.setTimeout(() => {
    applyFilters();
  }, 350);
}

watch(filtersState, scheduleFilterRefresh, { deep: true });

function updateSearch(value) {
  filtersState.search = value || "";
}

function updateFilter({ key, value }) {
  if (!Object.prototype.hasOwnProperty.call(filtersState, key)) return;
  filtersState[key] = value || "";
}

function clearFilters() {
  filtersState.search = "";
  filtersState.branchId = "";
  filtersState.status = "";
  filtersState.dateFrom = "";
  filtersState.dateTo = "";
}

function handleToolbarAction(actionId) {
  if (actionId === "clear") {
    clearFilters();
  }
}

function handlePageChange(url) {
  router.get(url, {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
}

function handleTableAction({ action, row }) {
  if (action === "view") {
    selectedSale.value = row;
    return;
  }

  if (action === "reprint") {
    void reprintSaleTicket(row);
    return;
  }

  if (action === "cancel") {
    openCancellationModal(row);
  }
}

function closeSaleModal() {
  selectedSale.value = null;
}

function openCancellationModal(row) {
  if (!row?.can_cancel) {
    ErrorAlert({
      title: "Ticket ya cancelado",
      message: "Esta venta ya tiene una cancelacion registrada y no puede cancelarse otra vez.",
    });
    return;
  }

  cancellationSale.value = row;
  cancellationForm.reason = "";
  cancellationForm.clearErrors();
}

function closeCancellationModal() {
  cancellationSale.value = null;
  cancellationForm.clearErrors();
}

function saveCancellation() {
  if (!cancellationSale.value?.can_cancel || cancellationForm.processing) return;

  cancellationForm.post(route("ventas.cancel", { sale: cancellationSale.value.id }), getModalRequestOptions({
    entityName: "Cancelacion de ticket",
    successTitle: "Ticket cancelado correctamente",
    errorTitle: "No se pudo cancelar el ticket",
    errorMessage: "Revisa el motivo capturado y que la venta siga activa.",
    preserveScroll: true,
    onSuccess: () => {
      closeCancellationModal();
      selectedSale.value = null;
      applyFilters();
    },
  }));
}

function blobToDataUrl(blob) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result || ""));
    reader.onerror = () => reject(reader.error || new Error("No se pudo leer el logo del ticket."));
    reader.readAsDataURL(blob);
  });
}

async function getTicketLogoDataUrl() {
  if (typeof window === "undefined") return "";

  if (!ticketLogoDataUrlPromise) {
    ticketLogoDataUrlPromise = window.fetch(TICKET_LOGO_URL, { cache: "force-cache" })
      .then((response) => response.ok ? response.blob() : null)
      .then((blob) => blob ? blobToDataUrl(blob) : "")
      .catch(() => "");
  }

  return ticketLogoDataUrlPromise;
}

function imageFromDataUrl(dataUrl) {
  return new Promise((resolve, reject) => {
    const image = new Image();
    image.onload = () => resolve(image);
    image.onerror = () => reject(new Error("No se pudo preparar el encabezado del ticket."));
    image.src = dataUrl;
  });
}

async function getTicketHeaderDataUrl(cashBoxText = "") {
  const normalizedCashBoxText = String(cashBoxText || "").trim();
  const cacheKey = normalizedCashBoxText || "__default__";

  if (ticketHeaderDataUrlPromises.has(cacheKey)) {
    return ticketHeaderDataUrlPromises.get(cacheKey);
  }

  const promise = getTicketLogoDataUrl()
    .then(async (logoDataUrl) => {
      if (!logoDataUrl || typeof document === "undefined") return logoDataUrl;

      const logoImage = await imageFromDataUrl(logoDataUrl);
      const canvas = document.createElement("canvas");
      canvas.width = 576;
      canvas.height = 92;
      const context = canvas.getContext("2d");

      if (!context) return logoDataUrl;

      context.fillStyle = "#ffffff";
      context.fillRect(0, 0, canvas.width, canvas.height);
      context.drawImage(logoImage, 12, 5, 82, 82);
      context.fillStyle = "#000000";
      context.font = "700 27px Arial, sans-serif";
      context.textAlign = "center";
      context.textBaseline = "middle";
      context.fillText("SUPER KAY", Math.round(canvas.width / 2), Math.round(canvas.height / 2));

      if (normalizedCashBoxText) {
        context.font = "700 21px Arial, sans-serif";
        context.textAlign = "right";
        context.fillText(normalizedCashBoxText.toUpperCase(), canvas.width - 12, Math.round(canvas.height / 2));
      }

      return canvas.toDataURL("image/png");
    })
    .catch(() => "");

  ticketHeaderDataUrlPromises.set(cacheKey, promise);

  return promise;
}

async function initializePrinterBridge() {
  try {
    await connectQzTray();
    const printers = await getQzPrinters();
    availablePrinters.value = printers;

    let printerName = selectedPrinterName.value || getStoredPrinterName();
    if (!printerName || !printers.includes(printerName)) {
      try {
        const defaultPrinter = await getDefaultQzPrinter();
        printerName = printers.includes(defaultPrinter) ? defaultPrinter : "";
      } catch (error) {
        printerName = "";
      }
    }

    if (!printerName || !printers.includes(printerName)) {
      printerName = printers[0] || "";
    }

    selectedPrinterName.value = printerName;
    saveStoredPrinterName(printerName);
    printerBridgeReady.value = true;
    printerBridgeMessage.value = printerName
      ? `Impresora lista: ${printerName}`
      : "QZ Tray conectado. Selecciona una impresora.";
  } catch (error) {
    printerBridgeReady.value = false;
    availablePrinters.value = [];
    printerBridgeMessage.value = error?.message || "QZ Tray no esta conectado.";
    throw error;
  }
}

async function printTicket(printJob) {
  if (!selectedPrinterName.value || !printerBridgeReady.value) {
    await initializePrinterBridge();
  }

  if (!selectedPrinterName.value) {
    throw new Error("No hay una impresora seleccionada para el ticket.");
  }

  const cashBoxNumber = String(printJob?.cash_box_number || "1");
  const cashBoxText = `CAJA #${cashBoxNumber}`;
  const resolvedPrintJob = {
    ...printJob,
    user_name: page.props.auth?.user?.name || printJob?.user_name || printJob?.employee_name || "",
    cash_box_number: cashBoxNumber,
    cash_box_text: cashBoxText,
    ticket_logo_data_url: printJob?.ticket_logo_data_url || await getTicketLogoDataUrl(),
    ticket_header_data_url: printJob?.ticket_header_data_url || await getTicketHeaderDataUrl(cashBoxText),
  };

  await printEscPosTicket(
    selectedPrinterName.value,
    buildEscPosTicketData(resolvedTicketTemplate.value, resolvedPrintJob),
    { timeoutMs: 10000 }
  );
}

async function reprintSaleTicket(sale) {
  if (!sale?.id) return;

  try {
    const { data } = await window.axios.get(route("ventas.ticket", { sale: sale.id }));
    const printJob = data?.print_job;

    if (!printJob) {
      throw new Error("El servidor no devolvio la informacion del ticket.");
    }

    await printTicket(printJob);
    ToastAlert({ title: `Ticket ${sale.folio} reenviado a la impresora` });
  } catch (error) {
    ErrorAlert({
      title: "No se pudo reimprimir",
      message: error?.response?.data?.message || error?.message || "QZ Tray no pudo enviar el ticket a la impresora seleccionada.",
    });
  }
}
</script>

<template>
  <Head title="Historial de ventas" />

  <PageLayout>
    <template #toolbar>
      <GlobalToolbar
        v-bind="toolbarConfig"
        @update:search="updateSearch"
        @update:filter="updateFilter"
        @action="handleToolbarAction"
      />
    </template>

    <section class="space-y-5">
      <div class="rounded-2xl border border-secondary bg-background px-4 py-3 text-sm text-text shadow-sm">
        <p class="font-black">{{ selectedPrinterName || "Sin impresora seleccionada" }}</p>
        <p class="mt-1 text-xs opacity-70">{{ printerBridgeMessage }}</p>
      </div>

      <GlobalTable
        :items="sales.data || []"
        :columns="columns"
        :actions="actions"
        :pagination="sales"
        row-key="id"
        mobile-card-header-field="folio"
        no-data-message="No hay ventas que coincidan con los filtros."
        @page-change="handlePageChange"
        @action="handleTableAction"
      />
    </section>

    <GlobalModal
      v-if="selectedSale"
      title="Detalle de ticket"
      :subtitle="selectedSale.folio"
      mode="view"
      size="5xl"
      :columns="1"
      :show-save="false"
      close-button-text="Cerrar"
      @close="closeSaleModal"
    >
      <div class="space-y-5">
        <section class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
          <MetricCard label="Fecha" :value="selectedSale.date_display" size="sm" />
          <MetricCard label="Sucursal" :value="selectedSale.branch" size="sm" />
          <MetricCard label="Estado" :value="selectedSale.status_label" :tone="selectedSale.status === 'cancelled' ? 'danger' : 'success'" size="sm" />
          <MetricCard label="Total" :value="money(selectedSale.total)" tone="dark" size="sm" />
        </section>

        <GlobalTable
          :items="selectedSale.details || []"
          :columns="detailColumns"
          :actions="[]"
          row-key="id"
          mobile-card-header-field="product"
          no-data-message="Esta venta no tiene productos registrados."
          :show-pagination="false"
        />

        <section
          v-if="selectedSale.cancellation"
          class="rounded-2xl border border-primary/30 bg-primary/5 p-4 text-sm text-text"
        >
          <p class="font-black">Cancelacion registrada</p>
          <div class="mt-2 grid gap-2 md:grid-cols-3">
            <p><span class="font-semibold opacity-60">Fecha:</span> {{ selectedSale.cancellation.cancelled_at_display }}</p>
            <p><span class="font-semibold opacity-60">Usuario:</span> {{ selectedSale.cancellation.cancelled_by }}</p>
            <p><span class="font-semibold opacity-60">Monto:</span> {{ money(selectedSale.cancellation.amount) }}</p>
          </div>
          <p class="mt-3 whitespace-pre-line">{{ selectedSale.cancellation.reason }}</p>
        </section>
      </div>
    </GlobalModal>

    <GlobalModal
      v-if="cancellationSale"
      title="Cancelar ticket"
      :subtitle="`${cancellationSale.folio} · ${money(cancellationSale.total)}`"
      mode="delete"
      size="2xl"
      :columns="1"
      :processing="cancellationForm.processing"
      :total-errors="Object.keys(cancellationForm.errors).length"
      save-button-text="Cancelar ticket"
      close-button-text="Conservar venta"
      @close="closeCancellationModal"
      @save="saveCancellation"
    >
      <div class="space-y-4">
        <section class="rounded-2xl border border-secondary bg-secondary p-4 text-sm text-text">
          <p class="font-black">Productos que se devolveran al inventario</p>
          <p class="mt-1 opacity-65">
            La devolucion se registrara con la fecha actual y quedara vinculada a este ticket.
          </p>
        </section>

        <GlobalTable
          :items="cancellationSale.details || []"
          :columns="detailColumns"
          :actions="[]"
          row-key="id"
          mobile-card-header-field="product"
          no-data-message="Esta venta no tiene productos registrados."
          :show-pagination="false"
        />

        <TextareaField
          v-model="cancellationForm.reason"
          label="Motivo de cancelacion"
          field="reason"
          :rows="4"
          placeholder="Ej. Botella rota, producto caducado, devolucion autorizada."
          :error="cancellationForm.errors.reason"
        />
      </div>
    </GlobalModal>
  </PageLayout>
</template>
