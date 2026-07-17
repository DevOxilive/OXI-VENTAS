<script setup>
import { computed, onMounted, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import GlobalCard from "@/Components/Cards/GlobalCard.vue";
import { GlobalToolbar } from "@/Components/Toolbars";
import { GlobalModal, getModalRequestOptions } from "@/Components/Modales";
import MetricCard from "@/Components/Cards/MetricCard.vue";
import InputField from "@/Components/Forms/InputField.vue";
import {
  ErrorAlert,
  ToastAlert,
  WarningAlert,
} from "@/Components/Modales/UniversalActionModal";
import {
  connectQzTray,
  getDefaultQzPrinter,
  getQzPrinters,
  getStoredPrinterName,
  isQzTrayActive,
  printEscPosTicket,
  saveStoredPrinterName,
} from "@/Composables/useQzTray";
import {
  buildEscPosTicketData,
  createDefaultTicketTemplate,
  normalizeTicketTemplate,
} from "@/config/ticketTemplate";
import { usePermissions } from "@/Composables/usePermissions";

defineOptions({
  layout: AdminLayout,
});

const props = defineProps({
  selectorMode: { type: Boolean, default: false },
  branch: { type: Object, default: null },
  branchesDB: { type: Array, default: () => [] },
  current: { type: Object, default: null },
  ticketTemplate: { type: Object, default: null },
});

const { can } = usePermissions();

const showClosureModal = ref(false);
const availablePrinters = ref([]);
const selectedPrinterName = ref(getStoredPrinterName());
const printerBridgeReady = ref(false);
const printerBridgeMessage = ref("Conecta QZ Tray para imprimir tickets.");

const denominations = [
  { key: "1000", label: "$1000", value: 1000, group: "Billetes" },
  { key: "500", label: "$500", value: 500, group: "Billetes" },
  { key: "200", label: "$200", value: 200, group: "Billetes" },
  { key: "100", label: "$100", value: 100, group: "Billetes" },
  { key: "50", label: "$50", value: 50, group: "Billetes" },
  { key: "20b", label: "$20", value: 20, group: "Billetes" },
  { key: "20m", label: "$20", value: 20, group: "Monedas" },
  { key: "10", label: "$10", value: 10, group: "Monedas" },
  { key: "5", label: "$5", value: 5, group: "Monedas" },
  { key: "2", label: "$2", value: 2, group: "Monedas" },
  { key: "1", label: "$1", value: 1, group: "Monedas" },
  { key: "0.5", label: "$0.50", value: 0.5, group: "Monedas" },
];

const form = useForm({
  branch_id: props.branch?.id ?? "",
  cash_box_number: props.current?.cash_box_number ?? "1",
  counted_cash: "",
  cash_left: "",
  counted_card: "",
  denomination_breakdown: Object.fromEntries(denominations.map((item) => [item.key, ""])),
  notes: "",
});

const printerOptions = computed(() =>
  availablePrinters.value.map((printerName) => ({
    label: printerName,
    value: printerName,
  }))
);
const resolvedTicketTemplate = computed(() =>
  normalizeTicketTemplate(props.ticketTemplate?.settings || {
    ...createDefaultTicketTemplate(),
    subheader_text: "CORTE DE CAJA",
    footer_text: "Corte realizado correctamente",
  })
);

const billDenominations = computed(() => denominations.filter((item) => item.group === "Billetes"));
const coinDenominations = computed(() => denominations.filter((item) => item.group === "Monedas"));
const countedCashTotal = computed(() =>
  denominations.reduce((sum, item) => {
    return sum + Number(form.denomination_breakdown[item.key] || 0) * item.value;
  }, 0)
);
const cashDifferencePreview = computed(() => {
  return countedCashTotal.value - Number(props.current?.expected_cash || 0);
});
const cardDifferencePreview = computed(() => {
  return Number(form.counted_card || 0) - Number(props.current?.card_total || 0);
});
const cashToWithdraw = computed(() => Math.max(0, countedCashTotal.value - Number(form.cash_left || 0)));
const isBalanced = computed(() => {
  return Math.abs(cashDifferencePreview.value) < 0.01 && Math.abs(cardDifferencePreview.value) < 0.01;
});
const canCreateClosure = computed(() => can("sales.cash-closures.create"));
const summaryCards = computed(() => [
  { label: "Ventas", value: props.current?.sales_count ?? 0, tone: "neutral" },
  { label: "Venta total", value: money(props.current?.sales_total), tone: "dark" },
  { label: "Efectivo", value: money(props.current?.expected_cash), tone: "success" },
  { label: "Tarjeta", value: money(props.current?.card_total), tone: "neutral" },
]);

const toolbarConfig = computed(() => {
  if (props.selectorMode) {
    return {
      title: "Corte de caja",
      subtitle: "Selecciona la sucursal donde quieres revisar cortes activos.",
      showSearch: false,
      showRecordsPerPage: false,
      showCounter: false,
      filters: [],
      actions: [],
      tabs: [],
    };
  }

  return {
    title: "Corte de caja",
    subtitle: `Sucursal ${props.branch?.name || "Sin sucursal"}`,
    showSearch: false,
    showRecordsPerPage: false,
    showCounter: false,
    filters: [],
    actions: canCreateClosure.value
      ? [
          {
            id: "new-closure",
            label: "Registrar corte",
            icon: "payments",
            variant: "primary",
          },
        ]
      : [],
    tabs: [],
  };
});

function money(value) {
  return new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
  }).format(Number(value || 0));
}

function denominationTotal(item) {
  return Number(form.denomination_breakdown[item.key] || 0) * item.value;
}

function switchBranch(branchId) {
  if (!branchId) return;

  router.get(
    route("ventas.cash-closures.index"),
    { branch: branchId },
    {
      preserveScroll: true,
      replace: true,
    }
  );
}

function openClosureModal() {
  if (!canCreateClosure.value) return;

  form.branch_id = props.branch?.id ?? "";
  form.cash_box_number = props.current?.cash_box_number ?? "1";
  form.counted_cash = countedCashTotal.value;
  form.cash_left = "";
  form.counted_card = props.current?.card_total || "";
  form.denomination_breakdown = Object.fromEntries(denominations.map((item) => [item.key, ""]));
  form.notes = "";
  showClosureModal.value = true;
}

function closeClosureModal() {
  showClosureModal.value = false;
  form.clearErrors();
}

async function initializePrinterBridge({ silent = true } = {}) {
  try {
    await connectQzTray();
    const printers = await getQzPrinters();
    availablePrinters.value = printers;
    printerBridgeReady.value = true;

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
    printerBridgeMessage.value = printerName
      ? `Impresora lista: ${printerName}`
      : "QZ Tray conectado. Selecciona una impresora.";
  } catch (error) {
    printerBridgeReady.value = false;
    availablePrinters.value = [];
    printerBridgeMessage.value = error?.message || "QZ Tray no esta conectado.";

    if (!silent) {
      ErrorAlert({
        title: "No se pudo conectar la impresora",
        message: printerBridgeMessage.value,
      });
    }
  }
}

function handlePrinterChange(event) {
  selectedPrinterName.value = event.target.value || "";
  saveStoredPrinterName(selectedPrinterName.value);
  printerBridgeMessage.value = selectedPrinterName.value
    ? `Impresora lista: ${selectedPrinterName.value}`
    : "Selecciona una impresora para imprimir.";
}

function escLine(text = "") {
  return `${String(text || "")}\n`;
}

function escPair(label, value) {
  const left = String(label || "").slice(0, 18);
  const right = String(value || "");
  const spaces = Math.max(1, 32 - left.length - right.length);
  return escLine(`${left}${" ".repeat(spaces)}${right}`);
}

async function printClosureJobs(jobs = []) {
  if (!jobs.length) return;

  if (!selectedPrinterName.value) {
    throw new Error("No hay impresora seleccionada para imprimir el corte.");
  }

  if (!printerBridgeReady.value || !isQzTrayActive()) {
    await initializePrinterBridge({ silent: false });
  }

  if (!isQzTrayActive()) {
    throw new Error("QZ Tray no esta conectado.");
  }

  for (const job of jobs) {
    await printEscPosTicket(selectedPrinterName.value, buildEscPosTicketData(resolvedTicketTemplate.value, {
      ...job,
      type: "cash_closure",
    }), {
      connectIfNeeded: false,
    });
  }
}

function saveClosure() {
  if (!canCreateClosure.value) return;

  form.counted_cash = Number(countedCashTotal.value || 0);
  form.cash_left = Number(form.cash_left || 0);
  form.counted_card = Number(form.counted_card || 0);

  form.post(route("ventas.cash-closures.store"), getModalRequestOptions({
    entityName: "Corte de caja",
    successTitle: "Corte registrado correctamente",
    errorTitle: "No se pudo registrar el corte",
    errorMessage: "Revisa las denominaciones, tarjeta capturada y caja detectada.",
    preserveScroll: true,
    onSuccess: async (pageResponse) => {
      closeClosureModal();

      const jobs = pageResponse.props.flash?.cash_closure_print_jobs || [];

      try {
        await printClosureJobs(jobs);
        ToastAlert({ title: "Ticket de corte enviado a la impresora" });
      } catch (error) {
        WarningAlert({
          title: "Corte guardado sin imprimir",
          message: error?.message || "No se pudo enviar el ticket a la impresora.",
        });
      }
    },
  }));
}

onMounted(() => {
  if (!props.selectorMode) {
    initializePrinterBridge({ silent: true });
  }
});
</script>

<template>
  <Head title="Corte de caja" />

  <PageLayout>
    <template #toolbar>
      <GlobalToolbar
        v-bind="toolbarConfig"
        @action="openClosureModal"
      />
    </template>

    <template v-if="selectorMode">
      <div
        v-if="branchesDB.length"
        class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
      >
        <GlobalCard
          v-for="item in branchesDB"
          :key="item.id"
          :title="item.name"
          subtitle="Corte de caja"
          :description="item.has_activity ? 'Hay ventas generadas en el periodo actual.' : 'Sin ventas pendientes de corte.'"
          icon="payments"
          :badge="item.has_activity ? 'Abrir corte' : 'Sin ventas'"
          badge-variant="neutral"
          @click="switchBranch(item.id)"
        >
          <div class="mt-5 grid grid-cols-2 gap-2 text-xs font-semibold text-text">
            <div class="rounded-xl bg-secondary px-3 py-2">
              <p class="opacity-60">Ventas</p>
              <p class="mt-1 text-base font-black">{{ item.sales_count }}</p>
            </div>
            <div class="rounded-xl bg-secondary px-3 py-2">
              <p class="opacity-60">Total</p>
              <p class="mt-1 text-base font-black">{{ money(item.sales_total) }}</p>
            </div>
          </div>

          <div class="mt-4 border-t border-secondary pt-3">
            <p class="text-xs font-black uppercase tracking-[0.14em] text-text opacity-50">
              Usuarios con ventas
            </p>
            <div v-if="item.active_users.length" class="mt-2 space-y-2">
              <div
                v-for="activeUser in item.active_users"
                :key="`${item.id}-${activeUser.name}`"
                class="flex items-center justify-between gap-3 rounded-xl bg-secondary px-3 py-2 text-xs font-semibold text-text"
              >
                <span class="truncate">{{ activeUser.name }}</span>
                <span>{{ activeUser.sales_count }} / {{ money(activeUser.sales_total) }}</span>
              </div>
            </div>
            <p v-else class="mt-2 text-xs font-semibold text-text opacity-60">
              Nadie ha generado ventas en este periodo.
            </p>
          </div>
        </GlobalCard>
      </div>
    </template>

    <template v-else>
      <div class="space-y-5">
        <section class="overflow-hidden rounded-2xl border border-secondary bg-background shadow-sm">
          <div class="flex flex-col gap-4 border-b border-secondary px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
              <p class="text-[11px] font-black uppercase tracking-[0.18em] text-text opacity-50">
                Corte operativo
              </p>
              <h2 class="mt-1 text-2xl font-black text-text">
                Caja #{{ current.cash_box_number }} · {{ branch.name }}
              </h2>
              <p class="mt-1 text-sm font-semibold text-text opacity-65">
                Periodo {{ current.period_start }} a {{ current.period_end }}
              </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
              <div class="rounded-xl border border-secondary bg-secondary px-4 py-2">
                <p class="text-[10px] font-black uppercase tracking-[0.16em] text-text opacity-50">
                  Impresora
                </p>
                <p class="max-w-60 truncate text-sm font-black text-text">
                  {{ selectedPrinterName || 'Sin seleccionar' }}
                </p>
              </div>
              <select
                class="h-11 w-full rounded-xl border border-secondary bg-background px-3 text-sm font-semibold text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary sm:w-56"
                :value="selectedPrinterName"
                :disabled="!printerOptions.length"
                @change="handlePrinterChange"
              >
                <option value="">Selecciona impresora</option>
                <option
                  v-for="printer in printerOptions"
                  :key="printer.value"
                  :value="printer.value"
                >
                  {{ printer.label }}
                </option>
              </select>
              <button
                type="button"
                class="h-11 rounded-xl border border-secondary bg-background px-5 text-sm font-black text-text transition hover:bg-secondary"
                @click="initializePrinterBridge({ silent: false })"
              >
                Conectar
              </button>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-3 p-4 md:grid-cols-4">
            <MetricCard
              v-for="card in summaryCards"
              :key="card.label"
              :label="card.label"
              :value="card.value"
              :tone="card.tone"
            />
          </div>
        </section>
      </div>
    </template>

    <GlobalModal
      v-if="showClosureModal"
      title="Registrar corte de caja"
      :subtitle="`Caja #${form.cash_box_number} detectada desde las ventas registradas.`"
      :processing="form.processing"
      :total-errors="Object.keys(form.errors).length"
      save-button-text="Guardar e imprimir"
      size="2xl"
      :columns="1"
      @close="closeClosureModal"
      @save="saveClosure"
    >
      <div class="grid w-full gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
        <div class="min-w-0 space-y-4">
          <section class="overflow-hidden rounded-2xl border border-secondary bg-background">
            <div class="flex flex-col gap-1 border-b border-secondary px-5 py-4 sm:flex-row sm:items-end sm:justify-between">
              <div>
                <h3 class="text-lg font-black text-text">Conteo de efectivo</h3>
                <p class="text-sm text-text opacity-60">Captura las piezas por denominacion.</p>
              </div>
              <div class="rounded-xl bg-primary px-4 py-2 text-right text-white">
                <p class="text-[11px] font-black uppercase tracking-[0.14em] text-white opacity-80">Total contado</p>
                <p class="text-2xl font-black">{{ money(countedCashTotal) }}</p>
              </div>
            </div>

            <div class="grid gap-4 p-4 lg:grid-cols-2">
              <div class="rounded-2xl border border-secondary bg-secondary p-4">
                <p class="mb-3 text-sm font-black text-text">Billetes</p>
                <div class="space-y-2">
                  <label
                    v-for="item in billDenominations"
                    :key="item.key"
                    class="grid grid-cols-[76px_minmax(0,1fr)_92px] items-center gap-2 rounded-xl border border-secondary bg-background px-3 py-2"
                  >
                    <span class="text-sm font-black text-text">{{ item.label }}</span>
                    <input
                      v-model="form.denomination_breakdown[item.key]"
                      type="number"
                      min="0"
                      inputmode="numeric"
                      class="h-10 min-w-0 rounded-lg border border-secondary bg-background px-3 text-center text-base font-black text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary"
                      placeholder="0"
                    />
                    <span class="text-right text-sm font-black text-text opacity-75">
                      {{ money(denominationTotal(item)) }}
                    </span>
                  </label>
                </div>
              </div>

              <div class="rounded-2xl border border-secondary bg-secondary p-4">
                <p class="mb-3 text-sm font-black text-text">Monedas</p>
                <div class="space-y-2">
                  <label
                    v-for="item in coinDenominations"
                    :key="item.key"
                    class="grid grid-cols-[76px_minmax(0,1fr)_92px] items-center gap-2 rounded-xl border border-secondary bg-background px-3 py-2"
                  >
                    <span class="text-sm font-black text-text">{{ item.label }}</span>
                    <input
                      v-model="form.denomination_breakdown[item.key]"
                      type="number"
                      min="0"
                      inputmode="decimal"
                      class="h-10 min-w-0 rounded-lg border border-secondary bg-background px-3 text-center text-base font-black text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary"
                      placeholder="0"
                    />
                    <span class="text-right text-sm font-black text-text opacity-75">
                      {{ money(denominationTotal(item)) }}
                    </span>
                  </label>
                </div>
              </div>
            </div>
          </section>

          <section class="grid gap-4 md:grid-cols-2">
            <InputField
              v-model="form.counted_card"
              label="Total en tarjeta"
              field="counted_card"
              type="number"
              prefix="$"
              :error="form.errors.counted_card"
            />

            <InputField
              v-model="form.cash_left"
              label="Se deja en caja"
              field="cash_left"
              type="number"
              prefix="$"
              :error="form.errors.cash_left"
            />
          </section>

          <div>
            <label class="mb-1 block text-sm font-semibold text-text" for="cash_closure_notes">
              Observaciones
            </label>
            <textarea
              id="cash_closure_notes"
              v-model="form.notes"
              rows="4"
              class="w-full rounded-xl border border-secondary bg-background px-4 py-3 text-sm text-text outline-none transition focus:border-primary focus:ring-2 focus:ring-primary"
              placeholder="Notas del corte, faltantes, sobrantes o aclaraciones."
            />
            <p v-if="form.errors.notes" class="mt-1 text-xs text-primary">
              {{ form.errors.notes }}
            </p>
          </div>
        </div>

        <aside class="space-y-4">
          <div
            class="rounded-2xl border p-4"
            :class="isBalanced ? 'border-accent bg-secondary' : 'border-primary bg-secondary'"
          >
            <p
              class="text-sm font-black"
              :class="isBalanced ? 'text-accent' : 'text-primary'"
            >
              {{ isBalanced ? 'Corte cuadrado' : 'No esta cuadrando' }}
            </p>
            <p class="mt-1 text-xs font-semibold text-text opacity-70">
              {{ isBalanced ? 'Lo capturado coincide con el sistema.' : 'Revisa efectivo, tarjeta u observaciones.' }}
            </p>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <MetricCard label="Caja" :value="`#${form.cash_box_number}`" size="sm" />
            <MetricCard label="Ventas" :value="current.sales_count" size="sm" />
            <MetricCard label="Efectivo sistema" :value="money(current.expected_cash)" tone="success" size="sm" />
            <MetricCard label="Efectivo contado" :value="money(countedCashTotal)" tone="dark" size="sm" />
            <MetricCard
              label="Dif. efectivo"
              :value="money(cashDifferencePreview)"
              :tone="Math.abs(cashDifferencePreview) < 0.01 ? 'success' : 'danger'"
              size="sm"
            />
            <MetricCard
              label="Dif. tarjeta"
              :value="money(cardDifferencePreview)"
              :tone="Math.abs(cardDifferencePreview) < 0.01 ? 'success' : 'danger'"
              size="sm"
            />
          </div>

          <div class="rounded-2xl border border-secondary bg-background p-4">
            <p class="text-[11px] font-black uppercase tracking-[0.14em] text-text opacity-50">
              Movimiento de efectivo
            </p>
            <div class="mt-3 space-y-2 text-sm font-semibold text-text">
              <div class="flex justify-between gap-3">
                <span class="opacity-60">Se deja para cambio</span>
                <span>{{ money(form.cash_left) }}</span>
              </div>
              <div class="flex justify-between gap-3">
                <span class="opacity-60">Retiro estimado</span>
                <span>{{ money(cashToWithdraw) }}</span>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </GlobalModal>
  </PageLayout>
</template>
