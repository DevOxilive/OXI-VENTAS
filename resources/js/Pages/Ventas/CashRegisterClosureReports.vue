<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import { GlobalToolbar } from "@/Components/Toolbars";
import { GlobalTable } from "@/Components/Tables";
import { GlobalModal, confirmModalAction } from "@/Components/Modales";
import { ErrorAlert, ToastAlert } from "@/Components/Modales/UniversalActionModal";
import GlobalCard from "@/Components/Cards/GlobalCard.vue";
import InputField from "@/Components/Forms/InputField.vue";
import TextareaField from "@/Components/Forms/TextareaField.vue";
import { usePermissions } from "@/Composables/usePermissions";
import {
  connectQzTray,
  findTicketPrinter,
  getQzPrinters,
  isQzTrayActive,
  printEscPosTicket,
} from "@/Composables/useQzTray";
import { getCashRegisterClosureReportsToolbarConfig } from "@/config/ToolbarConfigs/cashRegisterClosureReportsToolbarConfig";
import { cashDenominations, totalCashDenominations } from "@/config/cashDenominations";
import {
  buildEscPosTicketData,
  createDefaultTicketTemplate,
  normalizeTicketTemplate,
} from "@/config/ticketTemplate";

defineOptions({
  layout: AdminLayout,
});

const { can } = usePermissions();
const props = defineProps({
  selectorMode: { type: Boolean, default: false },
  currentBranch: { type: Object, default: null },
  branchesDB: { type: Array, default: () => [] },
  closures: { type: Object, required: true },
  summary: { type: Object, required: true },
  filters: { type: Object, default: () => ({}) },
  users: { type: Array, default: () => [] },
  ticketTemplate: { type: Object, default: null },
});

const rows = computed(() => props.closures?.data || []);
let filterReloadTimeout = null;
let syncingFilters = false;

const reportFilters = reactive({
  folio: props.filters.folio || "",
  user_id: props.filters.user_id || "",
  status: props.filters.status || "",
  date_from: props.filters.date_from || "",
  date_to: props.filters.date_to || "",
  per_page: Number(props.filters.per_page || 25),
});

const toolbarActions = computed(() => can("reports.cash-closures.create")
  ? [
      {
        id: "new-cut",
        label: "Nuevo corte",
        icon: "payments",
        variant: "primary",
        permission: "reports.cash-closures.create",
      },
    ]
  : []);

const toolbarConfig = computed(() => getCashRegisterClosureReportsToolbarConfig({
  form: reportFilters,
  users: props.users,
  selectorMode: props.selectorMode,
  actions: toolbarActions.value,
}));

const selectedClosure = ref(null);
const modalMode = ref("view");
const showClosureModal = ref(false);
const selectedPrinterName = ref("");
const printerBridgeReady = ref(false);
const TICKET_LOGO_URL = "/icons/super-kay-ticket-bw.png";
let ticketLogoDataUrlPromise = null;
const ticketHeaderDataUrlPromises = new Map();

const form = useForm({
  denomination_breakdown: {},
  cash_left: 0,
  counted_card: 0,
  notes: "",
  record_version: "",
});

const billDenominations = cashDenominations.filter((item) => item.group === "Billetes");
const coinDenominations = cashDenominations.filter((item) => item.group === "Monedas");
const denominationGroups = [
  { label: "Billetes", items: billDenominations },
  { label: "Monedas", items: coinDenominations },
];
const resolvedTicketTemplate = computed(() =>
  normalizeTicketTemplate(props.ticketTemplate?.settings || {
    ...createDefaultTicketTemplate(),
    subheader_text: "CORTE DE CAJA",
    footer_text: "Corte realizado correctamente",
  }),
);
const countedCashTotal = computed(() => totalCashDenominations(form.denomination_breakdown));
const savedDenominations = computed(() => cashDenominations.filter(
  (item) => Number(selectedClosure.value?.denomination_breakdown?.[item.key] || 0) > 0,
));
const savedDenominationTotal = computed(() => totalCashDenominations(selectedClosure.value?.denomination_breakdown));
const savedCountMismatch = computed(() => Math.abs(
  savedDenominationTotal.value - Number(selectedClosure.value?.counted_cash || 0),
) >= 0.01);
const denominationError = computed(() => Object.entries(form.errors)
  .find(([field]) => field === "denomination_breakdown" || field.startsWith("denomination_breakdown."))?.[1] || "");

const isSelectedBalanced = computed(() => {
  if (!selectedClosure.value) return false;

  return (
    Math.abs(Number(editableCashDifference.value || 0)) < 0.01
    && Math.abs(Number(editableCardDifference.value || 0)) < 0.01
  );
});

const editableCountedCash = computed(() => (
  modalMode.value === "edit" ? countedCashTotal.value : Number(selectedClosure.value?.counted_cash || 0)
));

const editableCountedCard = computed(() => (
  modalMode.value === "edit" ? Number(form.counted_card || 0) : Number(selectedClosure.value?.counted_card || 0)
));

const editableCashLeft = computed(() => (
  modalMode.value === "edit" ? Number(form.cash_left || 0) : Number(selectedClosure.value?.cash_left || 0)
));

const editableCashDifference = computed(() => (
  editableCountedCash.value - Number(selectedClosure.value?.expected_drawer_cash || 0)
));

const editableCardDifference = computed(() => (
  editableCountedCard.value - Number(selectedClosure.value?.card_total || 0)
));

const editableWithdrawal = computed(() => (
  Math.max(0, editableCountedCash.value - editableCashLeft.value)
));

const cashDifferenceLabel = computed(() => {
  if (editableCashDifference.value > 0.009) return "Sobrante";
  if (editableCashDifference.value < -0.009) return "Faltante";
  return "Cuadrado";
});

const columns = [
  { key: "folio", label: "Folio" },
  { key: "user", label: "Usuario" },
  {
    key: "status",
    label: "Estado",
    format: "badge",
    formatOptions: {
      statusMap: {
        Cuadrado: "green",
        Diferencia: "amber",
      },
    },
  },
];

const closureActions = computed(() => [
  {
    id: "view",
    label: "Ver",
    icon: "visibility",
    variant: "blue",
    permission: "reports.cash-closures.view",
  },
  {
    id: "reprint",
    label: "Reimprimir",
    icon: "print",
    variant: "green",
    permission: "reports.cash-closures.view",
  },
  {
    id: "edit",
    label: "Editar",
    icon: "edit",
    variant: "amber",
    permission: "reports.cash-closures.update",
  },
  {
    id: "delete",
    label: "Eliminar",
    icon: "delete",
    variant: "red",
    permission: "reports.cash-closures.delete",
  },
].map((action) => ({
  ...action,
  hidden: () => action.permission && !can(action.permission),
})));

function money(value) {
  return new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
  }).format(Number(value || 0));
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
      .then((response) => {
        if (!response.ok) throw new Error("No se encontro el logo del ticket.");
        return response.blob();
      })
      .then(blobToDataUrl)
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
  await connectQzTray();
  selectedPrinterName.value = findTicketPrinter(await getQzPrinters());
  printerBridgeReady.value = true;

  if (!selectedPrinterName.value) {
    throw new Error("No hay una impresora seleccionada para el ticket.");
  }
}

async function reprintClosureTicket(closure) {
  if (!closure?.id) return;

  try {
    const { data } = await window.axios.get(route("ventas.cash-closures.ticket", { closure: closure.id }));
    const jobs = data?.print_jobs || [];

    if (!jobs.length) {
      throw new Error("El servidor no devolvio la informacion del ticket.");
    }

    if (!printerBridgeReady.value || !isQzTrayActive()) {
      await initializePrinterBridge();
    }

    for (const job of jobs) {
      const cashBoxNumber = String(job.cash_box_number || "1");
      const cashBoxText = job.cash_box_text || `CAJA #${cashBoxNumber}`;

      await printEscPosTicket(selectedPrinterName.value, buildEscPosTicketData(resolvedTicketTemplate.value, {
        ...job,
        type: "cash_closure",
        cash_box_number: cashBoxNumber,
        cash_box_text: cashBoxText,
        ticket_logo_data_url: await getTicketLogoDataUrl(),
        ticket_header_data_url: await getTicketHeaderDataUrl(cashBoxText),
      }), {
        connectIfNeeded: false,
      });
    }

    ToastAlert({ title: `Ticket ${closure.folio} reenviado a la impresora` });
  } catch (error) {
    ErrorAlert({
      title: "No se pudo reimprimir",
      message: error?.response?.data?.message || error?.message || "QZ Tray no pudo enviar el ticket a la impresora seleccionada.",
    });
  }
}

function handlePageChange(url) {
  router.visit(url, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ["closures", "summary", "filters", "users"],
  });
}

function reportRoute() {
  if (props.currentBranch?.id) {
    return route("inventory.branches.reports.cash-closures", {
      branch: props.currentBranch.id,
    });
  }

  return route("ventas.cash-closures.reports");
}

function reportQuery(overrides = {}) {
  return {
    folio: reportFilters.folio || "",
    user_id: reportFilters.user_id || "",
    status: reportFilters.status || "",
    date_from: reportFilters.date_from || "",
    date_to: reportFilters.date_to || "",
    per_page: reportFilters.per_page || 25,
    ...overrides,
  };
}

function applyReportFilters() {
  if (props.selectorMode) return;

  router.get(reportRoute(), reportQuery({ page: 1 }), {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ["closures", "summary", "filters", "users"],
  });
}

function updateReportFilter({ key, value }) {
  reportFilters[key] = value;
}

function goToCut() {
  router.visit(route("ventas.cash-closures.index"));
}

function openBranchReport(branch) {
  router.visit(route("inventory.branches.reports.cash-closures", { branch: branch.id }));
}

function openClosure(row, mode = "view") {
  selectedClosure.value = row;
  modalMode.value = mode;
  form.denomination_breakdown = Object.fromEntries(
    cashDenominations.map((item) => [item.key, Number(row.denomination_breakdown?.[item.key] || 0)]),
  );
  form.cash_left = Number(row.cash_left || 0);
  form.counted_card = Number(row.counted_card || 0);
  form.notes = row.notes || "";
  form.record_version = row.record_version || "";
  form.clearErrors();
  showClosureModal.value = true;
}

function closeClosureModal() {
  showClosureModal.value = false;
  selectedClosure.value = null;
  form.clearErrors();
}

function handleTableAction({ action, row }) {
  if (action === "view" && can("reports.cash-closures.view")) {
    openClosure(row, "view");
  }

  if (action === "reprint" && can("reports.cash-closures.view")) {
    void reprintClosureTicket(row);
  }

  if (action === "edit" && can("reports.cash-closures.update")) {
    openClosure(row, "edit");
  }

  if (action === "delete" && can("reports.cash-closures.delete")) {
    deleteClosure(row);
  }
}

function showActionSuccess(title) {
  setTimeout(() => {
    ToastAlert({ title });
  }, 120);
}

function showActionError(title, message) {
  ErrorAlert({ title, message });
}

async function submitClosure() {
  if (!selectedClosure.value?.id || modalMode.value !== "edit") {
    closeClosureModal();
    return;
  }

  const result = await confirmModalAction({
    mode: "edit",
    entityName: "corte",
    title: "Guardar cambios",
    message: `El nuevo efectivo contado es ${money(countedCashTotal.value)}. ¿Deseas guardar los cambios del corte ${selectedClosure.value.folio}?`,
    confirmText: "Si, guardar",
    confirmButtonColor: "#e60012",
  });

  if (!result.isConfirmed) return;

  form.put(route("ventas.cash-closures.update", selectedClosure.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      closeClosureModal();
      showActionSuccess("Corte actualizado correctamente");
    },
    onError: () => {
      showActionError("Error al actualizar corte", "No fue posible actualizar el corte. Revisa los campos e intenta nuevamente.");
    },
  });
}

async function deleteClosure(row) {
  const result = await confirmModalAction({
    mode: "delete",
    entityName: "corte",
    title: "Eliminar corte",
    message: `Deseas eliminar el corte ${row.folio}`,
    confirmText: "Si, eliminar",
  });

  if (!result.isConfirmed) return;

  form.record_version = row.record_version || "";
  form.delete(route("ventas.cash-closures.destroy", row.id), {
    preserveScroll: true,
    onSuccess: () => {
      if (selectedClosure.value?.id === row.id) {
        closeClosureModal();
      }

      showActionSuccess("Corte eliminado correctamente");
    },
    onError: () => {
      showActionError("Error al eliminar corte", "No fue posible eliminar el corte.");
    },
  });
}

function backToReportsCenter() {
  router.visit(route("inventory.reports.select", { report: "cash-closures" }));
}

watch(
  () => props.filters,
  (filters) => {
    syncingFilters = true;
    reportFilters.folio = filters.folio || "";
    reportFilters.user_id = filters.user_id || "";
    reportFilters.status = filters.status || "";
    reportFilters.date_from = filters.date_from || "";
    reportFilters.date_to = filters.date_to || "";
    reportFilters.per_page = Number(filters.per_page || 25);

    setTimeout(() => {
      syncingFilters = false;
    }, 0);
  },
  { deep: true },
);

watch(
  () => ({ ...reportFilters }),
  () => {
    if (syncingFilters || props.selectorMode) return;

    clearTimeout(filterReloadTimeout);
    filterReloadTimeout = setTimeout(applyReportFilters, 350);
  },
  { deep: true },
);

onBeforeUnmount(() => {
  clearTimeout(filterReloadTimeout);
});
</script>

<template>
  <Head title="Reportes de cortes" />

  <PageLayout>
    <div class="space-y-6">
      <GlobalToolbar
        v-bind="toolbarConfig"
        :subtitle="selectorMode ? 'Selecciona una sucursal para consultar su historial de cortes.' : currentBranch ? `Historial de cortes de ${currentBranch.name}` : 'Historial de cortes registrados por sucursal y usuario'"
        back-label="Sucursales"
        @back="backToReportsCenter"
        @update:search="reportFilters.folio = $event"
        @update:filter="updateReportFilter"
        @update:records-per-page="reportFilters.per_page = $event"
        @action="goToCut"
      />

      <section
        v-if="selectorMode"
        class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
      >
        <GlobalCard
          v-for="branch in branchesDB"
          :key="branch.id"
          :title="branch.name"
          subtitle="Sucursal"
          description="Consulta el historial de cortes de caja de esta sucursal."
          icon="history"
          class="min-h-32"
          @click="openBranchReport(branch)"
        />
      </section>

      <template v-else>
      <div class="rounded-2xl border border-secondary bg-background p-4">
        <GlobalTable
          :items="rows"
          :columns="columns"
          :actions="closureActions"
          row-key="id"
          mobile-card-header-field="folio"
          :pagination="closures"
          no-data-message="Todavia no hay cortes registrados."
          @page-change="handlePageChange"
          @action="handleTableAction"
        />
      </div>
      </template>

      <GlobalModal
        v-if="showClosureModal"
        :title="modalMode === 'edit' ? 'Editar corte de caja' : 'Detalle del corte de caja'"
        :subtitle="selectedClosure?.folio || ''"
        :mode="modalMode"
        :columns="1"
        size="2xl"
        height="auto"
        :processing="form.processing"
        :show-save="modalMode === 'edit'"
        save-button-text="Guardar cambios"
        close-button-text="Cerrar"
        @save="submitClosure"
        @close="closeClosureModal"
      >
        <div v-if="selectedClosure" class="space-y-4">
          <div class="flex flex-wrap items-start justify-between gap-3 border-b border-secondary pb-3">
            <div>
              <p class="text-sm font-semibold text-text opacity-70">
                {{ selectedClosure.branch }} · Caja #{{ selectedClosure.cash_box_number || '1' }} · {{ selectedClosure.period }}
              </p>
            </div>
            <span
              class="rounded-full px-3 py-1 text-xs font-black"
              :class="isSelectedBalanced ? 'bg-accent/10 text-accent' : 'bg-primary/10 text-primary'"
            >
              {{ isSelectedBalanced ? 'Cuadrado' : 'Con diferencia' }}
            </span>
          </div>

          <div class="grid gap-5 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
            <section class="rounded-xl border border-secondary bg-background p-4">
              <div class="flex items-baseline justify-between gap-3">
                <h4 class="text-base font-black text-text">Denominaciones</h4>
                <p class="whitespace-nowrap text-lg font-black text-primary">{{ money(editableCountedCash) }}</p>
              </div>

              <div v-if="modalMode === 'edit'" class="mt-4 grid gap-5 sm:grid-cols-2">
                <div v-for="group in denominationGroups" :key="group.label">
                  <p class="mb-2 text-xs font-black uppercase tracking-wide text-text opacity-60">{{ group.label }}</p>
                  <label
                    v-for="item in group.items"
                    :key="item.key"
                    class="grid grid-cols-[48px_minmax(0,1fr)_72px] items-center gap-2 border-b border-secondary py-1.5 last:border-0"
                  >
                    <span class="text-xs font-bold text-text">{{ item.label }}</span>
                    <input
                      v-model="form.denomination_breakdown[item.key]"
                      type="number"
                      min="0"
                      step="1"
                      inputmode="numeric"
                      class="h-9 min-w-0 rounded-lg border border-secondary bg-background px-2 text-center text-sm font-bold text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary"
                    />
                    <span class="text-right text-xs font-bold text-text">{{ money(Number(form.denomination_breakdown[item.key] || 0) * item.value) }}</span>
                  </label>
                </div>
              </div>
              <div v-else-if="savedDenominations.length" class="mt-4 grid gap-x-5 sm:grid-cols-2">
                <div
                  v-for="item in savedDenominations"
                  :key="item.key"
                  class="flex justify-between gap-2 border-b border-secondary py-2 text-sm text-text"
                >
                  <span>{{ item.label }} × {{ selectedClosure.denomination_breakdown[item.key] }}</span>
                  <strong>{{ money(Number(selectedClosure.denomination_breakdown[item.key]) * item.value) }}</strong>
                </div>
              </div>
              <p v-else class="mt-4 text-sm text-text opacity-65">Sin denominaciones registradas.</p>
              <p v-if="denominationError" class="mt-3 text-xs font-bold text-primary">{{ denominationError }}</p>
              <p v-if="savedCountMismatch" class="mt-3 text-xs font-bold text-primary">
                El desglose anterior no coincide con el efectivo guardado. Revisa las piezas antes de guardar.
              </p>
            </section>

            <section class="rounded-xl border border-secondary bg-background p-4">
              <h4 class="text-base font-black text-text">Resumen del corte</h4>
              <dl class="mt-3 divide-y divide-secondary text-sm text-text">
                <div class="flex justify-between gap-3 py-2">
                  <dt class="opacity-70">Efectivo esperado</dt>
                  <dd class="font-black">{{ money(selectedClosure.expected_drawer_cash) }}</dd>
                </div>
                <div class="flex justify-between gap-3 py-2">
                  <dt class="opacity-70">Tarjeta esperada</dt>
                  <dd class="font-black">{{ money(selectedClosure.card_total) }}</dd>
                </div>
              </dl>

              <div v-if="modalMode === 'edit'" class="mt-4 grid gap-3 sm:grid-cols-2">
                <InputField
                  v-model="form.counted_card"
                  label="Total en tarjeta"
                  field="counted_card"
                  type="number"
                  min="0"
                  step="0.01"
                  prefix="$"
                  :error="form.errors.counted_card"
                />
                <InputField
                  v-model="form.cash_left"
                  label="Se deja en caja"
                  field="cash_left"
                  type="number"
                  min="0"
                  step="0.01"
                  prefix="$"
                  :error="form.errors.cash_left"
                />
              </div>
              <dl v-else class="mt-3 divide-y divide-secondary text-sm text-text">
                <div class="flex justify-between gap-3 py-2">
                  <dt class="opacity-70">Tarjeta capturada</dt>
                  <dd class="font-black">{{ money(editableCountedCard) }}</dd>
                </div>
                <div class="flex justify-between gap-3 py-2">
                  <dt class="opacity-70">Se deja en caja</dt>
                  <dd class="font-black">{{ money(editableCashLeft) }}</dd>
                </div>
              </dl>

              <div class="mt-4 border-t border-secondary pt-3">
                <div class="flex justify-between gap-3 text-sm text-text">
                  <span class="opacity-70">Retiro de efectivo</span>
                  <strong>{{ money(editableWithdrawal) }}</strong>
                </div>
              </div>

              <div class="mt-4 border-t border-secondary pt-3">
                <h5 class="text-sm font-black text-text">Resultado</h5>
                <div class="mt-2 flex justify-between gap-3 text-sm font-bold" :class="Math.abs(editableCashDifference) < 0.01 ? 'text-accent' : 'text-primary'">
                  <span>Efectivo · {{ cashDifferenceLabel }}</span>
                  <span>{{ money(Math.abs(editableCashDifference)) }}</span>
                </div>
                <div class="mt-2 flex justify-between gap-3 text-sm font-bold" :class="Math.abs(editableCardDifference) < 0.01 ? 'text-accent' : 'text-primary'">
                  <span>Diferencia en tarjeta</span>
                  <span>{{ money(editableCardDifference) }}</span>
                </div>
              </div>

              <div v-if="modalMode === 'edit'" class="mt-4 border-t border-secondary pt-3">
                <TextareaField
                  v-model="form.notes"
                  label="Observaciones"
                  field="notes"
                  :rows="2"
                  :max-height="72"
                  placeholder="Opcional"
                  :error="form.errors.notes"
                />
              </div>
              <p v-else-if="selectedClosure.notes" class="mt-4 border-t border-secondary pt-3 text-sm text-text">
                <span class="font-bold">Observaciones:</span> {{ selectedClosure.notes }}
              </p>
            </section>
          </div>
        </div>
      </GlobalModal>
    </div>
  </PageLayout>
</template>
