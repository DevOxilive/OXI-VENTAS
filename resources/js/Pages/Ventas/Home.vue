<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import InputField from "@/Components/Forms/InputField.vue";
import SelectField from "@/Components/Forms/SelectField.vue";
import {
  printHtmlTicket,
  connectQzTray,
  getDefaultQzPrinter,
  getQzPrinters,
  getStoredPrinterName,
  isQzTrayActive,
  printEscPosTicket,
  saveStoredPrinterName,
} from "@/Composables/useQzTray";
import {
  ToastAlert,
  ErrorAlert,
  WarningAlert,
  BlockingWarningAlert,
} from "@/Components/Modales/UniversalActionModal";
import {
  buildEscPosTicketData,
  getStoredTicketTemplateSettings,
  normalizeTicketTemplate,
} from "@/config/ticketTemplate";

defineOptions({
  layout: AdminLayout,
});

const props = defineProps({
  selectorMode: {
    type: Boolean,
    default: false,
  },
  currentBranch: {
    type: Object,
    default: null,
  },
  branchesDB: {
    type: Array,
    default: () => [],
  },
  productsDB: {
    type: Array,
    default: () => [],
  },
  paymentMethodsDB: {
    type: Array,
    default: () => [],
  },
  defaultPaymentMethodId: {
    type: [Number, String],
    default: null,
  },
  nearExpirationAlerts: {
    type: Array,
    default: () => [],
  },
  ticketTemplate: {
    type: Object,
    default: null,
  },
});

const page = usePage();
const CASH_BOX_STORAGE_KEY = "ventas_cash_box_by_branch";

const search = ref("");
const searchInput = ref(null);
const selectedBranchId = ref(props.currentBranch?.id ?? "");
const selectedCashBoxNumber = ref("1");
const cart = ref([]);
const lastPrintJob = ref(null);
const initialAlertBranchId = ref(null);
const expirationAlerts = ref([]);
const expirationAlertPanelOpen = ref(false);
const expirationAlertPulse = ref(false);
const highlightedSuggestionIndex = ref(0);
const availablePrinters = ref([]);
const selectedPrinterName = ref(getStoredPrinterName());
const printerBridgeReady = ref(false);
const printerBridgeMessage = ref("Conecta QZ Tray para imprimir tickets.");
let printerRetryInterval = null;

const saleForm = useForm({
  branch_id: props.currentBranch?.id ?? "",
  payment_method_id: props.defaultPaymentMethodId ?? "",
  cash_received: "",
  items: [],
});

function formatMoney(value) {
  return new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
  }).format(Number(value || 0));
}

function focusSearch() {
  nextTick(() => {
    searchInput.value?.focus?.();
  });
}

watch(
  () => props.currentBranch?.id,
  (branchId) => {
    selectedBranchId.value = branchId ?? "";
    saleForm.branch_id = branchId ?? "";
    cart.value = [];
    search.value = "";
    saleForm.cash_received = "";
    lastPrintJob.value = null;
    expirationAlertPanelOpen.value = false;
    highlightedSuggestionIndex.value = 0;
    loadCashBoxForBranch(selectedBranchId.value);

    if (!props.selectorMode) {
      focusSearch();
    }
  },
  { immediate: true }
);

onMounted(() => {
  if (!props.selectorMode) {
    focusSearch();
    initializePrinterBridge({ silent: true });
    window.addEventListener("focus", handleWindowFocus);
  }
});

onBeforeUnmount(() => {
  if (printerRetryInterval) {
    window.clearInterval(printerRetryInterval);
    printerRetryInterval = null;
  }

  window.removeEventListener("focus", handleWindowFocus);
});

watch(
  () => [props.currentBranch?.id, props.nearExpirationAlerts],
  ([branchId, alerts]) => {
    replaceExpirationAlerts(alerts || []);

    if (!branchId || initialAlertBranchId.value === branchId) return;
    if (!alerts?.length) return;

    initialAlertBranchId.value = branchId;
    nextTick(() => {
      showExpirationAlert(alerts);
    });
  },
  { immediate: true, deep: true }
);

const filteredProducts = computed(() => {
  const query = search.value.trim().toLowerCase();

  if (!query) return props.productsDB;

  return props.productsDB.filter((product) =>
    (product.searchable || "").includes(query)
  );
});

const searchSuggestions = computed(() => {
  const query = search.value.trim().toLowerCase();

  if (!query) return [];

  return filteredProducts.value
    .slice()
    .sort((a, b) => {
      const aName = String(a.name || "").toLowerCase();
      const bName = String(b.name || "").toLowerCase();
      const aBarcode = String(a.barcode || "").toLowerCase();
      const bBarcode = String(b.barcode || "").toLowerCase();
      const aStarts = aName.startsWith(query) || aBarcode.startsWith(query);
      const bStarts = bName.startsWith(query) || bBarcode.startsWith(query);

      if (aStarts !== bStarts) {
        return aStarts ? -1 : 1;
      }

      return aName.localeCompare(bName, "es");
    })
    .slice(0, 6);
});

watch(searchSuggestions, (suggestions) => {
  if (!suggestions.length) {
    highlightedSuggestionIndex.value = 0;
    return;
  }

  if (highlightedSuggestionIndex.value >= suggestions.length) {
    highlightedSuggestionIndex.value = 0;
  }
});

const totalItems = computed(() =>
  cart.value.reduce((sum, item) => sum + Number(item.quantity || 0), 0)
);

const totalLines = computed(() => cart.value.length);

const cartTotal = computed(() =>
  cart.value.reduce(
    (sum, item) => sum + cartItemSubtotal(item),
    0
  )
);

const receivedAmount = computed(() => Number(saleForm.cash_received || 0));

const changeDue = computed(() => {
  const difference = receivedAmount.value - cartTotal.value;
  return difference > 0 ? difference : 0;
});

const missingAmount = computed(() => {
  const difference = cartTotal.value - receivedAmount.value;
  return difference > 0 ? difference : 0;
});

const expirationAlertCount = computed(() => expirationAlerts.value.length);

const urgentExpirationAlertCount = computed(() =>
  expirationAlerts.value.filter((alert) => Number(alert.days_to_expire ?? 999) <= 7).length
);

const topExpirationAlerts = computed(() =>
  expirationAlerts.value
    .slice()
    .sort((left, right) => {
      const leftDays = Number(left.days_to_expire ?? 999);
      const rightDays = Number(right.days_to_expire ?? 999);

      if (leftDays !== rightDays) {
        return leftDays - rightDays;
      }

      return String(left.product_name || "").localeCompare(String(right.product_name || ""), "es");
    })
);

const canCharge = computed(() => {
  return cart.value.length > 0 && receivedAmount.value >= cartTotal.value;
});

const currentBranchLabel = computed(
  () => props.currentBranch?.name || "Sin sucursal"
);

const selectorDescription = computed(() => {
  if (props.branchesDB.length === 0) {
    return "No hay sucursales activas disponibles para generar ventas.";
  }

  return "Selecciona la sucursal donde quieres abrir el punto de venta.";
});

const printerOptions = computed(() =>
  availablePrinters.value.map((printerName) => ({
    label: printerName,
    value: printerName,
  }))
);

const cashBoxOptions = [
  { label: "Caja #1", value: "1" },
  { label: "Caja #2", value: "2" },
];

function readStoredCashBoxes() {
  if (typeof window === "undefined") {
    return {};
  }

  try {
    return JSON.parse(window.localStorage.getItem(CASH_BOX_STORAGE_KEY) || "{}");
  } catch (error) {
    return {};
  }
}

function saveCashBoxForBranch(branchId, cashBoxNumber) {
  if (typeof window === "undefined" || !branchId) {
    return;
  }

  const stored = readStoredCashBoxes();
  stored[String(branchId)] = String(cashBoxNumber || "1");
  window.localStorage.setItem(CASH_BOX_STORAGE_KEY, JSON.stringify(stored));
}

function loadCashBoxForBranch(branchId) {
  const stored = readStoredCashBoxes();
  selectedCashBoxNumber.value = stored[String(branchId || "")] || "1";
}

function handleCashBoxChange(value) {
  selectedCashBoxNumber.value = value || "1";
  saveCashBoxForBranch(selectedBranchId.value || props.currentBranch?.id, selectedCashBoxNumber.value);
}

const resolvedTicketTemplate = computed(() =>
  normalizeTicketTemplate(
    props.ticketTemplate?.settings || getStoredTicketTemplateSettings() || {}
  )
);

function detectPreferredPrinter(printers = []) {
  const normalizedPreferred = printers.find((printerName) => {
    const text = String(printerName || "").toLowerCase();

    return (
      text.includes("3nstar") ||
      text.includes("rpt006") ||
      text.includes("pos-58") ||
      text.includes("pos58")
    );
  });

  return normalizedPreferred || printers[0] || "";
}

async function initializePrinterBridge({ silent = true } = {}) {
  try {
    await connectQzTray();

    const printers = await getQzPrinters();
    availablePrinters.value = printers;
    printerBridgeReady.value = true;

    let printerName = selectedPrinterName.value;

    if (!printerName || !printers.includes(printerName)) {
      printerName = getStoredPrinterName();
    }

    if (!printerName || !printers.includes(printerName)) {
      try {
        const defaultPrinter = await getDefaultQzPrinter();

        if (defaultPrinter && printers.includes(defaultPrinter)) {
          printerName = defaultPrinter;
        }
      } catch (error) {
        printerName = "";
      }
    }

    if (!printerName || !printers.includes(printerName)) {
      printerName = detectPreferredPrinter(printers);
    }

    selectedPrinterName.value = printerName || "";
    saveStoredPrinterName(selectedPrinterName.value);
    stopPrinterRetry();

    printerBridgeMessage.value = selectedPrinterName.value
      ? `Impresora lista: ${selectedPrinterName.value}`
      : "QZ Tray conectado. Selecciona la impresora del ticket.";
  } catch (error) {
    printerBridgeReady.value = false;
    availablePrinters.value = [];
    printerBridgeMessage.value =
      error?.message || "QZ Tray no esta conectado en esta computadora.";

    if (!silent) {
      ErrorAlert({
        title: "No se pudo conectar la impresora",
        message:
          error?.message ||
          "QZ Tray no responde. Abre QZ Tray y vuelve a intentar imprimir.",
      });
    }

    startPrinterRetry();
  }
}

async function ensurePrinterReadyForPrint({ silent = true } = {}) {
  if (!selectedPrinterName.value) {
    await initializePrinterBridge({ silent });
    return;
  }

  try {
    await connectQzTray();
    printerBridgeReady.value = true;
    printerBridgeMessage.value = `Impresora lista: ${selectedPrinterName.value}`;
    stopPrinterRetry();
  } catch (error) {
    printerBridgeReady.value = false;
    printerBridgeMessage.value =
      error?.message || "QZ Tray no esta conectado en esta computadora.";

    if (!silent) {
      ErrorAlert({
        title: "No se pudo conectar la impresora",
        message:
          error?.message ||
          "QZ Tray no responde. Abre QZ Tray y vuelve a intentar imprimir.",
      });
    }

    startPrinterRetry();
    throw error;
  }
}

function startPrinterRetry() {
  if (printerRetryInterval || props.selectorMode) {
    return;
  }

  printerRetryInterval = window.setInterval(() => {
    if (printerBridgeReady.value) {
      stopPrinterRetry();
      return;
    }

    initializePrinterBridge({ silent: true });
  }, 5000);
}

function stopPrinterRetry() {
  if (!printerRetryInterval) {
    return;
  }

  window.clearInterval(printerRetryInterval);
  printerRetryInterval = null;
}

function handleWindowFocus() {
  if (printerBridgeReady.value) {
    return;
  }

  initializePrinterBridge({ silent: true });
}

function handlePrinterChange(value) {
  selectedPrinterName.value = value || "";
  saveStoredPrinterName(selectedPrinterName.value);

  printerBridgeMessage.value = selectedPrinterName.value
    ? `Impresora lista: ${selectedPrinterName.value}`
    : "Selecciona una impresora para continuar.";
}

function resolvePrintJob(printJob) {
  const cashBoxNumber = String(selectedCashBoxNumber.value || "1");

  return {
    ...printJob,
    user_name: page.props.auth?.user?.name || printJob?.user_name || printJob?.employee_name || "",
    cash_box_number: cashBoxNumber,
    cash_box_text: `CAJA #${cashBoxNumber}`,
  };
}

async function printTicket(printJob) {
  if (!printJob) {
    return;
  }

  if (!selectedPrinterName.value) {
    throw new Error("No hay una impresora seleccionada para el ticket.");
  }

  if (!printerBridgeReady.value || !isQzTrayActive()) {
    throw new Error("QZ Tray no esta conectado. La venta se guardo sin imprimir ticket.");
  }

  const printData = buildEscPosTicketData(resolvedTicketTemplate.value, resolvePrintJob(printJob));
  await printEscPosTicket(selectedPrinterName.value, printData, { connectIfNeeded: false });
}

function queueTicketPrint(printJob) {
  window.setTimeout(async () => {
    try {
      await printTicket(printJob);
    } catch (error) {
      WarningAlert({
        title: "Venta guardada sin ticket",
        message: error?.message || "La venta se registro, pero no se pudo imprimir el ticket en la impresora.",
      });
    }
  }, 0);
}

async function reprintLastTicket() {
  if (!lastPrintJob.value) {
    return;
  }

  try {
    await ensurePrinterReadyForPrint({ silent: false });
    await printTicket(lastPrintJob.value);

    ToastAlert({
      title: "Ticket reenviado a la impresora",
    });
  } catch (error) {
    ErrorAlert({
      title: "No se pudo reimprimir",
      message: error?.message || "QZ Tray no pudo enviar el ticket a la impresora seleccionada.",
    });
  }
}

function cartItemUnitPrice(item) {
  const basePrice = Number(item.original_price || item.price || 0);
  const discountPercentage = item.discount_enabled
    ? Number(item.discount_percentage || 0)
    : 0;

  const discounted = basePrice - (basePrice * discountPercentage) / 100;
  return Math.max(Number(discounted.toFixed(2)), 0);
}

function cartItemDiscountAmount(item) {
  const basePrice = Number(item.original_price || item.price || 0);
  const finalPrice = cartItemUnitPrice(item);
  const quantity = Number(item.quantity || 0);

  return Number(((basePrice - finalPrice) * quantity).toFixed(2));
}

function cartItemSubtotal(item) {
  return Number((cartItemUnitPrice(item) * Number(item.quantity || 0)).toFixed(2));
}

function addProduct(product) {
  if (!product) return;

  const existing = cart.value.find(
    (item) => Number(item.branch_product_id) === Number(product.branch_product_id)
  );

  if (existing) {
    if (Number(existing.quantity) >= Number(product.stock || 0)) {
      ErrorAlert({
        title: "Stock agotado",
        message: `Solo hay ${product.stock} unidad(es) disponibles de ${product.name}.`,
      });
      return;
    }

    existing.quantity += 1;
    search.value = "";
    focusSearch();
    return;
  }

  if (Number(product.stock || 0) <= 0) {
    ErrorAlert({
      title: "Sin stock",
      message: `El producto ${product.name} no tiene existencias.`,
    });
    return;
  }

  cart.value.unshift({
    branch_product_id: product.branch_product_id,
    product_id: product.product_id,
    barcode_id: product.barcode_id ?? null,
    name: product.name,
    barcode: product.barcode,
    image: product.image,
    original_price: Number(product.price || 0),
    stock: Number(product.stock || 0),
    quantity: 1,
    discount_enabled: false,
    discount_percentage: 0,
  });

  search.value = "";
  highlightedSuggestionIndex.value = 0;
  focusSearch();
}

function findExactProduct(query) {
  const normalized = query.toLowerCase();

  return props.productsDB.find((product) => {
    if (String(product.barcode || "").trim() === query) return true;
    if (String(product.name || "").trim().toLowerCase() === normalized) return true;

    return (product.barcodes || []).some((code) => String(code).trim() === query);
  });
}

function selectSuggestion(product) {
  addProduct(product);
}

function moveSuggestion(delta) {
  if (!searchSuggestions.value.length) return;

  const maxIndex = searchSuggestions.value.length - 1;
  const nextIndex = highlightedSuggestionIndex.value + delta;

  if (nextIndex < 0) {
    highlightedSuggestionIndex.value = maxIndex;
    return;
  }

  if (nextIndex > maxIndex) {
    highlightedSuggestionIndex.value = 0;
    return;
  }

  highlightedSuggestionIndex.value = nextIndex;
}

function handleSearchKeydown(event) {
  if (event.key === "ArrowDown") {
    event.preventDefault();
    moveSuggestion(1);
    return;
  }

  if (event.key === "ArrowUp") {
    event.preventDefault();
    moveSuggestion(-1);
    return;
  }

  if (event.key !== "Enter") return;

  event.preventDefault();

  if (searchSuggestions.value.length) {
    selectSuggestion(
      searchSuggestions.value[
        Math.min(highlightedSuggestionIndex.value, searchSuggestions.value.length - 1)
      ]
    );
    return;
  }

  const query = search.value.trim();
  if (!query) return;

  const exact = findExactProduct(query);
  if (exact) {
    addProduct(exact);
    return;
  }

  if (filteredProducts.value.length === 1) {
    addProduct(filteredProducts.value[0]);
    return;
  }

  ErrorAlert({
    title: "Producto no encontrado",
    message: "Escanea el codigo o escribe el nombre del producto para buscarlo rapido.",
  });
}

function increaseQuantity(index) {
  const item = cart.value[index];
  if (!item) return;

  if (Number(item.quantity) >= Number(item.stock)) return;

  item.quantity += 1;
}

function decreaseQuantity(index) {
  const item = cart.value[index];
  if (!item) return;

  if (Number(item.quantity) <= 1) {
    cart.value.splice(index, 1);
    return;
  }

  item.quantity -= 1;
}

function removeItem(index) {
  cart.value.splice(index, 1);
}

function clearCart() {
  cart.value = [];
  search.value = "";
  saleForm.cash_received = "";
  highlightedSuggestionIndex.value = 0;
  focusSearch();
}

function switchBranch(branchId) {
  if (!branchId || String(branchId) === String(props.currentBranch?.id)) return;

  router.get(
    route("ventas.home"),
    { branch: branchId },
    {
      preserveScroll: true,
      replace: true,
    }
  );
}

function handleBranchChange(value) {
  selectedBranchId.value = value;
  loadCashBoxForBranch(value);
  switchBranch(value);
}

function handlePaymentMethodChange(value) {
  saleForm.payment_method_id = value ? Number(value) : "";
}

function toggleDiscount(item) {
  item.discount_enabled = !item.discount_enabled;

  if (!item.discount_enabled) {
    item.discount_percentage = 0;
  }
}

function normalizeDiscount(item) {
  const value = Number(item.discount_percentage || 0);

  if (Number.isNaN(value) || value < 0) {
    item.discount_percentage = 0;
    return;
  }

  if (value > 100) {
    item.discount_percentage = 100;
    return;
  }

  item.discount_percentage = Number(value.toFixed(2));
}

function expirationAlertKey(alert) {
  return [
    alert.branch_product_id || alert.product_name || "producto",
    alert.lot_number || "sin-lote",
    alert.expiration_date || alert.formatted_expiration_date || "sin-fecha",
  ].join("|");
}

function normalizeExpirationAlerts(alerts = []) {
  const byKey = new Map();

  alerts.forEach((alert) => {
    if (!alert) {
      return;
    }

    byKey.set(expirationAlertKey(alert), {
      ...alert,
      quantity: Number(alert.quantity || 0),
      days_to_expire: alert.days_to_expire === null || alert.days_to_expire === undefined
        ? null
        : Number(alert.days_to_expire),
    });
  });

  return Array.from(byKey.values())
    .sort((left, right) => {
      const leftDays = Number(left.days_to_expire ?? 999);
      const rightDays = Number(right.days_to_expire ?? 999);

      if (leftDays !== rightDays) {
        return leftDays - rightDays;
      }

      return String(left.product_name || "").localeCompare(String(right.product_name || ""), "es");
    });
}

function replaceExpirationAlerts(alerts = []) {
  expirationAlerts.value = normalizeExpirationAlerts(alerts);
}

function toggleExpirationAlerts() {
  expirationAlertPanelOpen.value = !expirationAlertPanelOpen.value;
}

function closeExpirationAlerts() {
  expirationAlertPanelOpen.value = false;
}

function expirationTone(alert) {
  const days = Number(alert.days_to_expire ?? 999);

  if (days <= 3) {
    return "border-rose-200 bg-rose-50 text-rose-700";
  }

  if (days <= 7) {
    return "border-amber-200 bg-amber-50 text-amber-700";
  }

  return "border-sky-200 bg-sky-50 text-sky-700";
}

function expirationBadgeLabel(alert) {
  const days = Number(alert.days_to_expire ?? 999);

  if (days <= 0) {
    return "Hoy";
  }

  if (days === 1) {
    return "1 dia";
  }

  if (Number.isFinite(days) && days < 999) {
    return `${days} dias`;
  }

  return "Por vencer";
}

function showExpirationAlert(alerts) {
  if (!alerts.length) return;

  const summary = alerts
    .map((alert) => {
      const quantityLabel = `${Number(alert.quantity || 0).toFixed(0)} pza(s)`;

      return `<p style="margin:0 0 8px 0;"><strong>${alert.product_name}</strong>: ${quantityLabel}, ${alert.message}.</p>`;
    })
    .join("");

  BlockingWarningAlert({
    title: "Lotes proximos a vencer",
    message: `${summary}<p style="margin:12px 0 0 0;">La campana de alertas queda arriba para revisarlos durante la venta.</p>`,
  });
}

function submitSale() {
  if (cart.value.length === 0) {
    ErrorAlert({
      title: "Venta vacia",
      message: "Agrega al menos un producto antes de cobrar.",
    });
    return;
  }

  if (receivedAmount.value < cartTotal.value) {
    ErrorAlert({
      title: "Efectivo insuficiente",
      message: "El monto recibido debe cubrir el total de la venta.",
    });
    return;
  }

  saleForm.branch_id = selectedBranchId.value || props.currentBranch?.id || "";
  saleForm.items = cart.value.map((item) => ({
    branch_product_id: item.branch_product_id,
    product_id: item.product_id,
    barcode_id: item.barcode_id ?? null,
    quantity: item.quantity,
    original_unit_price: Number(item.original_price || 0),
    discount_percentage: item.discount_enabled
      ? Number(item.discount_percentage || 0)
      : 0,
    discount_amount: cartItemDiscountAmount(item),
    unit_price: cartItemUnitPrice(item),
  }));

  saleForm.post(route("ventas.store"), {
    preserveScroll: true,
    onSuccess: async (pageResponse) => {
      const flash = pageResponse.props.flash || {};
      lastPrintJob.value = flash.print_job ? resolvePrintJob(flash.print_job) : null;

      ToastAlert({
        title: flash.sale_folio
          ? `Venta registrada: ${flash.sale_folio}`
          : "Venta registrada correctamente",
      });

      replaceExpirationAlerts(flash.expiration_alerts || []);

      if ((flash.expiration_alerts || []).length) {
        expirationAlertPulse.value = true;
        window.setTimeout(() => {
          expirationAlertPulse.value = false;
        }, 1600);
      }

      clearCart();
      saleForm.reset("items", "cash_received");
      saleForm.payment_method_id = props.defaultPaymentMethodId ?? "";

      if (lastPrintJob.value) {
        queueTicketPrint(lastPrintJob.value);
      }
    },
    onError: () => {
      ErrorAlert({
        title: "No se pudo guardar la venta",
        message: "Revisa el stock, el efectivo recibido o la sucursal seleccionada.",
      });
    },
  });
}
</script>

<template>
  <div class="h-full bg-slate-50">
    <template v-if="selectorMode">
      <div class="border-b border-slate-200 bg-white px-5 py-5 md:px-8">
        <div class="min-w-0">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
            Punto de venta
          </p>
          <h1 class="mt-1 text-2xl font-black text-slate-900">
            Ventas
          </h1>
          <p class="mt-1 text-sm text-slate-500">
            {{ selectorDescription }}
          </p>
        </div>
      </div>

      <div class="px-4 py-5 md:px-6 lg:px-8">
        <div
          v-if="branchesDB.length"
          class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
          <button
            v-for="branch in branchesDB"
            :key="branch.id"
            type="button"
            class="flex min-h-[220px] flex-col justify-between rounded-2xl border border-slate-200 bg-white p-6 text-left shadow-sm transition hover:-translate-y-[1px] hover:border-slate-300 hover:shadow-md"
            @click="switchBranch(branch.id)"
          >
            <div class="flex items-start justify-between gap-4">
              <div
                class="flex h-14 w-14 items-center justify-center rounded-2xl"
                :style="{ backgroundColor: `${branch.color || '#2563eb'}14`, color: branch.color || '#2563eb' }"
              >
                <span class="material-symbols-outlined text-3xl">
                  storefront
                </span>
              </div>

              <span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold text-slate-500">
                Abrir venta
              </span>
            </div>

            <div class="mt-8">
              <h2 class="text-xl font-bold text-slate-900">
                {{ branch.name }}
              </h2>
              <p class="mt-2 text-sm text-slate-500">
                Entra al punto de venta de esta sucursal y registra la operacion con su stock real.
              </p>
            </div>
          </button>
        </div>

        <div
          v-else
          class="flex min-h-[260px] items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white px-6 text-center text-sm text-slate-500"
        >
          No hay sucursales activas configuradas para abrir ventas.
        </div>
      </div>
    </template>

    <template v-else>
      <div class="border-b border-slate-200 bg-white px-5 py-4 md:px-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
              Punto de venta
            </p>
            <h1 class="mt-1 text-2xl font-black text-slate-900">
              Ventas
            </h1>
            <p class="mt-1 text-sm text-slate-500">
              {{ currentBranchLabel }} · Escanea, agrega productos y cobra una venta real.
            </p>
          </div>

          <div class="flex flex-col gap-3 xl:flex-row xl:items-start">
            <div class="relative flex justify-end xl:order-2">
              <button
                type="button"
                class="relative flex h-12 w-12 items-center justify-center rounded-2xl border transition"
                :class="[
                  expirationAlertCount
                    ? 'border-amber-200 bg-amber-50 text-amber-700 shadow-sm hover:bg-amber-100'
                    : 'border-slate-200 bg-slate-50 text-slate-400 hover:bg-slate-100',
                  expirationAlertPulse ? 'scale-105 ring-4 ring-amber-100' : '',
                ]"
                @click="toggleExpirationAlerts"
              >
                <span class="material-symbols-outlined text-[24px]">
                  notifications
                </span>
                <span
                  v-if="expirationAlertCount"
                  class="absolute -right-1.5 -top-1.5 flex h-6 min-w-6 items-center justify-center rounded-full bg-rose-600 px-1.5 text-[11px] font-black text-white shadow"
                >
                  {{ expirationAlertCount }}
                </span>
              </button>

              <div
                v-if="expirationAlertPanelOpen"
                class="absolute right-0 top-[calc(100%+0.75rem)] z-30 w-[min(92vw,420px)] overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl"
              >
                <div class="border-b border-slate-200 bg-slate-950 px-4 py-4 text-white">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-amber-200">
                        Alertas de caducidad
                      </p>
                      <h3 class="mt-1 text-lg font-black">
                        {{ expirationAlertCount ? `${expirationAlertCount} lote(s) por atender` : "Sin alertas" }}
                      </h3>
                      <p v-if="urgentExpirationAlertCount" class="mt-1 text-xs text-rose-100">
                        {{ urgentExpirationAlertCount }} urgente(s) en 7 dias o menos.
                      </p>
                    </div>

                    <button
                      type="button"
                      class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/10 text-white transition hover:bg-white/20"
                      @click="closeExpirationAlerts"
                    >
                      <span class="material-symbols-outlined text-[18px]">close</span>
                    </button>
                  </div>
                </div>

                <div v-if="expirationAlertCount" class="max-h-[360px] space-y-2 overflow-y-auto bg-slate-50 p-3">
                  <article
                    v-for="alert in topExpirationAlerts"
                    :key="expirationAlertKey(alert)"
                    class="rounded-2xl border bg-white p-3 shadow-sm"
                    :class="expirationTone(alert)"
                  >
                    <div class="flex items-start justify-between gap-3">
                      <div class="min-w-0">
                        <p class="truncate text-sm font-black text-slate-950">
                          {{ alert.product_name }}
                        </p>
                        <p class="mt-1 text-xs font-semibold text-slate-600">
                          Lote {{ alert.lot_number || "sin lote" }} · {{ Number(alert.quantity || 0).toFixed(0) }} pza(s)
                        </p>
                      </div>

                      <span class="shrink-0 rounded-full bg-white px-2.5 py-1 text-[11px] font-black shadow-sm">
                        {{ expirationBadgeLabel(alert) }}
                      </span>
                    </div>

                    <div class="mt-3 rounded-xl bg-white/70 px-3 py-2 text-xs font-semibold text-slate-700">
                      <p>{{ alert.message || "Producto proximo a vencer" }}</p>
                      <p v-if="alert.formatted_expiration_date || alert.expiration_date" class="mt-1 text-slate-500">
                        Caduca: {{ alert.formatted_expiration_date || alert.expiration_date }}
                      </p>
                    </div>
                  </article>
                </div>

                <div v-else class="bg-slate-50 px-5 py-8 text-center">
                  <span class="material-symbols-outlined text-4xl text-emerald-500">
                    task_alt
                  </span>
                  <p class="mt-2 text-sm font-bold text-slate-900">
                    Todo tranquilo
                  </p>
                  <p class="mt-1 text-xs text-slate-500">
                    No hay lotes por caducar en esta sucursal.
                  </p>
                </div>
              </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4 lg:min-w-[760px]">
            <SelectField
              v-if="branchesDB.length > 1"
              label="Sucursal"
              field="branch_id"
              :model-value="selectedBranchId"
              :options="branchesDB"
              placeholder="Selecciona una sucursal"
              @update:modelValue="handleBranchChange"
            />

            <SelectField
              label="Forma de pago"
              field="payment_method_id"
              :model-value="saleForm.payment_method_id"
              :options="paymentMethodsDB"
              placeholder="Selecciona pago"
              @update:modelValue="handlePaymentMethodChange"
            />

            <SelectField
              label="Caja"
              field="cash_box"
              :model-value="selectedCashBoxNumber"
              :options="cashBoxOptions"
              placeholder="Selecciona caja"
              @update:modelValue="handleCashBoxChange"
            />

            <SelectField
              label="Impresora"
              field="ticket_printer"
              :model-value="selectedPrinterName"
              :options="printerOptions"
              :disabled="!printerOptions.length"
              :placeholder="printerBridgeReady ? 'Selecciona impresora' : 'QZ Tray no conectado'"
              @update:modelValue="handlePrinterChange"
            />
            </div>
          </div>
        </div>
      </div>

      <div class="grid h-[calc(100vh-10.5rem)] gap-4 px-4 py-4 lg:grid-cols-[minmax(0,1.2fr)_480px] lg:px-6">
        <section class="min-h-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0 flex-1">
              <div class="relative">
                <InputField
                  ref="searchInput"
                  label="Buscar o escanear"
                  field="sales_search"
                  v-model="search"
                  icon="barcode_scanner"
                  placeholder="Codigo de barras o nombre del producto"
                  @keydown="handleSearchKeydown"
                />

                <div
                  v-if="searchSuggestions.length"
                  class="absolute left-0 right-0 top-[calc(100%+0.25rem)] z-20 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
                >
                  <button
                    v-for="(product, index) in searchSuggestions"
                    :key="`${product.branch_product_id}-${index}`"
                    type="button"
                    class="flex w-full items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 text-left last:border-b-0"
                    :class="index === highlightedSuggestionIndex ? 'bg-slate-900 text-white' : 'bg-white text-slate-900 hover:bg-slate-50'"
                    @click="selectSuggestion(product)"
                  >
                    <div class="min-w-0">
                      <p class="truncate text-sm font-semibold">
                        {{ product.name }}
                      </p>
                      <p
                        class="truncate text-xs"
                        :class="index === highlightedSuggestionIndex ? 'text-slate-200' : 'text-slate-500'"
                      >
                        {{ product.barcode || "Sin codigo" }}
                      </p>
                    </div>

                    <span
                      class="shrink-0 text-sm font-bold"
                      :class="index === highlightedSuggestionIndex ? 'text-white' : 'text-slate-700'"
                    >
                      {{ formatMoney(product.price) }}
                    </span>
                  </button>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:min-w-[280px]">
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                <span class="block text-[11px] uppercase tracking-[0.16em] text-slate-400">Capturados</span>
                <span class="font-semibold text-slate-900">{{ totalLines }}</span>
                articulos
              </div>

              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                <span class="block text-[11px] uppercase tracking-[0.16em] text-slate-400">Coincidencias</span>
                <span class="font-semibold text-slate-900">{{ filteredProducts.length }}</span>
                disponibles
              </div>
            </div>
          </div>

          <div class="mt-4 flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
            <div>
              <p class="font-semibold text-slate-900">
                Productos en la venta actual
              </p>
              <p class="text-xs text-slate-500">
                Escanea un codigo o escribe el nombre. Al agregarlo, se captura aqui mismo.
              </p>
            </div>

            <button
              type="button"
              class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-100"
              @click="clearCart"
            >
              Limpiar
            </button>
          </div>

          <div class="mt-4 min-h-0 overflow-hidden rounded-2xl border border-slate-200">
            <div class="hidden grid-cols-[minmax(0,1.4fr)_120px_120px_140px_56px] gap-3 border-b border-slate-200 bg-slate-50 px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500 md:grid">
              <span>Producto</span>
              <span>Precio</span>
              <span>Cantidad</span>
              <span>Importe</span>
              <span></span>
            </div>

            <div class="max-h-[calc(100vh-20.5rem)] space-y-3 overflow-y-auto bg-white p-3">
              <article
                v-for="(item, index) in cart"
                :key="`${item.branch_product_id}-${index}`"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
              >
                <div class="grid gap-4 md:grid-cols-[minmax(0,1.4fr)_120px_120px_140px_56px] md:items-start">
                  <div class="min-w-0">
                    <div class="flex gap-3">
                      <div class="h-16 w-16 shrink-0 overflow-hidden rounded-xl border border-slate-200 bg-white">
                        <img
                          v-if="item.image"
                          :src="item.image"
                          :alt="item.name"
                          class="h-full w-full object-contain"
                        />
                        <div v-else class="flex h-full items-center justify-center">
                          <span class="material-symbols-outlined text-2xl text-slate-300">
                            inventory_2
                          </span>
                        </div>
                      </div>

                      <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900">
                          {{ item.name }}
                        </p>
                        <p class="mt-1 truncate text-xs text-slate-500">
                          {{ item.barcode || "Sin codigo" }}
                        </p>
                        <p class="mt-2 text-xs text-slate-500">
                          Stock disponible: {{ Number(item.stock).toFixed(0) }}
                        </p>
                      </div>
                    </div>
                  </div>

                  <div class="rounded-xl bg-white px-3 py-3 md:bg-transparent md:px-0 md:py-0">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400 md:hidden">
                      Precio
                    </p>
                    <p class="mt-1 text-sm font-semibold text-slate-900 md:mt-0">
                      {{ formatMoney(cartItemUnitPrice(item)) }}
                    </p>
                    <p
                      v-if="item.discount_enabled && Number(item.discount_percentage || 0) > 0"
                      class="mt-1 text-xs text-rose-700"
                    >
                      Rebaja {{ Number(item.discount_percentage || 0).toFixed(0) }}%
                    </p>
                  </div>

                  <div class="rounded-xl bg-white px-3 py-3 md:bg-transparent md:px-0 md:py-0">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400 md:hidden">
                      Cantidad
                    </p>
                    <div class="mt-1 flex items-center rounded-xl border border-slate-200 bg-white md:mt-0">
                      <button
                        type="button"
                        class="h-9 w-9 text-lg font-bold text-slate-600"
                        @click="decreaseQuantity(index)"
                      >
                        -
                      </button>
                      <span class="min-w-10 px-2 text-center text-sm font-semibold text-slate-900">
                        {{ item.quantity }}
                      </span>
                      <button
                        type="button"
                        class="h-9 w-9 text-lg font-bold text-slate-600"
                        @click="increaseQuantity(index)"
                      >
                        +
                      </button>
                    </div>
                  </div>

                  <div class="rounded-xl bg-white px-3 py-3 md:bg-transparent md:px-0 md:py-0">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400 md:hidden">
                      Importe
                    </p>
                    <p class="mt-1 text-base font-bold text-slate-900 md:mt-0">
                      {{ formatMoney(cartItemSubtotal(item)) }}
                    </p>
                    <p
                      v-if="item.discount_enabled && cartItemDiscountAmount(item) > 0"
                      class="mt-1 text-xs text-rose-700"
                    >
                      -{{ formatMoney(cartItemDiscountAmount(item)) }}
                    </p>
                  </div>

                  <div class="flex items-start justify-end">
                    <button
                      type="button"
                      class="h-10 w-10 rounded-xl bg-white text-slate-500 transition hover:bg-slate-200"
                      @click="removeItem(index)"
                    >
                      x
                    </button>
                  </div>
                </div>

                <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-3">
                  <div class="flex items-center justify-between gap-3">
                    <div>
                      <p class="text-sm font-semibold text-slate-900">
                        Descuento
                      </p>
                      <p class="text-xs text-slate-500">
                        Activalo solo si este producto lleva rebaja.
                      </p>
                    </div>

                    <button
                      type="button"
                      class="flex h-6 w-11 items-center rounded-full p-1 transition"
                      :class="item.discount_enabled ? 'bg-emerald-500' : 'bg-slate-300'"
                      @click="toggleDiscount(item)"
                    >
                      <span
                        class="h-4 w-4 rounded-full bg-white shadow transition"
                        :class="item.discount_enabled ? 'translate-x-5' : 'translate-x-0'"
                      />
                    </button>
                  </div>

                  <div v-if="item.discount_enabled" class="mt-3 grid gap-3 lg:grid-cols-[180px_minmax(0,1fr)]">
                    <InputField
                      v-model="item.discount_percentage"
                      label="Porcentaje"
                      field="item_discount_percentage"
                      type="number"
                      placeholder="0"
                      suffix="%"
                      @validate="normalizeDiscount(item)"
                    />

                    <div class="grid grid-cols-2 gap-3 text-sm">
                      <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">
                          Precio original
                        </p>
                        <p class="mt-1 font-semibold text-slate-900">
                          {{ formatMoney(item.original_price) }}
                        </p>
                      </div>

                      <div class="rounded-xl bg-slate-50 px-3 py-2">
                        <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">
                          Descuento
                        </p>
                        <p class="mt-1 font-semibold text-rose-700">
                          -{{ formatMoney(cartItemDiscountAmount(item)) }}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </article>

              <div
                v-if="cart.length === 0"
                class="flex min-h-[340px] items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 text-center text-sm text-slate-500"
              >
                Todavia no hay productos capturados en la venta. Escanea o busca uno para empezar.
              </div>
            </div>
          </div>
        </section>

        <aside class="flex h-full min-h-0 flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">
                Resumen
              </p>
              <h2 class="text-xl font-bold text-slate-900">
                Venta actual
              </h2>
            </div>
          </div>

          <div class="mt-5 flex min-h-0 flex-1 flex-col">
            <div class="grid grid-cols-2 gap-3">
              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">
                  Articulos
                </p>
                <p class="mt-2 text-3xl font-black text-slate-900">
                  {{ totalLines }}
                </p>
              </div>

              <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">
                  Piezas
                </p>
                <p class="mt-2 text-3xl font-black text-slate-900">
                  {{ totalItems }}
                </p>
              </div>
            </div>

            <div class="mt-4 flex flex-1 flex-col">
              <div class="space-y-4">
                <InputField
                  v-model="saleForm.cash_received"
                  label="Efectivo recibido"
                  field="cash_received"
                  type="number"
                  placeholder="0.00"
                  :error="saleForm.errors.cash_received"
                  prefix="$"
                />

                <div class="grid grid-cols-2 gap-3">
                  <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">
                      Cambio:
                    </p>
                    <p class="mt-2 text-xl font-bold text-emerald-700">
                      {{ formatMoney(changeDue) }}
                    </p>
                  </div>

                  <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">
                      Falta
                    </p>
                    <p class="mt-2 text-xl font-bold text-rose-700">
                      {{ formatMoney(missingAmount) }}
                    </p>
                  </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                  <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">
                    Forma de pago
                  </p>
                  <p class="mt-2 text-base font-semibold text-slate-900">
                    {{
                      paymentMethodsDB.find(
                        (paymentMethod) =>
                          String(paymentMethod.id) === String(saleForm.payment_method_id)
                      )?.name || "Sin seleccionar"
                    }}
                  </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                  <div class="flex items-center justify-between gap-3">
                    <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">
                      Impresora
                    </p>

                    <button
                      v-if="!printerBridgeReady"
                      type="button"
                      class="text-[11px] font-semibold text-slate-600 underline underline-offset-2"
                      @click="initializePrinterBridge({ silent: false })"
                    >
                      Reconectar
                    </button>
                  </div>
                  <p class="mt-2 text-base font-semibold text-slate-900">
                    {{ selectedPrinterName || "Sin seleccionar" }}
                  </p>
                  <p class="mt-1 text-xs text-slate-500">
                    {{ printerBridgeMessage }}
                  </p>
                </div>

                <div class="rounded-3xl bg-[#1f1d2b] px-5 py-6 text-white">
                  <p class="text-[11px] uppercase tracking-[0.168em] text-slate-300">
                    Total venta
                  </p>
                  <p class="mt-3 text-5xl font-black leading-none">
                    {{ formatMoney(cartTotal) }}
                  </p>
                </div>
              </div>

              <div class="mt-auto space-y-3 pt-6">
                <button
                  v-if="lastPrintJob"
                  type="button"
                  class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-base font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                  @click="reprintLastTicket"
                >
                  Reimprimir ticket
                </button>

                <button
                  type="button"
                  class="inline-flex w-full items-center justify-center rounded-2xl bg-[#1f1d2b] px-4 py-4 text-base font-bold text-white transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50"
                  :disabled="saleForm.processing || !canCharge"
                  @click="submitSale"
                >
                  {{ saleForm.processing ? "Guardando..." : "Cobrar venta" }}
                </button>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </template>
  </div>
</template>
