<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import { GlobalToolbar } from "@/Components/Toolbars";
import { ChangeDueModal, GlobalModal } from "@/Components/Modales";
import InputField from "@/Components/Forms/InputField.vue";
import SelectField from "@/Components/Forms/SelectField.vue";
import SearchableSelectField from "@/Components/Forms/SearchableSelectField.vue";
import EmptyStateCard from "@/Components/Cards/EmptyStateCard.vue";
import MetricCard from "@/Components/Cards/MetricCard.vue";
import SaleCartItemCard from "@/Components/Ventas/SaleCartItemCard.vue";
import SaleBranchSelectorCard from "@/Components/Ventas/SaleBranchSelectorCard.vue";
import { getSalesToolbarConfig } from "@/config/ToolbarConfigs/salesToolbarConfig";
import {
  connectQzTray,
  findTicketPrinter,
  getQzPrinters,
  printEscPosTicket,
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
import {
  REALTIME_CHANNELS,
  REALTIME_EVENTS,
  refreshRealtimeProps,
  subscribeRealtime,
} from "@/realtime";

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
  creditAccounts: { type: Array, default: () => [] },
});

const page = usePage();
const { can } = usePermissions();
const CASH_BOX_STORAGE_KEY = "ventas_cash_box_by_branch";
const PRODUCT_SEARCH_DEBOUNCE_MS = 120;
const PRODUCT_SEARCH_CACHE_TTL_MS = 5000;
const TICKET_LOGO_URL = "/icons/super-kay-ticket-bw.png";

const search = ref("");
const searchInput = ref(null);
const remoteSearchProducts = ref([]);
const searchLoading = ref(false);
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
const selectedPrinterName = ref("");
const printerBridgeReady = ref(false);
const printerBridgeMessage = ref("Conecta QZ Tray para imprimir tickets.");
let searchDebounceTimer = null;
let searchRequestId = 0;
let productSearchCacheVersion = 0;
const productSearchCache = new Map();
const productSearchRequests = new Map();
let unsubscribeStockUpdated = null;
let unsubscribeProductChanged = null;
let unsubscribeCustomerChanged = null;
let realtimeMounted = false;
let ticketLogoDataUrlPromise = null;
const ticketHeaderDataUrlPromises = new Map();

const saleForm = useForm({
  branch_id: props.currentBranch?.id ?? "",
  cash_box_number: selectedCashBoxNumber.value,
  payment_method_id: props.defaultPaymentMethodId ?? "",
  cash_received: "",
  items: [],
  credit_holder_type: null,
  credit_holder_id: null,
  estimated_payment_date: null,
});
const saleSubmitting = ref(false);
const showCreditModal = ref(false);
const showPaymentModal = ref(false);
const creditHolder = ref("");
const creditDueDate = ref("");
const showChangeModal = ref(false);
const completedSaleChange = ref(0);
const completedSaleFolio = ref("");
const cashReceivedInput = ref(null);
const creditHolderInput = ref(null);

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

function clearPendingProductSearch() {
  if (!searchDebounceTimer) return;

  window.clearTimeout(searchDebounceTimer);
  searchDebounceTimer = null;
}

function clearProductSearchCache() {
  productSearchCacheVersion += 1;
  productSearchCache.clear();
  productSearchRequests.clear();
}

function refreshSalesCatalog() {
  clearProductSearchCache();

  return refreshRealtimeProps(page, [
    "productsDB",
    "nearExpirationAlerts",
  ]);
}

function subscribeSalesBranchRealtime(branchId) {
  unsubscribeStockUpdated?.();
  unsubscribeProductChanged?.();
  unsubscribeStockUpdated = null;
  unsubscribeProductChanged = null;

  if (!branchId || props.selectorMode) return;

  const channelName = REALTIME_CHANNELS.inventoryBranch(branchId);

  unsubscribeStockUpdated = subscribeRealtime(
    channelName,
    REALTIME_EVENTS.stockUpdated,
    refreshSalesCatalog,
  );
  unsubscribeProductChanged = subscribeRealtime(
    channelName,
    REALTIME_EVENTS.productChanged,
    refreshSalesCatalog,
  );
}

watch(
  () => props.currentBranch?.id,
  (branchId, previousBranchId) => {
    const branchChanged = String(branchId ?? "") !== String(previousBranchId ?? "");

    selectedBranchId.value = branchId ?? "";
    saleForm.branch_id = branchId ?? "";
    cart.value = [];
    search.value = "";
    saleForm.cash_received = "";
    cardPaymentConfirmed.value = false;
    if (branchChanged) {
      lastPrintJob.value = null;
    }
    expirationAlertPanelOpen.value = false;
    highlightedSuggestionIndex.value = 0;
    loadCashBoxForBranch(selectedBranchId.value);

    if (!props.selectorMode) {
      focusSearch();
    }

    if (realtimeMounted) {
      subscribeSalesBranchRealtime(branchId);
    }
  },
  { immediate: true }
);

onMounted(() => {
  realtimeMounted = true;

  if (!props.selectorMode) {
    focusSearch();
    subscribeSalesBranchRealtime(props.currentBranch?.id);
    void initializePrinterBridge({ silent: true });
  }

  unsubscribeCustomerChanged = subscribeRealtime(
    REALTIME_CHANNELS.systems,
    REALTIME_EVENTS.customerChanged,
    () => refreshRealtimeProps(page, ["creditAccounts"]),
  );
});

onBeforeUnmount(() => {
  realtimeMounted = false;
  clearPendingProductSearch();
  unsubscribeStockUpdated?.();
  unsubscribeProductChanged?.();
  unsubscribeCustomerChanged?.();
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

  if (remoteSearchProducts.value.length) {
    return remoteSearchProducts.value;
  }

  return props.productsDB.filter((product) =>
    (product.searchable || "").includes(query)
  );
});

const searchSuggestions = computed(() => {
  const query = search.value.trim().toLowerCase();

  if (!query) return [];

  if (remoteSearchProducts.value.length) {
    return filteredProducts.value.slice(0, 20);
  }

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

watch(
  () => [search.value, selectedBranchId.value],
  ([value, branchId]) => {
    const query = String(value || "").trim();

    clearPendingProductSearch();
    const requestId = ++searchRequestId;

    if (!query || !branchId || props.selectorMode) {
      remoteSearchProducts.value = [];
      searchLoading.value = false;
      return;
    }

    searchDebounceTimer = window.setTimeout(() => {
      searchDebounceTimer = null;
      void fetchProductsForSearch(query, requestId);
    }, PRODUCT_SEARCH_DEBOUNCE_MS);
  }
);

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
const canCreateSale = computed(() => can("sales.create"));
const canReturnToBranchSelector = computed(() =>
  !props.selectorMode && props.branchesDB.length > 1
);

watch(cartTotal, (total) => {
  if (!isCashPayment.value && saleForm.payment_method_id) {
    saleForm.cash_received = Number(total || 0).toFixed(2);
  }
});

const toolbarConfig = computed(() =>
  getSalesToolbarConfig({
    selectorMode: props.selectorMode,
    selectorDescription: selectorDescription.value,
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

function isPublicPwaOriginForQz() {
  if (typeof window === "undefined") {
    return false;
  }

  const hostname = window.location.hostname || "";

  return !["localhost", "127.0.0.1", "::1"].includes(hostname)
    && !hostname.endsWith(".local");
}

function readablePrinterBridgeError(error) {
  const message = String(error?.message || error || "");

  if (
    isPublicPwaOriginForQz()
    && /unable to establish connection|qz tray no esta conectado|websocket|connection/i.test(message)
  ) {
    return "Chrome esta bloqueando la conexion local con QZ Tray porque la PWA se abrio desde una URL publica. Para imprimir tickets, abre el POS desde localhost/LAN o desactiva temporalmente la revision de acceso local del navegador.";
  }

  return message || "QZ Tray no esta conectado en esta computadora.";
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
    printerBridgeMessage.value = readablePrinterBridgeError(error);

    if (!silent) {
      ErrorAlert({
        title: "No se pudo conectar la impresora",
        message: readablePrinterBridgeError(error),
      });
    }
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
  } catch (error) {
    printerBridgeReady.value = false;
    printerBridgeMessage.value =
      readablePrinterBridgeError(error);

    if (!silent) {
      ErrorAlert({
        title: "No se pudo conectar la impresora",
        message: readablePrinterBridgeError(error),
      });
    }
    throw error;
  }
}

function resolvePrintJob(printJob) {
  const cashBoxNumber = String(printJob?.cash_box_number || selectedCashBoxNumber.value || "1");

  return {
    ...printJob,
    ticket_header_data_url: printJob?.ticket_header_data_url || "",
    ticket_logo_data_url: printJob?.ticket_logo_data_url || "",
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

  if (!printerBridgeReady.value) {
    throw new Error("La impresora no esta verificada. Conectala y usa Reconectar antes de imprimir.");
  }

  const resolvedBasePrintJob = resolvePrintJob(printJob);
  const resolvedPrintJob = {
    ...resolvedBasePrintJob,
    ticket_logo_data_url: printJob.ticket_logo_data_url || await getTicketLogoDataUrl(),
    ticket_header_data_url: printJob.ticket_header_data_url || await getTicketHeaderDataUrl(resolvedBasePrintJob.cash_box_text),
  };
  const printData = buildEscPosTicketData(resolvedTicketTemplate.value, resolvedPrintJob);
  await printEscPosTicket(selectedPrinterName.value, printData, {
    timeoutMs: 10000,
  });
  printerBridgeReady.value = true;
  printerBridgeMessage.value = `Impresora lista: ${selectedPrinterName.value}`;
}

function queueTicketPrint(printJob) {
  window.setTimeout(async () => {
    try {
      await printTicket(printJob);
    } catch (error) {
      printerBridgeReady.value = false;
      printerBridgeMessage.value = error?.message || "La venta quedo registrada, pero el ticket no salio.";
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

function cartItemAvailableQuantity(item) {
  if (item.presentation === "box") {
    return Math.floor(Number(item.stock || 0) / Number(item.pieces_per_box || 1));
  }

  return Number(item.stock || 0);
}

function setCartPresentation(index, presentation) {
  const item = cart.value[index];
  if (!item || (presentation === "box" && !item.has_box_presentation)) return;

  item.presentation = presentation;
  item.original_price = presentation === "box"
    ? Number(item.sale_price_per_box || 0)
    : Number(item.sale_price_per_piece || item.price || 0);
  item.available_quantity = cartItemAvailableQuantity(item);
  item.quantity = Math.max(1, Number(item.quantity || 1));
}

function saleQuantityStep(item) {
  return item.presentation === "piece" && item.inventory_unit === "kg" ? 0.001 : 1;
}

function updateCartQuantity(index, value) {
  const item = cart.value[index];
  if (!item) return;

  const step = saleQuantityStep(item);
  const raw = Number(String(value ?? "").replace(",", "."));
  if (!Number.isFinite(raw)) return;

  const normalized = step === 1
    ? Math.floor(raw)
    : Math.round(raw * 1000) / 1000;
  item.quantity = Math.max(step, normalized);
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
    existing.quantity += 1;
    search.value = "";
    focusSearch();
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
    sale_price_per_piece: Number(product.sale_price_per_piece ?? product.price ?? 0),
    sale_price_per_box: Number(product.sale_price_per_box ?? 0),
    has_box_presentation: Boolean(product.has_box_presentation),
    pieces_per_box: Number(product.pieces_per_box || 0),
    inventory_unit: product.inventory_unit ?? "pza",
    presentation: "piece",
    stock: Number(product.stock || 0),
    available_quantity: Number(product.stock || 0),
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

  return filteredProducts.value.find((product) => {
    if (String(product.barcode || "").trim().toLowerCase() === normalized) return true;
    if (String(product.name || "").trim().toLowerCase() === normalized) return true;

    return (product.barcodes || []).some((code) => String(code).trim().toLowerCase() === normalized);
  });
}

async function fetchProductsForSearch(query, requestId = ++searchRequestId, { throwOnError = false } = {}) {
  const term = String(query || "").trim();
  const branchId = selectedBranchId.value;

  if (!term || !branchId || props.selectorMode) {
    remoteSearchProducts.value = [];
    return [];
  }

  const requestKey = `${branchId}:${term.toLocaleLowerCase("es-MX")}`;
  const cacheVersion = productSearchCacheVersion;
  const cached = productSearchCache.get(requestKey);

  if (cached && Date.now() - cached.createdAt < PRODUCT_SEARCH_CACHE_TTL_MS) {
    if (requestId === searchRequestId) {
      remoteSearchProducts.value = cached.products;
      searchLoading.value = false;
    }

    return cached.products;
  }

  let request = productSearchRequests.get(requestKey);

  if (!request) {
    request = window.axios
      .get(route("ventas.products.search"), {
        params: {
          branch_id: branchId,
          search: term,
        },
        headers: {
          Accept: "application/json",
        },
        timeout: 8000,
      })
      .then(({ data }) => {
        const products = Array.isArray(data?.products) ? data.products : [];

        if (cacheVersion === productSearchCacheVersion) {
          productSearchCache.set(requestKey, {
            createdAt: Date.now(),
            products,
          });
        }

        return products;
      });

    productSearchRequests.set(requestKey, request);
    request.then(
      () => {
        if (productSearchRequests.get(requestKey) === request) {
          productSearchRequests.delete(requestKey);
        }
      },
      () => {
        if (productSearchRequests.get(requestKey) === request) {
          productSearchRequests.delete(requestKey);
        }
      }
    );
  }

  if (requestId === searchRequestId) {
    searchLoading.value = true;
  }

  try {
    const products = await request;

    if (requestId !== searchRequestId) {
      return remoteSearchProducts.value;
    }

    remoteSearchProducts.value = products;

    return products;
  } catch (error) {
    if (requestId === searchRequestId) {
      remoteSearchProducts.value = [];
    }

    if (throwOnError) {
      throw error;
    }

    return [];
  } finally {
    if (requestId === searchRequestId) {
      searchLoading.value = false;
    }
  }
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

async function handleSearchKeydown(event) {
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
  if (!query) {
    openPaymentModal();
    return;
  }

  if (!searchSuggestions.value.length) {
    clearPendingProductSearch();

    try {
      await fetchProductsForSearch(query, ++searchRequestId, { throwOnError: true });
    } catch (error) {
      ErrorAlert({
        title: "No se pudo buscar",
        message: "Revisa la conexion e intenta escanear o escribir de nuevo.",
      });
      return;
    }
  }

  if (searchSuggestions.value.length) {
    selectSuggestion(
      searchSuggestions.value[
        Math.min(highlightedSuggestionIndex.value, searchSuggestions.value.length - 1)
      ]
    );
    return;
  }

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

  item.quantity = Number((Number(item.quantity) + saleQuantityStep(item)).toFixed(3));
}

function decreaseQuantity(index) {
  const item = cart.value[index];
  if (!item) return;

  const step = saleQuantityStep(item);
  if (Number(item.quantity) <= step) {
    cart.value.splice(index, 1);
    return;
  }

  item.quantity = Number((Number(item.quantity) - step).toFixed(3));
}

function removeItem(index) {
  cart.value.splice(index, 1);
}

function clearCart() {
  cart.value = [];
  search.value = "";
  saleForm.cash_received = "";
  saleForm.credit_holder_type = null;
  saleForm.credit_holder_id = null;
  saleForm.estimated_payment_date = null;
  creditHolder.value = "";
  creditDueDate.value = "";
  showCreditModal.value = false;
  showPaymentModal.value = false;
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

function openPaymentModal() {
  if (!cart.value.length) {
    return;
  }

  showPaymentModal.value = true;
  nextTick(() => {
    if (isCashPayment.value) {
      cashReceivedInput.value?.focus?.();
    }
  });
}

function closePaymentModal() {
  showPaymentModal.value = false;
  focusSearch();
}

function handlePaymentMethodSelection(value) {
  handlePaymentMethodChange(value);

  nextTick(() => {
    if (isCashPayment.value) {
      cashReceivedInput.value?.focus?.();
    }
  });
}

function submitPaymentFromModal() {
  if (saleSubmitting.value) return;

  void submitSale();
}

function handlePaymentKeydown(event) {
  if (event.key !== "Enter") return;

  event.preventDefault();
  submitPaymentFromModal();
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
}

function handleToolbarAction(actionId) {
  if (actionId === "toggle-expiration-alerts") {
    toggleExpirationAlerts();
    return;
  }

  if (actionId === "open-cash-drawer") {
    void openCashDrawer();
  }
}

async function openCashDrawer() {
  if (!printerBridgeReady.value || !selectedPrinterName.value) {
    await initializePrinterBridge({ silent: false });
  }

  if (!printerBridgeReady.value || !selectedPrinterName.value) {
    return;
  }

  try {
    await printEscPosTicket(selectedPrinterName.value, [
      "\x1B\x70\x00\x19\xFA",
      "\x1B\x70\x01\x19\xFA",
      "\x10\x14\x01\x00\x05",
    ]);
    ToastAlert({ title: "Caja abierta" });
  } catch (error) {
    ErrorAlert({
      title: "No se pudo abrir la caja",
      message: readablePrinterBridgeError(error),
    });
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
      const badgeLabel = escapeHtml(expirationBadgeLabel(alert));
      const productName = escapeHtml(alert.product_name || "Producto sin nombre");
      const lot = escapeHtml(alert.lot_number || "Sin lote");
      const quantity = Number(alert.quantity || 0).toFixed(0);
      const expirationDate = escapeHtml(alert.formatted_expiration_date || alert.expiration_date || "Fecha no disponible");

      return `<article style="margin:0 0 10px;padding:12px;border:1px solid color-mix(in srgb, var(--primary) 34%, var(--secondary));border-radius:14px;background:var(--secondary);color:var(--text);text-align:left;">
        <div style="display:flex;gap:10px;align-items:flex-start;justify-content:space-between;">
          <strong style="color:var(--text);font-size:14px;line-height:1.35;">${productName}</strong>
          <span style="flex:0 0 auto;border-radius:999px;background:var(--primary);color:var(--text-on-primary);padding:4px 8px;font-size:11px;font-weight:700;white-space:nowrap;">${badgeLabel}</span>
        </div>
        <p style="margin:7px 0 0;color:var(--text-muted);font-size:12px;line-height:1.4;">Lote ${lot} · ${quantity} unidades</p>
        <p style="margin:3px 0 0;color:var(--text-subtle);font-size:12px;line-height:1.4;">Caduca: ${expirationDate}</p>
      </article>`;
    })
    .join("");

  BlockingWarningAlert({
    title: "Lotes próximos a vencer",
    message: `<div style="margin:0 0 12px;color:var(--text-muted);font-size:13px;line-height:1.45;">Revisa estos lotes antes de continuar con la venta.</div><div style="max-height:300px;overflow-y:auto;padding-right:4px;">${summary}</div><p style="margin:12px 0 0;color:var(--text-subtle);font-size:12px;line-height:1.4;">La campana permanecerá disponible arriba para consultarlos durante la venta.</p>`,
    confirmButtonColor: "var(--primary)",
  });
}

function handleSaleRegistered(payload = {}, completion = {}) {
  const printJob = payload.print_job ? resolvePrintJob(payload.print_job) : null;

  if (printJob) {
    printJob.ticket_logo_data_url = "";
    lastPrintJob.value = printJob;
  }

  ToastAlert({
    title: payload.sale_folio
      ? `Venta registrada: ${payload.sale_folio}`
      : "Venta registrada correctamente",
  });

  if (completion.showChange) {
    completedSaleChange.value = Number(printJob?.change_due ?? completion.change ?? 0);
    completedSaleFolio.value = payload.sale_folio || printJob?.folio || "";
    showChangeModal.value = true;
  }

  showPaymentModal.value = false;

  replaceExpirationAlerts(payload.expiration_alerts || []);

  if ((payload.expiration_alerts || []).length) {
    expirationAlertPulse.value = true;
    window.setTimeout(() => {
      expirationAlertPulse.value = false;
    }, 1600);
  }

  clearProductSearchCache();
  clearCart();
  saleForm.reset("items", "cash_received");
  saleForm.payment_method_id = props.defaultPaymentMethodId ?? "";
  void refreshRealtimeProps(page, ["nearExpirationAlerts"]);

  if (printJob) {
    if (printerBridgeReady.value && selectedPrinterName.value) {
      queueTicketPrint(printJob);
    } else {
      printerBridgeMessage.value = "La venta quedo registrada. Reconecta la impresora y usa Reimprimir ticket.";
      ToastAlert({
        icon: "warning",
        title: "Venta registrada sin ticket: impresora no verificada",
      });
    }
    return;
  }

  printerBridgeMessage.value = "La venta se registro, pero el sistema no recibio el ticket para imprimir.";
  WarningAlert({
    title: "Venta registrada sin ticket",
    message: "No se recibio la informacion del ticket desde el servidor. Revisa la venta en reportes antes de reimprimir.",
  });
}

function openCreditModal() {
  if (!cart.value.length) return ErrorAlert({ title: "Venta vacía", message: "Agrega productos antes de registrar un fiado." });
  showPaymentModal.value = false;
  creditHolder.value = "";
  creditDueDate.value = "";
  showCreditModal.value = true;
  nextTick(() => creditHolderInput.value?.focus?.());
}

async function submitSale(creditSelection = null, estimatedPaymentDate = null) {
  const [selectedCreditType, selectedCreditIdText] = String(creditSelection || "").split(":");
  const selectedCreditId = Number.isFinite(Number(selectedCreditIdText)) && Number(selectedCreditIdText) > 0
    ? Number(selectedCreditIdText)
    : null;
  const hasCreditHolder = ["employee", "customer"].includes(selectedCreditType) && selectedCreditId;
  const shouldShowChange = !hasCreditHolder && isCashPayment.value;
  const expectedChange = changeDue.value;

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

  if (!saleForm.payment_method_id && !hasCreditHolder) {
    ErrorAlert({
      title: "Forma de pago requerida",
      message: "Selecciona efectivo o pago con tarjeta antes de cobrar.",
    });
    return;
  }

  if (!hasCreditHolder && isCashPayment.value && receivedAmount.value < cartTotal.value) {
    ErrorAlert({
      title: "Efectivo insuficiente",
      message: "El monto recibido debe cubrir el total de la venta.",
    });
    return;
  }

  if (!hasCreditHolder && !isCashPayment.value && !cardPaymentConfirmed.value) {
    ErrorAlert({
      title: "Confirma el cobro",
      message: "Marca la confirmación del cobro.",
    });
    return;
  }

  saleForm.branch_id = selectedBranchId.value || props.currentBranch?.id || "";
  saleForm.cash_box_number = String(selectedCashBoxNumber.value || "1");
  saleForm.cash_received = hasCreditHolder ? 0 : (isCashPayment.value
    ? Number(saleForm.cash_received || 0)
    : Number(cartTotal.value || 0));
  saleForm.credit_holder_type = hasCreditHolder ? selectedCreditType : null;
  saleForm.credit_holder_id = hasCreditHolder ? selectedCreditId : null;
  saleForm.estimated_payment_date = hasCreditHolder ? (estimatedPaymentDate || null) : null;
  saleForm.items = cart.value.map((item) => ({
    branch_product_id: item.branch_product_id,
    product_id: item.product_id,
    barcode_id: item.barcode_id ?? null,
    quantity: item.quantity,
    presentation: item.presentation,
    original_unit_price: Number(item.original_price || 0),
    discount_percentage: item.discount_enabled
      ? Number(item.discount_percentage || 0)
      : 0,
    discount_amount: cartItemDiscountAmount(item),
    unit_price: cartItemUnitPrice(item),
  }));

  saleSubmitting.value = true;
  saleForm.clearErrors();

  try {
    const { data } = await window.axios.post(route("ventas.store"), saleForm.data(), {
      headers: {
        Accept: "application/json",
      },
    });

    handleSaleRegistered(data || {}, {
      showChange: shouldShowChange,
      change: expectedChange,
    });
  } catch (error) {
    if (error?.response?.status === 422 && error.response.data?.errors) {
      saleForm.setError(error.response.data.errors);
    }

      const validationMessage = error?.response?.data?.message
        || Object.values(error?.response?.data?.errors || {}).flat().filter(Boolean).join(" ");

      ErrorAlert({
        title: "No se pudo guardar la venta",
        message: validationMessage || "Revisa el stock, el efectivo recibido o la sucursal seleccionada.",
      });
  } finally {
    saleSubmitting.value = false;
  }
}

function submitCreditSale() {
  if (!creditHolder.value) return ErrorAlert({ title: "Persona requerida", message: "Selecciona a quién se cargará la compra." });
  showCreditModal.value = false;
  void submitSale(creditHolder.value, creditDueDate.value || null);
}

function handleCreditHolderKeydown(event) {
  if (event.key !== "Enter" || !creditHolder.value) return;

  event.preventDefault();
  submitCreditSale();
}

function closeChangeModal() {
  showChangeModal.value = false;
  completedSaleChange.value = 0;
  completedSaleFolio.value = "";
  focusSearch();
}
</script>

<template>
  <PageLayout fill-height>
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
      <GlobalModal
          v-if="expirationAlertPanelOpen"
          title="Alertas de caducidad"
          size="md"
          height="auto"
          :columns="1"
          :show-header="false"
          :show-footer="false"
          :show-save="false"
          @close="closeExpirationAlerts"
      >
        <template #header="{ close }">
          <header class="border-b border-secondary bg-primary px-5 py-4 text-white">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-white/80">
                  Alertas de caducidad
                </p>
                <h2 class="mt-1 text-lg font-black">
                  {{ expirationAlertCount ? `${expirationAlertCount} lote(s) por atender` : "Sin alertas" }}
                </h2>
                <p v-if="urgentExpirationAlertCount" class="mt-1 text-xs text-white/80">
                  {{ urgentExpirationAlertCount }} urgente(s) en 7 dias o menos.
                </p>
              </div>

              <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/10 text-white transition hover:bg-white/20"
                aria-label="Cerrar alertas de caducidad"
                @click="close"
              >
                <span class="material-symbols-outlined text-[18px]">close</span>
              </button>
            </div>
          </header>
        </template>

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
      </GlobalModal>

      <div class="min-h-0 flex-1">
        <section class="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-secondary bg-background p-3 shadow-sm lg:h-full md:p-4">
          <div class="flex shrink-0 flex-col gap-3 sm:flex-row sm:items-end">
            <div class="min-w-0 flex-1">
              <div class="relative">
                <InputField
                  ref="searchInput"
                  label="Buscar o escanear"
                  field="sales_search"
                  validation-field="toolbar_search"
                  v-model="search"
                  icon="barcode_scanner"
                  placeholder="Codigo de barras o nombre del producto"
                  autocomplete="off"
                  @keydown="handleSearchKeydown"
                />

                <div
                  v-if="searchSuggestions.length || searchLoading"
                  class="absolute left-0 right-0 top-[calc(100%+0.25rem)] z-20 overflow-hidden rounded-2xl border border-secondary bg-background shadow-xl"
                >
                  <div
                    v-if="searchLoading && !searchSuggestions.length"
                    class="px-4 py-3 text-sm font-semibold text-text opacity-70"
                  >
                    Buscando productos...
                  </div>

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

            <button
              type="button"
              class="h-[46px] shrink-0 rounded-lg border border-secondary bg-background px-4 text-sm font-semibold text-text transition hover:border-primary hover:bg-secondary sm:mb-5"
              @click="clearCart"
            >
              Limpiar
            </button>
          </div>

          <div class="mt-3 flex min-h-0 flex-1 flex-col overflow-hidden">
            <div class="hidden grid-cols-[minmax(0,1.4fr)_120px_120px_140px_88px] gap-3 border-b border-secondary px-1 py-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-text opacity-70 md:grid">
              <span>Producto</span>
              <span>Precio</span>
              <span>Cantidad</span>
              <span>Importe</span>
              <span></span>
            </div>

            <div class="min-h-0 flex-1 divide-y divide-secondary overflow-y-auto">
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
                @set-presentation="setCartPresentation(index, $event)"
                @update-quantity="updateCartQuantity(index, $event)"
                @toggle-discount="toggleDiscount(item)"
                @normalize-discount="normalizeDiscount(item)"
              />

              <div
                v-if="cart.length === 0"
                class="flex min-h-[260px] items-center justify-center bg-background px-6 text-center"
              >
                <div>
                  <span class="material-symbols-outlined text-4xl text-text opacity-35">shopping_cart</span>
                  <p class="mt-2 text-sm font-semibold text-text">Todavia no hay productos capturados</p>
                  <p class="mt-1 text-sm text-text opacity-70">Escanea o busca uno para empezar.</p>
                </div>
              </div>
            </div>

            <div class="mt-3 flex shrink-0 items-center justify-between gap-3 rounded-xl border border-primary/30 bg-secondary px-4 py-3">
              <div class="min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-text opacity-60">Total de la venta</p>
                <p class="mt-1 text-2xl font-black leading-none text-text">{{ formatMoney(cartTotal) }}</p>
              </div>
              <button
                type="button"
                class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-primary bg-primary px-5 py-3 text-sm font-bold text-white transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="saleSubmitting || !cart.length"
                @click="openPaymentModal"
              >
                <span class="material-symbols-outlined text-[20px]">payments</span>
                Cobrar
              </button>
            </div>
          </div>
        </section>

        <aside class="hidden">
          <div class="flex shrink-0 items-start justify-between gap-3">
            <div>
              <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-text opacity-50">
                Resumen
              </p>
              <h2 class="text-base font-bold text-text 2xl:text-lg">
                Venta actual
              </h2>
            </div>
            <div class="text-right text-xs font-semibold leading-5 text-text opacity-70">
              <p>{{ selectedPaymentMethodLabel }}</p>
              <p>Caja #{{ selectedCashBoxNumber }}</p>
            </div>
          </div>

          <div class="mt-3 flex min-h-0 flex-1 flex-col">
            <div class="grid grid-cols-2 divide-x divide-secondary rounded-lg bg-secondary px-3 py-2 text-sm">
              <div class="pr-3">
                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-text opacity-50">
                  Articulos
                </p>
                <p class="mt-0.5 text-lg font-bold text-text">
                  {{ totalLines }}
                </p>
              </div>

              <div class="pl-3">
                <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-text opacity-50">
                  Piezas
                </p>
                <p class="mt-0.5 text-lg font-bold text-text">
                  {{ totalItems }}
                </p>
              </div>
            </div>

            <div class="mt-3 min-h-0 flex-1 overflow-y-auto lg:pr-1">
              <div class="space-y-2.5">
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

                  <div class="grid grid-cols-2 gap-2 rounded-lg bg-secondary px-3 py-2 text-sm">
                    <div>
                      <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-accent">
                        Cambio
                      </p>
                      <p class="mt-0.5 font-bold text-accent">
                        {{ formatMoney(changeDue) }}
                      </p>
                    </div>

                    <div>
                      <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-primary">
                        Falta
                      </p>
                      <p class="mt-0.5 font-bold text-primary">
                        {{ formatMoney(missingAmount) }}
                      </p>
                    </div>
                  </div>
                </template>

                <div v-else class="grid grid-cols-1 gap-2">
                  <button
                    type="button"
                    class="flex items-center justify-between gap-3 rounded-lg border px-3 py-2.5 text-left transition"
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
                        <span class="block text-sm font-bold">
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

                <div class="rounded-lg bg-secondary px-3 py-2 text-sm">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-text opacity-50">
                        Impresora
                      </p>
                      <p class="mt-0.5 truncate font-semibold text-text">
                        {{ selectedPrinterName || 'Sin seleccionar' }}
                      </p>
                      <p class="mt-0.5 line-clamp-2 text-xs text-text opacity-65">
                        {{ printerBridgeMessage }}
                      </p>
                    </div>
                    <button
                      v-if="!printerBridgeReady"
                      type="button"
                      class="shrink-0 rounded-lg border border-secondary bg-background px-3 py-2 text-xs font-semibold text-text transition hover:border-primary"
                      @click="initializePrinterBridge({ silent: false })"
                    >
                      Reconectar
                    </button>
                  </div>
                </div>

                <div class="rounded-lg bg-primary px-4 py-3 text-white">
                  <p class="text-[10px] font-semibold uppercase tracking-[0.12em] opacity-80">
                    Total venta
                  </p>
                  <p class="mt-1 text-2xl font-black leading-none">
                    {{ formatMoney(cartTotal) }}
                  </p>
                </div>
              </div>

            </div>

            <div class="shrink-0 space-y-2 border-t border-secondary pt-3">
                <button
                  v-if="can('sales.employee-credit.create')"
                  type="button"
                  class="inline-flex w-full items-center justify-center rounded-lg border border-primary bg-background px-4 py-2.5 text-sm font-bold text-primary transition hover:bg-secondary disabled:cursor-not-allowed disabled:opacity-50"
                  :disabled="saleSubmitting || !cart.length"
                  @click="openCreditModal"
                >
                  Venta a crédito
                </button>
                <button
                  v-if="lastPrintJob"
                  type="button"
                  class="inline-flex w-full items-center justify-center rounded-lg border border-secondary bg-background px-4 py-2.5 text-sm font-bold text-text transition hover:border-primary hover:bg-secondary"
                  @click="reprintLastTicket"
                >
                  Reimprimir ticket
                </button>

                <button
                  type="button"
                  class="inline-flex w-full items-center justify-center rounded-lg border border-primary bg-primary px-4 py-3 text-base font-bold text-white transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50"
                  :disabled="saleSubmitting || !canCharge"
                  @click="submitSale()"
                >
                  {{ !canCreateSale ? "Sin permiso para cobrar" : saleSubmitting ? "Guardando..." : "Cobrar venta" }}
                </button>
              </div>
          </div>
        </aside>
      </div>

    <GlobalModal
      v-if="showPaymentModal"
      title="Cobrar venta"
      subtitle="Confirma el pago para registrar la venta."
      size="lg"
      height="auto"
      :columns="1"
      :processing="saleSubmitting"
      save-button-text="Cobrar venta"
      close-button-text="Cancelar"
      @close="closePaymentModal"
      @save="submitPaymentFromModal"
    >
      <div class="space-y-4">
        <div class="grid grid-cols-2 divide-x divide-secondary rounded-xl bg-secondary px-4 py-3">
          <div class="pr-4">
            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-text opacity-60">Artículos</p>
            <p class="mt-1 text-xl font-black text-text">{{ totalLines }}</p>
          </div>
          <div class="pl-4">
            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-text opacity-60">Total a cobrar</p>
            <p class="mt-1 text-xl font-black text-primary">{{ formatMoney(cartTotal) }}</p>
          </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
          <SelectField
            v-model="saleForm.payment_method_id"
            label="Método de pago"
            field="payment_method_id"
            :options="paymentMethodsDB"
            placeholder="Selecciona pago"
            @change="handlePaymentMethodSelection"
          />
          <SelectField
            v-model="selectedCashBoxNumber"
            label="Caja"
            field="cash_box"
            :options="cashBoxOptions"
            @change="handleCashBoxChange"
          />
        </div>

        <template v-if="isCashPayment">
          <InputField
            ref="cashReceivedInput"
            v-model="saleForm.cash_received"
            label="Efectivo recibido"
            field="cash_received"
            type="number"
            placeholder="0.00"
            :error="saleForm.errors.cash_received"
            prefix="$"
            data-modal-autofocus
            @keydown="handlePaymentKeydown"
          />

          <div class="grid grid-cols-2 gap-3 rounded-xl bg-secondary px-4 py-3 text-sm">
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-accent">Cambio</p>
              <p class="mt-1 text-lg font-black text-accent">{{ formatMoney(changeDue) }}</p>
            </div>
            <div>
              <p class="text-[10px] font-semibold uppercase tracking-[0.12em] text-primary">Falta</p>
              <p class="mt-1 text-lg font-black text-primary">{{ formatMoney(missingAmount) }}</p>
            </div>
          </div>
        </template>

        <button
          v-else
          type="button"
          class="flex w-full items-center justify-between gap-3 rounded-xl border px-4 py-3 text-left transition"
          :class="cardPaymentConfirmed ? 'border-accent bg-secondary text-accent' : 'border-primary bg-secondary text-primary hover:bg-background'"
          @click="cardPaymentConfirmed = !cardPaymentConfirmed"
        >
          <span>
            <span class="block text-sm font-bold">Terminal aprobada</span>
            <span class="mt-1 block text-xs font-semibold opacity-75">Confirma que el pago con tarjeta ya fue aceptado.</span>
          </span>
          <span class="material-symbols-outlined text-2xl">{{ cardPaymentConfirmed ? 'task_alt' : 'credit_score' }}</span>
        </button>

        <button
          v-if="can('sales.employee-credit.create')"
          type="button"
          class="inline-flex w-full items-center justify-center rounded-xl border border-primary bg-background px-4 py-3 text-sm font-bold text-primary transition hover:bg-secondary"
          :disabled="saleSubmitting"
          @click="openCreditModal"
        >
          Venta a crédito
        </button>
      </div>
    </GlobalModal>

    <GlobalModal
      v-if="showCreditModal"
      title="Venta a crédito"
      subtitle="Selecciona un empleado o un cliente independiente para registrar la venta."
      size="lg"
      height="auto"
      :columns="1"
      save-button-text="Registrar fiado"
      close-button-text="Cancelar"
      @close="showCreditModal = false"
      @save="submitCreditSale"
    >
      <div class="space-y-4">
        <SearchableSelectField ref="creditHolderInput" v-model="creditHolder" label="Cliente o empleado" field="credit_holder_id" :options="creditAccounts" option-label="name" option-value="value" placeholder="Escribe el nombre del cliente o empleado" empty-message="No se encontró ningún cliente o empleado." :error="saleForm.errors.credit_holder_id" @keydown="handleCreditHolderKeydown" />
        <InputField v-model="creditDueDate" label="Fecha estimada de pago (opcional)" field="estimated_payment_date" type="date" />
        <MetricCard label="Cargo a cuenta" :value="formatMoney(cartTotal)" tone="dark" size="lg" />
      </div>
    </GlobalModal>

    <ChangeDueModal
      v-if="showChangeModal"
      :subtitle="completedSaleFolio ? `Venta ${completedSaleFolio} registrada correctamente` : 'Venta registrada correctamente'"
      :amount="completedSaleChange"
      @close="closeChangeModal"
    />

    </template>
  </PageLayout>
</template>
