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
import TextareaField from "@/Components/Forms/TextareaField.vue";
import {
  ErrorAlert,
  ToastAlert,
  WarningAlert,
} from "@/Components/Modales/UniversalActionModal";
import {
  connectQzTray,
  findTicketPrinter,
  getQzPrinters,
  isQzTrayActive,
  printEscPosTicket,
} from "@/Composables/useQzTray";
import {
  buildEscPosTicketData,
  createDefaultTicketTemplate,
  normalizeTicketTemplate,
} from "@/config/ticketTemplate";
import { usePermissions } from "@/Composables/usePermissions";
import { cashDenominations, totalCashDenominations } from "@/config/cashDenominations";

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
const selectedPrinterName = ref("");
const printerBridgeReady = ref(false);
const printerBridgeMessage = ref("Conecta QZ Tray para imprimir tickets.");
const TICKET_LOGO_URL = "/icons/super-kay-ticket-bw.png";
let ticketLogoDataUrlPromise = null;
const ticketHeaderDataUrlPromises = new Map();

const denominations = cashDenominations;

const form = useForm({
  branch_id: props.branch?.id ?? "",
  cash_box_number: props.current?.cash_box_number ?? "1",
  counted_cash: "",
  cash_left: "",
  counted_card: "",
  denomination_breakdown: Object.fromEntries(denominations.map((item) => [item.key, ""])),
  notes: "",
});

const resolvedTicketTemplate = computed(() =>
  normalizeTicketTemplate(props.ticketTemplate?.settings || {
    ...createDefaultTicketTemplate(),
    subheader_text: "CORTE DE CAJA",
    footer_text: "Corte realizado correctamente",
  })
);

const billDenominations = computed(() => denominations.filter((item) => item.group === "Billetes"));
const coinDenominations = computed(() => denominations.filter((item) => item.group === "Monedas"));
const denominationGroups = computed(() => [
  { label: "Billetes", items: billDenominations.value },
  { label: "Monedas", items: coinDenominations.value },
]);
const countedCashTotal = computed(() => totalCashDenominations(form.denomination_breakdown));
const cashToWithdraw = computed(() => Math.max(0, countedCashTotal.value - Number(form.cash_left || 0)));
const cashDifference = computed(() => countedCashTotal.value - Number(props.current?.expected_cash || 0));
const cardDifference = computed(() => Number(form.counted_card || 0) - Number(props.current?.card_total || 0));
const cashDifferenceLabel = computed(() => cashDifference.value > 0.009 ? "Sobrante" : cashDifference.value < -0.009 ? "Faltante" : "Cuadrado");
const denominationError = computed(() => Object.entries(form.errors)
  .find(([field]) => field === "denomination_breakdown" || field.startsWith("denomination_breakdown."))?.[1] || "");
const canCreateClosure = computed(() => can("sales.cash-closures.create"));
const canViewClosureOutcome = computed(() => can("reports.cash-closures.view"));
const summaryCards = computed(() => [
  { label: "Caja", value: `#${props.current?.cash_box_number || "1"}`, tone: "neutral" },
  { label: "Ventas pendientes", value: props.current?.sales_count ?? 0, tone: "neutral" },
  { label: "Devoluciones", value: props.current?.refunds_count ?? 0, tone: "danger" },
  { label: "Corte", value: "Caja", tone: "dark" },
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

function blobToDataUrl(blob) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result || ""));
    reader.onerror = () => reject(reader.error || new Error("No se pudo leer el logo del ticket."));
    reader.readAsDataURL(blob);
  });
}

async function getTicketLogoDataUrl() {
  if (typeof window === "undefined") {
    return "";
  }

  if (!ticketLogoDataUrlPromise) {
    ticketLogoDataUrlPromise = window.fetch(TICKET_LOGO_URL, {
      cache: "force-cache",
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error("No se encontro el logo del ticket.");
        }

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
      if (!logoDataUrl || typeof document === "undefined") {
        return logoDataUrl;
      }

      const logoImage = await imageFromDataUrl(logoDataUrl);
      const canvas = document.createElement("canvas");
      canvas.width = 576;
      canvas.height = 92;
      const context = canvas.getContext("2d");

      if (!context) {
        return logoDataUrl;
      }

      context.fillStyle = "#ffffff";
      context.fillRect(0, 0, canvas.width, canvas.height);
      context.drawImage(logoImage, 12, 5, 82, 82);

      context.fillStyle = "#000000";
      context.font = "700 27px Arial, sans-serif";
      context.textAlign = "center";
      context.textBaseline = "middle";
      context.fillText("SUPER KAY", Math.round(canvas.width / 2), Math.round(canvas.height / 2));

      if (normalizedCashBoxText) {
        context.fillStyle = "#000000";
        context.font = "700 21px Arial, sans-serif";
        context.textAlign = "right";
        context.textBaseline = "middle";
        context.fillText(normalizedCashBoxText.toUpperCase(), canvas.width - 12, Math.round(canvas.height / 2));
      }

      return canvas.toDataURL("image/png");
    })
    .catch(() => "");

  ticketHeaderDataUrlPromises.set(cacheKey, promise);

  return promise;
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
  form.counted_card = "";
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
    printerBridgeReady.value = true;
    selectedPrinterName.value = findTicketPrinter(printers);
    printerBridgeMessage.value = selectedPrinterName.value
      ? `Impresora lista: ${selectedPrinterName.value}`
      : "QZ Tray conectado, pero no encontro una impresora de tickets identificada.";
  } catch (error) {
    printerBridgeReady.value = false;
    printerBridgeMessage.value = error?.message || "QZ Tray no esta conectado.";

    if (!silent) {
      ErrorAlert({
        title: "No se pudo conectar la impresora",
        message: printerBridgeMessage.value,
      });
    }
  }
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
    const cashBoxNumber = String(job.cash_box_number || form.cash_box_number || "1");
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
    errorMessage: "Revisa los datos capturados.",
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
      :subtitle="`Caja #${form.cash_box_number} · ${branch?.name || ''}`"
      :processing="form.processing"
      :total-errors="Object.keys(form.errors).length"
      save-button-text="Guardar e imprimir"
      size="2xl"
      :columns="1"
      height="auto"
      @close="closeClosureModal"
      @save="saveClosure"
    >
      <div class="grid w-full gap-5 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
        <section class="min-w-0 rounded-xl border border-secondary bg-background p-4">
          <div class="flex items-baseline justify-between gap-3">
            <h3 class="text-base font-black text-text">Denominaciones</h3>
            <p class="whitespace-nowrap text-lg font-black text-primary">{{ money(countedCashTotal) }}</p>
          </div>

          <div class="mt-4 grid gap-5 sm:grid-cols-2">
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
                  placeholder="0"
                />
                <span class="text-right text-xs font-bold text-text">{{ money(denominationTotal(item)) }}</span>
              </label>
            </div>
          </div>
          <p v-if="denominationError" class="mt-3 text-xs font-bold text-primary">{{ denominationError }}</p>
        </section>

        <section class="min-w-0 rounded-xl border border-secondary bg-background p-4">
          <h3 class="text-base font-black text-text">{{ canViewClosureOutcome ? 'Resumen del corte' : 'Captura del corte' }}</h3>
          <dl v-if="canViewClosureOutcome" class="mt-3 divide-y divide-secondary text-sm text-text">
            <div class="flex justify-between gap-3 py-2">
              <dt class="opacity-70">Efectivo esperado</dt>
              <dd class="font-black">{{ money(current?.expected_cash) }}</dd>
            </div>
            <div class="flex justify-between gap-3 py-2">
              <dt class="opacity-70">Tarjeta esperada</dt>
              <dd class="font-black">{{ money(current?.card_total) }}</dd>
            </div>
          </dl>

          <div class="mt-4 grid gap-3 sm:grid-cols-2">
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

          <div class="mt-4 flex justify-between gap-3 border-t border-secondary pt-3 text-sm text-text">
            <span class="opacity-70">Retiro de efectivo</span>
            <strong>{{ money(cashToWithdraw) }}</strong>
          </div>

          <div v-if="canViewClosureOutcome" class="mt-4 border-t border-secondary pt-3">
            <h4 class="text-sm font-black text-text">Resultado</h4>
            <div class="mt-2 flex justify-between gap-3 text-sm font-bold" :class="Math.abs(cashDifference) < 0.01 ? 'text-accent' : 'text-primary'">
              <span>Efectivo · {{ cashDifferenceLabel }}</span>
              <span>{{ money(Math.abs(cashDifference)) }}</span>
            </div>
            <div class="mt-2 flex justify-between gap-3 text-sm font-bold" :class="Math.abs(cardDifference) < 0.01 ? 'text-accent' : 'text-primary'">
              <span>Diferencia en tarjeta</span>
              <span>{{ money(cardDifference) }}</span>
            </div>
          </div>

          <div class="mt-4 border-t border-secondary pt-3">
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
        </section>
      </div>
    </GlobalModal>
  </PageLayout>
</template>
