<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import { GlobalToolbar } from "@/Components/Toolbars";
import InputField from "@/Components/Forms/InputField.vue";
import EmptyStateCard from "@/Components/Cards/EmptyStateCard.vue";
import InfoCard from "@/Components/Cards/InfoCard.vue";
import MetricCard from "@/Components/Cards/MetricCard.vue";
import SaleCartItemCard from "@/Components/Ventas/SaleCartItemCard.vue";
import SaleBranchSelectorCard from "@/Components/Ventas/SaleBranchSelectorCard.vue";
import { getSalesToolbarConfig } from "@/config/ToolbarConfigs/salesToolbarConfig";
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
import { usePermissions } from "@/Composables/usePermissions";

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
const { can } = usePermissions();
const CASH_BOX_STORAGE_KEY = "ventas_cash_box_by_branch";

const search = ref("");
const searchInput = ref(null);
const selectedBranchId = ref(props.currentBranch?.id ?? "");
const selectedCashBoxNumber = ref("1");
const cart = ref([]);
const lastPrintJob = ref(null);
const cardPaymentConfirmed = ref(false);
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
  cash_box_number: selectedCashBoxNumber.value,
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
    cardPaymentConfirmed.value = false;
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
  if (!canCreateSale.value) {
    return false;
  }

  if (cart.value.length === 0 || !saleForm.payment_method_id) {
    return false;
  }

  if (!isCashPayment.value) {
    return cardPaymentConfirmed.value;
  }

  return receivedAmount.value >= cartTotal.value;
});

const currentBranchLabel = computed(
  () => props.currentBranch?.name || "Sin sucursal"
);

const selectedPaymentMethod = computed(() =>
  props.paymentMethodsDB.find(
    (paymentMethod) =>
      String(paymentMethod.id) === String(saleForm.payment_method_id)
  ) || null
);

const selectedPaymentMethodLabel = computed(() =>
  selectedPaymentMethod.value?.name || "Sin seleccionar"
);

const selectedPaymentMethodType = computed(() => {
  const methodName = String(selectedPaymentMethod.value?.name || "")
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "");

  if (methodName.includes("tarjeta") || methodName.includes("card") || methodName.includes("credito") || methodName.includes("debito")) {
    return "card";
  }

  if (methodName.includes("efectivo") || methodName.includes("cash")) {
    return "cash";
  }

  return "cash";
});

const isCashPayment = computed(() => selectedPaymentMethodType.value === "cash");
const canAccessAllBranches = computed(() => can("branches.access-all"));
const canCreateSale = computed(() => can("sales.create"));
const canReturnToBranchSelector = computed(() =>
  canAccessAllBranches.value && !props.selectorMode && props.branchesDB.length > 1
);

watch(cartTotal, (total) => {
  if (!isCashPayment.value && saleForm.payment_method_id) {
    saleForm.cash_received = Number(total || 0).toFixed(2);
  }
});

const toolbarConfig = computed(() =>
  getSalesToolbarConfig({
    selectorMode: props.selectorMode,
    branchName: currentBranchLabel.value,
    selectorDescription: selectorDescription.value,
    branches: props.branchesDB,
    selectedBranchId: selectedBranchId.value,
    paymentMethods: props.paymentMethodsDB,
    selectedPaymentMethodId: saleForm.payment_method_id,
    cashBoxOptions,
    selectedCashBoxNumber: selectedCashBoxNumber.value,
    printerOptions: printerOptions.value,
    selectedPrinterName: selectedPrinterName.value,
    printerBridgeReady: printerBridgeReady.value,
    expirationAlertCount: expirationAlertCount.value,
    backButton: canReturnToBranchSelector.value,
  })
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
  if (!canCreateSale.value) {
    WarningAlert({
      title: "Sin permiso para vender",
      message: "Puedes consultar el punto de venta, pero no tienes permiso para generar ventas.",
    });
    return;
  }

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
  cardPaymentConfirmed.value = false;
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

function returnToBranchSelector() {
  if (!canReturnToBranchSelector.value) {
    return;
  }

  router.get(
    route("ventas.home"),
    {},
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
  cardPaymentConfirmed.value = false;

  if (!isCashPayment.value) {
    saleForm.cash_received = Number(cartTotal.value || 0).toFixed(2);
    return;
  }

  saleForm.cash_received = "";
}

function handleToolbarFilterUpdate({ key, value }) {
  if (key === "branch_id") {
    handleBranchChange(value);
    return;
  }

  if (key === "payment_method_id") {
    handlePaymentMethodChange(value);
    return;
  }

  if (key === "cash_box") {
    handleCashBoxChange(value);
    return;
  }

  if (key === "ticket_printer") {
    handlePrinterChange(value);
  }
}

function handleToolbarAction(actionId) {
  if (actionId === "toggle-expiration-alerts") {
    toggleExpirationAlerts();
  }
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
    return "border-primary bg-secondary text-primary";
  }

  if (days <= 7) {
    return "border-accent bg-secondary text-accent";
  }

  return "border-secondary bg-background text-text";
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

  const escapeHtml = (value) => String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");

  const summary = topExpirationAlerts.value
    .map((alert) => {
      const days = Number(alert.days_to_expire ?? 999);
      const urgencyColor = days <= 3 ? "#e60012" : days <= 7 ? "#c81e2b" : "#8f1d2c";
      const badgeLabel = escapeHtml(expirationBadgeLabel(alert));
      const productName = escapeHtml(alert.product_name || "Producto sin nombre");
      const lot = escapeHtml(alert.lot_number || "Sin lote");
      const quantity = Number(alert.quantity || 0).toFixed(0);
      const expirationDate = escapeHtml(alert.formatted_expiration_date || alert.expiration_date || "Fecha no disponible");

      return `<article style="margin:0 0 10px;padding:12px;border:1px solid #704047;border-radius:14px;background:#1d1315;text-align:left;">
        <div style="display:flex;gap:10px;align-items:flex-start;justify-content:space-between;">
          <strong style="color:#fff0f1;font-size:14px;line-height:1.35;">${productName}</strong>
          <span style="flex:0 0 auto;border-radius:999px;background:${urgencyColor};color:#fff;padding:4px 8px;font-size:11px;font-weight:700;white-space:nowrap;">${badgeLabel}</span>
        </div>
        <p style="margin:7px 0 0;color:#d6b8bd;font-size:12px;line-height:1.4;">Lote ${lot} · ${quantity} unidades</p>
        <p style="margin:3px 0 0;color:#b9969c;font-size:12px;line-height:1.4;">Caduca: ${expirationDate}</p>
      </article>`;
    })
    .join("");

  BlockingWarningAlert({
    title: "Lotes próximos a vencer",
    message: `<div style="margin:0 0 12px;color:#c9aaaf;font-size:13px;line-height:1.45;">Revisa estos lotes antes de continuar con la venta.</div><div style="max-height:300px;overflow-y:auto;padding-right:4px;">${summary}</div><p style="margin:12px 0 0;color:#b9969c;font-size:12px;line-height:1.4;">La campana permanecerá disponible arriba para consultarlos durante la venta.</p>`,
    confirmButtonColor: "#e60012",
  });
}

function submitSale() {
  if (!canCreateSale.value) {
    WarningAlert({
      title: "Sin permiso para cobrar",
      message: "No tienes permiso para registrar ventas.",
    });
    return;
  }

  if (cart.value.length === 0) {
    ErrorAlert({
      title: "Venta vacia",
      message: "Agrega al menos un producto antes de cobrar.",
    });
    return;
  }

  if (!saleForm.payment_method_id) {
    ErrorAlert({
      title: "Forma de pago requerida",
      message: "Selecciona efectivo o pago con tarjeta antes de cobrar.",
    });
    return;
  }

  if (isCashPayment.value && receivedAmount.value < cartTotal.value) {
    ErrorAlert({
      title: "Efectivo insuficiente",
      message: "El monto recibido debe cubrir el total de la venta.",
    });
    return;
  }

  if (!isCashPayment.value && !cardPaymentConfirmed.value) {
    ErrorAlert({
      title: "Confirma el pago con tarjeta",
      message: "Marca que la terminal aprobo el cobro antes de registrar la venta.",
    });
    return;
  }

  saleForm.branch_id = selectedBranchId.value || props.currentBranch?.id || "";
  saleForm.cash_box_number = String(selectedCashBoxNumber.value || "1");
  saleForm.cash_received = isCashPayment.value
    ? Number(saleForm.cash_received || 0)
    : Number(cartTotal.value || 0);
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
  <PageLayout>
    <template #toolbar>
      <GlobalToolbar
        v-bind="toolbarConfig"
        @back="returnToBranchSelector"
        @update:filter="handleToolbarFilterUpdate"
        @action="handleToolbarAction"
      />
    </template>
    <template v-if="selectorMode">
      <div
        v-if="branchesDB.length"
        class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
      >
          <SaleBranchSelectorCard
            v-for="branch in branchesDB"
            :key="branch.id"
            :branch="branch"
            @select="switchBranch"
          />
      </div>

      <EmptyStateCard
        v-else
        title="No hay sucursales activas"
        description="No hay sucursales activas configuradas para abrir ventas."
        icon="storefront"
        min-height-class="min-h-[260px]"
        tone="white"
      />
    </template>

    <template v-else>
      <div v-if="false" class="border-b border-secondary bg-background px-5 py-4 md:px-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-text opacity-50">
              Punto de venta
            </p>
            <h1 class="mt-1 text-2xl font-black text-text">
              Ventas
            </h1>
            <p class="mt-1 text-sm text-text opacity-70">
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
                    ? 'border-accent bg-secondary text-accent shadow-sm hover:brightness-95'
                    : 'border-secondary bg-secondary text-text opacity-70 hover:bg-background',
                  expirationAlertPulse ? 'scale-105 ring-4 ring-secondary' : '',
                ]"
                @click="toggleExpirationAlerts"
              > 
                <span class="material-symbols-outlined text-[24px]">
                  notifications
                </span>
                <span
                  v-if="expirationAlertCount"
                  class="absolute -right-1.5 -top-1.5 flex h-6 min-w-6 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] font-black text-white shadow"
                >
                  {{ expirationAlertCount }}
                </span>
              </button>

              <div
                v-if="expirationAlertPanelOpen"
                class="absolute right-0 top-[calc(100%+0.75rem)] z-30 w-[min(92vw,420px)] overflow-hidden rounded-3xl border border-secondary bg-background shadow-2xl"
              >
                <div class="border-b border-secondary bg-primary px-4 py-4 text-white">

                  
                  
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-white/80">
                        Alertas de caducidad
                      </p>
                      <h3 class="mt-1 text-lg font-black">
                        {{ expirationAlertCount ? `${expirationAlertCount} lote(s) por atender` : "Sin alertas" }}
                      </h3>
                      <p v-if="urgentExpirationAlertCount" class="mt-1 text-xs text-white/80">
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

                <div v-if="expirationAlertCount" class="max-h-[360px] space-y-2 overflow-y-auto bg-secondary p-3">
                  <article
                    v-for="alert in topExpirationAlerts"
                    :key="expirationAlertKey(alert)"
                    class="rounded-2xl border bg-background p-3 shadow-sm"
                    :class="expirationTone(alert)"
                  >
                    <div class="flex items-start justify-between gap-3">
                      <div class="min-w-0">
                        <p class="truncate text-sm font-black text-text">
                          {{ alert.product_name }}
                        </p>
                        <p class="mt-1 text-xs font-semibold text-text opacity-70">
                          Lote {{ alert.lot_number || "sin lote" }} · {{ Number(alert.quantity || 0).toFixed(0) }} pza(s)
                        </p>
                      </div>

                      <span class="shrink-0 rounded-full bg-background px-2.5 py-1 text-[11px] font-black shadow-sm">
                        {{ expirationBadgeLabel(alert) }}
                      </span>
                    </div>

                    <div class="mt-3 rounded-xl bg-secondary px-3 py-2 text-xs font-semibold text-text">
                      <p>{{ alert.message || "Producto proximo a vencer" }}</p>
                      <p v-if="alert.formatted_expiration_date || alert.expiration_date" class="mt-1 text-text opacity-70">
                        Caduca: {{ alert.formatted_expiration_date || alert.expiration_date }}
                      </p>
                    </div>
                  </article>
                </div>

                <div v-else class="bg-secondary px-5 py-8 text-center">
                  <span class="material-symbols-outlined text-4xl text-accent">
                    task_alt
                  </span>
                  <p class="mt-2 text-sm font-bold text-text">
                    Todo tranquilo
                  </p>
                  <p class="mt-1 text-xs text-text opacity-70">
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

      <div class="relative">
        <div
          v-if="expirationAlertPanelOpen"
          class="absolute right-0 top-0 z-30 w-[min(92vw,420px)] overflow-hidden rounded-3xl border border-secondary bg-background shadow-2xl"
        >
          <div class="border-b border-secondary bg-primary px-4 py-4 text-white">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-white/80">
                  Alertas de caducidad
                </p>
                <h3 class="mt-1 text-lg font-black">
                  {{ expirationAlertCount ? `${expirationAlertCount} lote(s) por atender` : "Sin alertas" }}
                </h3>
                <p v-if="urgentExpirationAlertCount" class="mt-1 text-xs text-white/80">
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

          <div v-if="expirationAlertCount" class="max-h-[360px] space-y-2 overflow-y-auto bg-secondary p-3">
            <article
              v-for="alert in topExpirationAlerts"
              :key="expirationAlertKey(alert)"
              class="rounded-2xl border bg-background p-3 shadow-sm"
              :class="expirationTone(alert)"
            >
              <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                  <p class="truncate text-sm font-black text-text">
                    {{ alert.product_name }}
                  </p>
                  <p class="mt-1 text-xs font-semibold text-text opacity-70">
                    Lote {{ alert.lot_number || "sin lote" }} · {{ Number(alert.quantity || 0).toFixed(0) }} pza(s)
                  </p>
                </div>

                <span class="shrink-0 rounded-full bg-background px-2.5 py-1 text-[11px] font-black shadow-sm">
                  {{ expirationBadgeLabel(alert) }}
                </span>
              </div>

              <div class="mt-3 rounded-xl bg-secondary px-3 py-2 text-xs font-semibold text-text">
                <p>{{ alert.message || "Producto proximo a vencer" }}</p>
                <p v-if="alert.formatted_expiration_date || alert.expiration_date" class="mt-1 text-text opacity-70">
                  Caduca: {{ alert.formatted_expiration_date || alert.expiration_date }}
                </p>
              </div>
            </article>
          </div>

          <div v-else class="bg-secondary px-5 py-8 text-center">
            <span class="material-symbols-outlined text-4xl text-accent">
              task_alt
            </span>
            <p class="mt-2 text-sm font-bold text-text">
              Todo tranquilo
            </p>
            <p class="mt-1 text-xs text-text opacity-70">
              No hay lotes por caducar en esta sucursal.
            </p>
          </div>
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_480px]">
        <section class="rounded-2xl border border-secondary bg-background p-4 shadow-sm">
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
                  class="absolute left-0 right-0 top-[calc(100%+0.25rem)] z-20 overflow-hidden rounded-2xl border border-secondary bg-background shadow-xl"
                >
                  <button
                    v-for="(product, index) in searchSuggestions"
                    :key="`${product.branch_product_id}-${index}`"
                    type="button"
                    class="flex w-full items-center justify-between gap-3 border-b border-secondary px-4 py-3 text-left last:border-b-0"
                    :class="index === highlightedSuggestionIndex ? 'bg-primary text-white' : 'bg-background text-text hover:bg-secondary'"
                    @click="selectSuggestion(product)"
                  >
                    <div class="min-w-0">
                      <p class="truncate text-sm font-semibold">
                        {{ product.name }}
                      </p>
                      <p
                        class="truncate text-xs"
                        :class="index === highlightedSuggestionIndex ? 'text-white opacity-80' : 'text-text opacity-70'"
                      >
                        {{ product.barcode || "Sin codigo" }}
                      </p>
                    </div>

                    <span
                      class="shrink-0 text-sm font-bold"
                      :class="index === highlightedSuggestionIndex ? 'text-white' : 'text-text'"
                    >
                      {{ formatMoney(product.price) }}
                    </span>
                  </button>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:min-w-[280px]">
              <MetricCard
                label="Capturados"
                :value="totalLines"
                suffix="articulos"
                size="sm"
              />

              <MetricCard
                label="Coincidencias"
                :value="filteredProducts.length"
                suffix="disponibles"
                size="sm"
              />
            </div>
          </div>

          <div class="mt-4 flex items-center justify-between rounded-2xl border border-secondary bg-secondary px-4 py-3 text-sm">
            <div>
              <p class="font-semibold text-text">
                Productos en la venta actual
              </p>
              <p class="text-xs text-text opacity-70">
                Escanea un codigo o escribe el nombre. Al agregarlo, se captura aqui mismo.
              </p>
            </div>

            <button
              type="button"
              class="rounded-xl border border-secondary bg-background px-3 py-2 text-sm font-semibold text-text transition hover:border-primary hover:bg-secondary"
              @click="clearCart"
            >
              Limpiar
            </button>
          </div>

          <div class="mt-4 min-h-0 overflow-hidden rounded-2xl border border-secondary">
            <div class="hidden grid-cols-[minmax(0,1.4fr)_120px_120px_140px_56px] gap-3 border-b border-secondary bg-secondary px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-text opacity-70 md:grid">
              <span>Producto</span>
              <span>Precio</span>
              <span>Cantidad</span>
              <span>Importe</span>
              <span></span>
            </div>

            <div class="max-h-[calc(100vh-20.5rem)] space-y-3 overflow-y-auto bg-background p-3">
              <SaleCartItemCard
                v-for="(item, index) in cart"
                :key="`${item.branch_product_id}-${index}`"
                :item="item"
                :format-money="formatMoney"
                :cart-item-unit-price="cartItemUnitPrice"
                :cart-item-subtotal="cartItemSubtotal"
                :cart-item-discount-amount="cartItemDiscountAmount"
                @increase="increaseQuantity(index)"
                @decrease="decreaseQuantity(index)"
                @remove="removeItem(index)"
                @toggle-discount="toggleDiscount(item)"
                @normalize-discount="normalizeDiscount(item)"
              />

              <EmptyStateCard
                v-if="cart.length === 0"
                title="Todavia no hay productos capturados"
                description="Escanea o busca uno para empezar."
                icon="shopping_cart"
                min-height-class="min-h-[340px]"
              />
            </div>
          </div>
        </section>

        <aside class="flex min-h-0 flex-col rounded-2xl border border-secondary bg-background p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-text opacity-50">
                Resumen
              </p>
              <h2 class="text-xl font-bold text-text">
                Venta actual
              </h2>
            </div>
          </div>

          <div class="mt-5 flex min-h-0 flex-1 flex-col">
            <div class="grid grid-cols-2 gap-3">
              <MetricCard label="Articulos" :value="totalLines" />

              <MetricCard label="Piezas" :value="totalItems" />
            </div>

            <div class="mt-4 flex flex-1 flex-col">
              <div class="space-y-4">
                <template v-if="isCashPayment">
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
                    <MetricCard
                      label="Cambio"
                      :value="formatMoney(changeDue)"
                      tone="success"
                      size="sm"
                    />

                    <MetricCard
                      label="Falta"
                      :value="formatMoney(missingAmount)"
                      tone="danger"
                      size="sm"
                    />
                  </div>
                </template>

                <div v-else class="grid grid-cols-1 gap-3">
                  <MetricCard
                    label="Total en tarjeta"
                    :value="formatMoney(cartTotal)"
                    tone="neutral"
                    size="sm"
                  />

                  <button
                    type="button"
                    class="flex items-center justify-between gap-3 rounded-2xl border px-4 py-3 text-left transition"
                    :class="cardPaymentConfirmed
                      ? 'border-accent bg-secondary text-accent'
                      : 'border-primary bg-secondary text-primary hover:bg-background'"
                    @click="cardPaymentConfirmed = !cardPaymentConfirmed"
                  >
                    <span class="flex min-w-0 items-center gap-3">
                      <span
                        class="material-symbols-outlined text-[22px]"
                        :class="cardPaymentConfirmed ? 'text-accent' : 'text-primary'"
                      >
                        {{ cardPaymentConfirmed ? 'task_alt' : 'credit_score' }}
                      </span>
                      <span class="min-w-0">
                        <span class="block text-sm font-black">
                          Terminal aprobada
                        </span>
                        <span class="block text-xs font-semibold opacity-75">
                          Confirma que el pago con tarjeta ya fue aceptado.
                        </span>
                      </span>
                    </span>
                    <span
                      class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border"
                      :class="cardPaymentConfirmed
                        ? 'border-accent bg-accent text-white'
                        : 'border-primary bg-background text-transparent'"
                    >
                      <span class="material-symbols-outlined text-[16px]">check</span>
                    </span>
                  </button>
                </div>

                <InfoCard
                  label="Forma de pago"
                  :value="selectedPaymentMethodLabel"
                />

                <InfoCard
                  label="Impresora"
                  :value="selectedPrinterName || 'Sin seleccionar'"
                  :description="printerBridgeMessage"
                >
                  <template #action>
                    <button
                      v-if="!printerBridgeReady"
                      type="button"
                      class="text-[11px] font-semibold text-text opacity-80 underline underline-offset-2"
                      @click="initializePrinterBridge({ silent: false })"
                    >
                      Reconectar
                    </button>
                  </template>
                </InfoCard>

                <MetricCard
                  label="Total venta"
                  :value="formatMoney(cartTotal)"
                  tone="dark"
                  size="lg"
                />
              </div>

              <div class="mt-auto space-y-3 pt-6">
                <button
                  v-if="lastPrintJob"
                  type="button"
                  class="inline-flex w-full items-center justify-center rounded-2xl border border-secondary bg-background px-4 py-3.5 text-base font-bold text-text transition hover:border-primary hover:bg-secondary"
                  @click="reprintLastTicket"
                >
                  Reimprimir ticket
                </button>

                <button
                  type="button"
                  class="inline-flex w-full items-center justify-center rounded-2xl border border-primary bg-primary px-4 py-4 text-base font-bold text-white transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50"
                  :disabled="saleForm.processing || !canCharge"
                  @click="submitSale"
                >
                  {{ !canCreateSale ? "Sin permiso para cobrar" : saleForm.processing ? "Guardando..." : "Cobrar venta" }}
                </button>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </template>
  </PageLayout>
</template>
