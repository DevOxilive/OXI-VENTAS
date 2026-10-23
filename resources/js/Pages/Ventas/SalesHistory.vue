<script setup>
import { renderTicketHeaderImage } from "@/config/ticketTemplate";
import { computed, onMounted, reactive, ref, watch } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import { GlobalTable } from "@/Components/Tables";
import { GlobalToolbar } from "@/Components/Toolbars";
import { GlobalModal, getModalRequestOptions } from "@/Components/Modales";
import TextareaField from "@/Components/Forms/TextareaField.vue";
import {
  ErrorAlert,
  ToastAlert,
} from "@/Components/Modales/UniversalActionModal";
import {
  connectQzTray,
  findTicketPrinter,
  getQzPrinters,
  getStoredTicketPrinterName,
  printEscPosTicket,
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
const historySale = ref(null);
const historyTab = ref('applied');
const historyReturns = computed(() => (historySale.value?.returns || []).filter((item) => Boolean(item.is_voided) === (historyTab.value === 'voided')));
const historySections = computed(() => [
  { id: 'applied', label: `Devoluciones aplicadas (${(historySale.value?.returns || []).filter(item => !item.is_voided).length})` },
  { id: 'voided', label: `Devoluciones anuladas (${(historySale.value?.returns || []).filter(item => item.is_voided).length})` },
]);
const cancellationSale = ref(null);
const returnToVoid = ref(null);
const selectedPrinterName = ref(getStoredTicketPrinterName());
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
  paymentMethod: props.filters?.payment_method || "",
  dateFrom: props.filters?.date_from || "",
  dateTo: props.filters?.date_to || "",
});

const cancellationForm = useForm({
  reason: "",
  items: [],
});
const voidReturnForm = useForm({ reason: "" });
const canVoidReturns = computed(() => (page.props.auth?.permissions || []).includes("sales.returns.void"));

const returnQuantities = reactive({});

const columns = [
  { key: "folio", label: "Folio", format: "text", mobileSecondary: true },
  { key: "date_display", label: "Fecha", format: "text", mobileDisplay: true },
  { key: "branch", label: "Sucursal", format: "text", mobileDisplay: true },
  { key: "holder", label: "Cliente / empleado", format: "text", mobileDisplay: true },
  { key: "seller", label: "Registró", format: "text", mobileDisplay: true },
  { key: "payment_method", label: "Pago", format: "text", mobileDisplay: true },
  { key: "cash_box_number", label: "Caja", format: "text", mobileDisplay: true },
  {
    key: "visual_status",
    label: "Estado",
    format: "badge",
    mobileBadge: true,
    formatOptions: {
      labelMap: {
        completed: "Completada",
        partial_return: "Devolución parcial",
        returned: "Devolución total",
        payment: "Abono aplicado",
      },
      statusMap: {
        completed: "green",
        partial_return: "amber",
        returned: "red",
        payment: "blue",
      },
    },
  },
  { key: "total", label: "Total", format: "currency", mobileDisplay: true },
];

const actions = [
  { id: "view", label: "Ver", icon: "visibility", variant: "blue", permission: "sales.view" },
  { id: "returns", label: "Historial de devoluciones", icon: "history", variant: "blue", permission: "sales.view", hidden: (row) => row.operation_type === "payment" },
  { id: "reprint", label: "Reimprimir", icon: "print", variant: "green", permission: "sales.view", hidden: (row) => row.operation_type === "payment" },
  {
    id: "cancel",
    label: "Registrar devolución",
    icon: "undo",
    variant: "red",
    permission: "sales.update",
    hidden: (row) => !row.can_cancel,
  },
];

const statusOptions = [
  { value: "completed", label: "Completadas" },
  { value: "cancelled", label: "Canceladas" },
  { value: "payment", label: "Abonos aplicados" },
];

const paymentMethodOptions = [
  { value: "cash", label: "Efectivo" },
  { value: "card", label: "Tarjeta" },
  { value: "credit", label: "Crédito empleado" },
];

const resolvedTicketTemplate = computed(() =>
  normalizeTicketTemplate(
    props.ticketTemplate?.settings || getStoredTicketTemplateSettings() || {}
  )
);

const toolbarConfig = computed(() => ({
  icon: "receipt_long",
  title: "Historial de ventas",
  subtitle: "Consulta ventas, abonos, titulares y usuarios que recibieron el dinero.",
  search: filtersState.search,
  searchPlaceholder: "Buscar folio, producto, titular o usuario",
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
      key: "paymentMethod",
      label: "Forma de pago",
      placeholder: "Todas las formas",
      value: filtersState.paymentMethod,
      options: paymentMethodOptions,
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
  actions: [],
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
    payment_method: filtersState.paymentMethod || undefined,
    date_from: filtersState.dateFrom || undefined,
    date_to: filtersState.dateTo || undefined,
  };
}

function salesHistoryUrl() {
  try {
    return typeof route === "function" && route().has("ventas.history")
      ? route("ventas.history")
      : "/ventas/historial";
  } catch {
    return "/ventas/historial";
  }
}

function applyFilters() {
  router.get(salesHistoryUrl(), currentFilterPayload(), {
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

function handlePageChange(url) {
  router.get(url, {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
}

function handleTableAction({ action, row }) {
  if (action === "returns") {
    historySale.value = row;
    historyTab.value = 'applied';
    return;
  }
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
      message: "Este ticket ya no tiene productos pendientes por devolver.",
    });
    return;
  }

  cancellationSale.value = row;
  cancellationForm.reason = "";
  cancellationForm.items = [];
  Object.keys(returnQuantities).forEach((key) => delete returnQuantities[key]);
  (row.details || []).forEach((detail) => {
    returnQuantities[detail.id] = 0;
  });
  cancellationForm.clearErrors();
}

function closeCancellationModal() {
  cancellationSale.value = null;
  Object.keys(returnQuantities).forEach((key) => delete returnQuantities[key]);
  cancellationForm.clearErrors();
}

function openVoidReturn(returnedSale) {
  if (!returnedSale?.can_void || !canVoidReturns.value) return;
  returnToVoid.value = returnedSale;
  voidReturnForm.reason = "";
  voidReturnForm.clearErrors();
}

function closeVoidReturnModal() {
  returnToVoid.value = null;
  voidReturnForm.reset();
  voidReturnForm.clearErrors();
}

function saveVoidReturn() {
  if (!historySale.value?.id || !returnToVoid.value?.id || voidReturnForm.processing) return;

  voidReturnForm.patch(route("ventas.returns.void", {
    sale: historySale.value.id,
    cancellation: returnToVoid.value.id,
  }), getModalRequestOptions({
    entityName: "Devolución",
    successTitle: "Devolución anulada correctamente",
    errorTitle: "No se pudo anular la devolución",
    errorMessage: "Revisa el motivo y confirma que la devolución siga activa.",
    preserveScroll: true,
    onSuccess: () => {
      closeVoidReturnModal();
      historySale.value = null;
      selectedSale.value = null;
      applyFilters();
    },
  }));
}

function saveCancellation() {
  if (!cancellationSale.value?.can_cancel || cancellationForm.processing) return;

  cancellationForm.items = (cancellationSale.value.details || [])
    .map((detail) => ({
      sale_detail_id: detail.id,
      quantity: Number(returnQuantities[detail.id] || 0),
    }))
    .filter((item) => item.quantity > 0);

  if (!cancellationForm.items.length) {
    ErrorAlert({
      title: "Selecciona productos",
      message: "Captura una cantidad mayor a cero en al menos un producto para registrar la devolución.",
    });
    return;
  }

  cancellationForm.post(route("ventas.cancel", { sale: cancellationSale.value.id }), getModalRequestOptions({
    entityName: "Cancelacion de ticket",
    successTitle: "Devolución registrada correctamente",
    errorTitle: "No se pudo registrar la devolución",
    errorMessage: "Revisa las cantidades, el motivo y que el ticket siga activo.",
    preserveScroll: true,
    onSuccess: (pageResponse) => {
      const printJob = pageResponse?.props?.flash?.sale_cancellation?.print_job;
      closeCancellationModal();
      selectedSale.value = null;
      if (printJob) {
        void printReturnTicket(printJob, "Devolución registrada, pero no se pudo imprimir");
      }
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

async function getTicketHeaderDataUrl(cashBoxText = "", settings = resolvedTicketTemplate.value) {
  const cacheKey = JSON.stringify([settings, cashBoxText]);
  if (!ticketHeaderDataUrlPromises.has(cacheKey)) {
    const promise = getTicketLogoDataUrl().then(async (logoDataUrl) => {
      if (!logoDataUrl || typeof document === "undefined") return logoDataUrl;
      return renderTicketHeaderImage(settings, await imageFromDataUrl(logoDataUrl), cashBoxText);
    }).catch(() => "");
    ticketHeaderDataUrlPromises.set(cacheKey, promise);
  }
  return ticketHeaderDataUrlPromises.get(cacheKey);
}

async function initializePrinterBridge() {
  try {
    await connectQzTray();
    const printers = await getQzPrinters({
      expectedPrinterNames: [selectedPrinterName.value || getStoredTicketPrinterName()],
    });
    selectedPrinterName.value = findTicketPrinter(printers);
    printerBridgeReady.value = true;
    printerBridgeMessage.value = selectedPrinterName.value
      ? `Impresora lista: ${selectedPrinterName.value}`
      : "QZ Tray conectado, pero no encontro una impresora de tickets identificada.";
  } catch (error) {
    printerBridgeReady.value = false;
    printerBridgeMessage.value = error?.message || "QZ Tray no esta conectado.";
    throw error;
  }
}

onMounted(() => {
  void initializePrinterBridge().catch(() => {});
});

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
    ticket_header_data_url: printJob?.ticket_header_data_url || await getTicketHeaderDataUrl(cashBoxText, printJob.template_settings || resolvedTicketTemplate.value),
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

async function printReturnTicket(printJob, errorTitle = "No se pudo imprimir la devolución") {
  try {
    await printTicket(printJob);
    ToastAlert({ title: `Comprobante ${printJob.folio || "de devolución"} enviado a la impresora` });
  } catch (error) {
    ErrorAlert({
      title: errorTitle,
      message: `${error?.message || "QZ Tray no pudo enviar el comprobante."} La devolución quedó guardada y puede reimprimirse desde el historial.`,
    });
  }
}

async function reprintReturnTicket(sale, returnedSale) {
  if (!sale?.id || !returnedSale?.id) return;

  try {
    const { data } = await window.axios.get(route("ventas.returns.ticket", {
      sale: sale.id,
      cancellation: returnedSale.id,
    }));
    const printJob = data?.print_job;

    if (!printJob) throw new Error("El servidor no devolvió la información del comprobante.");
    await printReturnTicket(printJob);
  } catch (error) {
    ErrorAlert({
      title: "No se pudo reimprimir la devolución",
      message: error?.response?.data?.message || error?.message || "No fue posible recuperar el comprobante.",
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
        no-data-message="No hay operaciones que coincidan con los filtros."
        @page-change="handlePageChange"
        @action="handleTableAction"
      />
    </section>

    <GlobalModal
      v-if="selectedSale"
      :title="selectedSale.operation_type === 'payment' ? 'Detalle del abono' : 'Detalle de ticket'"
      :subtitle="selectedSale.folio"
      mode="view"
      size="md"
      height="auto"
      :columns="1"
      :show-save="false"
      close-button-text="Cerrar"
      @close="closeSaleModal"
    >
      <div class="space-y-5">
        <section class="flex flex-wrap items-center justify-between gap-2 text-sm text-text">
          <div><p>{{ selectedSale.date_display }}</p><p class="text-xs opacity-60">{{ selectedSale.branch }}</p></div>
          <span class="rounded-full bg-secondary px-3 py-1 text-xs font-semibold">{{ selectedSale.status_label }}</span>
        </section>

        <section v-if="selectedSale.operation_type === 'payment'" class="rounded-2xl border border-secondary bg-secondary/30 p-4 text-sm text-text">
          <div class="grid gap-2 md:grid-cols-2">
            <p><span class="font-semibold opacity-60">Titular:</span> {{ selectedSale.holder }}</p>
            <p><span class="font-semibold opacity-60">Recibió:</span> {{ selectedSale.seller }}</p>
            <p><span class="font-semibold opacity-60">Forma de pago:</span> {{ selectedSale.payment_method }}</p>
            <p><span class="font-semibold opacity-60">Caja:</span> #{{ selectedSale.cash_box_number }}</p>
            <p v-if="Number(selectedSale.change_due || 0) > 0"><span class="font-semibold opacity-60">Efectivo recibido:</span> {{ money(selectedSale.cash_received) }}</p>
            <p v-if="Number(selectedSale.change_due || 0) > 0"><span class="font-semibold opacity-60">Cambio:</span> {{ money(selectedSale.change_due) }}</p>
          </div>
          <div class="mt-3 border-t border-secondary pt-3">
            <p class="font-black">Tickets liquidados o abonados</p>
            <p v-if="!selectedSale.applied_sales?.length" class="mt-2 opacity-65">Este abono histórico no tiene un ticket vinculado.</p>
            <div v-else class="mt-2 flex flex-wrap gap-2">
              <span v-for="applied in selectedSale.applied_sales" :key="applied.id" class="rounded-full bg-background px-3 py-1 font-semibold">
                {{ applied.folio }} · {{ money(applied.amount) }}
              </span>
            </div>
          </div>
        </section>

        <table v-if="selectedSale.operation_type !== 'payment'" class="w-full table-fixed text-sm text-text">
          <thead class="border-b border-secondary text-left text-xs opacity-60"><tr><th class="w-1/2 pb-2 font-medium">Producto</th><th class="pb-2 text-center font-medium">Cant.</th><th class="pb-2 text-right font-medium">Importe</th></tr></thead>
          <tbody class="divide-y divide-secondary">
            <tr v-for="item in selectedSale.details || []" :key="item.id">
              <td class="py-3 pr-3 break-words"><p class="font-medium">{{ item.product }}</p><p class="text-xs opacity-60">{{ item.code }} · {{ money(item.unit_price) }}</p><p v-if="Number(item.discount_amount) > 0" class="text-xs opacity-60">Descuento: {{ money(item.discount_amount) }}</p></td>
              <td class="py-3 text-center align-top">{{ item.quantity_display }}<span v-if="Number(item.returned_quantity) > 0" class="block text-xs text-primary">{{ item.returned_quantity }} devueltos</span></td>
              <td class="py-3 text-right align-top tabular-nums">{{ money(item.subtotal) }}</td>
            </tr>
          </tbody>
        </table>
        <dl class="ml-auto w-full max-w-xs space-y-2 border-t border-secondary pt-3 text-sm text-text">
          <div class="flex justify-between"><dt>Total</dt><dd class="tabular-nums">{{ money(selectedSale.total) }}</dd></div>
          <template v-if="selectedSale.returned_amount > 0">
            <div class="flex justify-between text-primary"><dt>Devuelto</dt><dd class="tabular-nums">−{{ money(selectedSale.returned_amount) }}</dd></div>
            <div class="flex justify-between font-bold"><dt>Neto</dt><dd class="tabular-nums">{{ money(selectedSale.effective_total) }}</dd></div>
          </template>
        </dl>
      </div>
    </GlobalModal>

    <GlobalModal
      v-if="historySale"
      title="Historial de devoluciones"
      :subtitle="historySale.folio"
      mode="view"
      size="md"
      height="auto"
      :columns="1"
      :show-save="false"
      :sections="historySections"
      :active-section="historyTab"
      close-button-text="Cerrar"
      @select-section="historyTab = $event"
      @close="historySale = null"
    >
        <section class="text-sm text-text">
          <p v-if="!historyReturns.length" class="py-6 text-center text-sm opacity-60">Sin devoluciones {{ historyTab === 'voided' ? 'anuladas' : 'aplicadas' }}</p>
          <article v-for="returnedSale in historyReturns" :key="returnedSale.id" class="border-b border-secondary py-4 first:pt-0 last:border-0 last:pb-0">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-black">{{ returnedSale.folio }}</p>
                  <span v-if="returnedSale.is_voided" class="rounded-full bg-secondary px-2 py-1 text-[10px] font-black uppercase text-primary">Anulada</span>
                </div>
                <p class="mt-1 text-xs opacity-60">{{ returnedSale.cancelled_at_display }} · {{ returnedSale.cancelled_by }}</p>
              </div>
              <div class="flex flex-wrap gap-2">
                <button type="button" class="inline-flex items-center gap-2 rounded-lg px-2 py-1 font-medium text-primary transition hover:bg-secondary" @click="reprintReturnTicket(historySale, returnedSale)">
                  <span class="material-symbols-outlined text-[18px]">print</span>
                  Reimprimir
                </button>
                <button v-if="returnedSale.can_void && canVoidReturns" type="button" class="inline-flex items-center gap-2 rounded-xl bg-primary px-3 py-2 font-bold text-white transition hover:opacity-85" @click="openVoidReturn(returnedSale)">
                  <span class="material-symbols-outlined text-[18px]">undo</span>
                  Anular
                </button>
              </div>
            </div>
            <div class="mt-3 flex flex-wrap justify-between gap-2">
              <p class="opacity-60">{{ returnedSale.application_label }}</p>
              <p class="font-semibold tabular-nums">{{ money(returnedSale.amount) }}</p>
            </div>
            <div v-if="returnedSale.account_balance_before !== null" class="mt-2 grid gap-2 md:grid-cols-2">
              <p><span class="font-semibold opacity-60">Adeudo anterior:</span> {{ money(returnedSale.account_balance_before) }}</p>
              <p><span class="font-semibold opacity-60">Adeudo nuevo:</span> {{ money(returnedSale.account_balance_after) }}</p>
            </div>
            <div class="mt-3">
              <table class="w-full table-fixed text-xs">
                <thead class="border-b border-secondary text-left"><tr><th class="w-1/2 py-2 font-medium">Producto</th><th class="px-2 py-2 font-medium">Cantidad</th><th class="py-2 text-right font-medium">Importe</th></tr></thead>
                <tbody class="divide-y divide-primary/10">
                  <tr v-for="(item, itemIndex) in returnedSale.items" :key="`${returnedSale.id}-${itemIndex}`">
                    <td class="py-2 pr-2 break-words"><span>{{ item.product }}</span><span class="block opacity-60">{{ item.code }}</span></td>
                    <td class="px-3 py-2">{{ item.quantity_display }}</td>
                    <td class="px-3 py-2 text-right">{{ money(item.subtotal) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="mt-2 whitespace-pre-line">{{ returnedSale.reason }}</p>
            <div v-if="returnedSale.is_voided" class="mt-3 border-l-2 border-primary/30 pl-3 text-xs">
              <p class="font-black">Anulada por {{ returnedSale.voided_by || 'Sin usuario' }} · {{ returnedSale.voided_at_display || '-' }}</p>
              <p class="mt-1 whitespace-pre-line">{{ returnedSale.void_reason }}</p>
            </div>
          </article>
        </section>
    </GlobalModal>

    <GlobalModal
      v-if="cancellationSale"
      title="Registrar devolución"
      :subtitle="`${cancellationSale.folio} · ${money(cancellationSale.total)}`"
      mode="delete"
      size="md"
      height="auto"
      :columns="1"
      :processing="cancellationForm.processing"
      :total-errors="Object.keys(cancellationForm.errors).length"
      save-button-text="Confirmar devolución"
      close-button-text="Conservar venta"
      @close="closeCancellationModal"
      @save="saveCancellation"
    >
      <div class="space-y-3">
        <div class="overflow-x-auto border-y border-secondary">
          <table class="min-w-full text-sm text-text">
            <thead class="text-left text-xs uppercase opacity-60">
              <tr><th class="py-2 pr-3">Producto</th><th class="px-2 py-2">Comprado</th><th class="px-2 py-2">Pendiente</th><th class="py-2 pl-2">Devolver</th></tr>
            </thead>
            <tbody>
              <tr v-for="detail in cancellationSale.details || []" :key="detail.id" class="border-t border-secondary">
                <td class="py-3 pr-3 font-medium">{{ detail.product }}</td>
                <td class="px-2 py-3">{{ detail.quantity_display }}</td>
                <td class="px-2 py-3">{{ detail.remaining_quantity }} {{ detail.sale_unit === 'box' ? 'cajas' : detail.sale_unit === 'kg' ? 'kg' : 'pzas' }}</td>
                <td class="py-3 pl-2">
                  <input v-model.number="returnQuantities[detail.id]" type="number" min="0" :max="detail.remaining_quantity" :step="detail.sale_unit === 'kg' ? '0.001' : '1'" class="w-28 rounded-lg border border-secondary bg-background px-3 py-2 text-text" :disabled="Number(detail.remaining_quantity) <= 0" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <TextareaField
          v-model="cancellationForm.reason"
          label="Motivo de cancelacion"
          field="reason"
          :rows="2"
          placeholder="Motivo"
          :error="cancellationForm.errors.reason"
        />
      </div>
    </GlobalModal>

    <GlobalModal
      v-if="returnToVoid"
      title="Anular devolución"
      size="sm"
      height="auto"
      :columns="1"
      :subtitle="`${returnToVoid.folio} · ${money(returnToVoid.amount)}`"
      mode="delete"
      :processing="voidReturnForm.processing"
      :total-errors="Object.keys(voidReturnForm.errors).length"
      save-button-text="Confirmar anulación"
      close-button-text="Conservar devolución"
      @close="closeVoidReturnModal"
      @save="saveVoidReturn"
    >
      <div class="space-y-4">
        <TextareaField
          v-model="voidReturnForm.reason"
          label="Motivo de la anulación"
          field="reason"
          :rows="4"
          placeholder="Ej. La devolución fue registrada en el ticket equivocado."
          :error="voidReturnForm.errors.reason || voidReturnForm.errors.return"
        />
      </div>
    </GlobalModal>
  </PageLayout>
</template>
