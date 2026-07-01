<script setup>
import { computed, nextTick, onMounted, ref, watch } from "vue";
import { router, useForm, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import InputField from "@/Components/Forms/InputField.vue";
import SelectField from "@/Components/Forms/SelectField.vue";
import {
  ToastAlert,
  ErrorAlert,
  BlockingWarningAlert,
} from "@/Components/Modales/UniversalActionModal";

defineOptions({
  layout: AdminLayout,
});

const page = usePage();

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
});

const search = ref("");
const searchInput = ref(null);
const selectedBranchId = ref(props.currentBranch?.id ?? "");
const cart = ref([]);
const lastTicketUrl = ref("");
const initialAlertBranchId = ref(null);
const highlightedSuggestionIndex = ref(0);

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
    lastTicketUrl.value = "";
    highlightedSuggestionIndex.value = 0;

    if (!props.selectorMode) {
      focusSearch();
    }
  },
  { immediate: true }
);

onMounted(() => {
  if (!props.selectorMode) {
    focusSearch();
  }
});

watch(
  () => [props.currentBranch?.id, props.nearExpirationAlerts],
  ([branchId, alerts]) => {
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
    .filter((product) =>
      String(product.name || "").toLowerCase().includes(query)
    )
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

function openTicket() {
  const ticketUrl = lastTicketUrl.value || page.props.flash?.ticket_url || "";

  if (!ticketUrl) return;

  window.open(ticketUrl, "_blank", "noopener,noreferrer");
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
    message: `${summary}<p style="margin:12px 0 0 0;">Frontealos para mover primero esas piezas.</p>`,
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
    onSuccess: (pageResponse) => {
      const flash = pageResponse.props.flash || {};
      lastTicketUrl.value = flash.ticket_url || "";

      ToastAlert({
        title: flash.sale_folio
          ? `Venta registrada: ${flash.sale_folio}`
          : "Venta registrada correctamente",
      });

      if ((flash.expiration_alerts || []).length) {
        showExpirationAlert(flash.expiration_alerts || []);
      }

      clearCart();
      saleForm.reset("items", "cash_received");
      saleForm.payment_method_id = props.defaultPaymentMethodId ?? "";

      if (lastTicketUrl.value) {
        window.setTimeout(() => {
          openTicket();
        }, 250);
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

          <div class="grid gap-3 sm:grid-cols-2 lg:min-w-[420px]">
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
          </div>
        </div>
      </div>

      <div class="grid h-[calc(100vh-10.5rem)] gap-4 px-4 py-4 lg:grid-cols-[minmax(0,1.45fr)_420px] lg:px-6">
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

            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
              <span class="block text-[11px] uppercase tracking-[0.16em] text-slate-400">Resultados</span>
              <span class="font-semibold text-slate-900">{{ filteredProducts.length }}</span>
              productos
            </div>
          </div>

          <div class="mt-4 grid max-h-[calc(100vh-18.5rem)] grid-cols-1 gap-3 overflow-y-auto pr-1 sm:grid-cols-2 xl:grid-cols-3">
            <button
              v-for="product in filteredProducts"
              :key="product.branch_product_id"
              type="button"
              class="group flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white text-left shadow-sm transition hover:-translate-y-[1px] hover:border-slate-300 hover:shadow-md"
              @click="addProduct(product)"
            >
              <div class="aspect-[4/3] bg-slate-100">
                <img
                  v-if="product.image"
                  :src="product.image"
                  :alt="product.name"
                  class="h-full w-full object-contain bg-white"
                />
                <div v-else class="flex h-full items-center justify-center">
                  <span class="material-symbols-outlined text-5xl text-slate-300">
                    inventory_2
                  </span>
                </div>
              </div>

              <div class="flex flex-1 flex-col gap-2 p-4">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-900">
                      {{ product.name }}
                    </p>
                    <p class="mt-1 truncate text-xs text-slate-500">
                      {{ product.barcode || "Sin codigo" }}
                    </p>
                  </div>

                  <span class="shrink-0 rounded-full bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-white">
                    {{ formatMoney(product.price) }}
                  </span>
                </div>

                <div class="mt-auto flex items-center justify-between text-xs text-slate-500">
                  <span>Stock: {{ Number(product.stock).toFixed(0) }}</span>
                  <span class="font-medium text-slate-700">{{ product.category || "Sin categoria" }}</span>
                </div>

                <div class="mt-2 rounded-xl bg-slate-50 px-3 py-2 text-center text-sm font-semibold text-slate-800 transition group-hover:bg-slate-900 group-hover:text-white">
                  Agregar
                </div>
              </div>
            </button>

            <div
              v-if="filteredProducts.length === 0"
              class="col-span-full flex min-h-[260px] items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 text-sm text-slate-500"
            >
              No hay productos que coincidan con la busqueda.
            </div>
          </div>
        </section>

        <aside class="min-h-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">
                Carrito
              </p>
              <h2 class="text-lg font-bold text-slate-900">
                Venta actual
              </h2>
            </div>

            <button
              type="button"
              class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50"
              @click="clearCart"
            >
              Limpiar
            </button>
          </div>

          <div class="mt-4 max-h-[calc(100vh-27rem)] space-y-3 overflow-y-auto pr-1">
            <article
              v-for="(item, index) in cart"
              :key="`${item.branch_product_id}-${index}`"
              class="rounded-2xl border border-slate-200 bg-slate-50 p-3"
            >
              <div class="flex gap-3">
                <div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl bg-white">
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

                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-semibold text-slate-900">
                    {{ item.name }}
                  </p>
                  <p class="truncate text-xs text-slate-500">
                    {{ item.barcode || "Sin codigo" }}
                  </p>
                  <p class="mt-1 text-sm font-semibold text-slate-900">
                    {{ formatMoney(cartItemUnitPrice(item)) }}
                  </p>
                </div>

                <button
                  type="button"
                  class="h-9 w-9 rounded-xl bg-white text-slate-500 transition hover:bg-slate-200"
                  @click="removeItem(index)"
                >
                  x
                </button>
              </div>

              <div class="mt-3 flex items-center justify-between gap-3">
                <div class="flex items-center rounded-xl border border-slate-200 bg-white">
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

                <div class="text-right">
                  <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">
                    Subtotal
                  </p>
                  <p class="text-base font-bold text-slate-900">
                    {{ formatMoney(cartItemSubtotal(item)) }}
                  </p>
                </div>
              </div>

              <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-3">
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

                <div v-if="item.discount_enabled" class="mt-3 grid gap-3">
                  <InputField
                    v-model="item.discount_percentage"
                    label="Porcentaje de descuento"
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
              class="flex min-h-[220px] items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 text-sm text-slate-500"
            >
              No has agregado productos todavia.
            </div>
          </div>

          <div class="mt-4 border-t border-slate-200 pt-4">
            <div class="flex items-center justify-between text-sm text-slate-600">
              <span>Productos</span>
              <span class="font-semibold text-slate-900">{{ totalItems }}</span>
            </div>

            <div class="mt-2 flex items-center justify-between">
              <span class="text-sm font-medium text-slate-600">Total</span>
              <span class="text-2xl font-black text-slate-900">
                {{ formatMoney(cartTotal) }}
              </span>
            </div>

            <div class="mt-4 grid gap-3">
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
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                  <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">
                    Cambio
                  </p>
                  <p class="mt-1 text-lg font-bold text-emerald-700">
                    {{ formatMoney(changeDue) }}
                  </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                  <p class="text-[11px] uppercase tracking-[0.14em] text-slate-400">
                    Falta
                  </p>
                  <p class="mt-1 text-lg font-bold text-rose-700">
                    {{ formatMoney(missingAmount) }}
                  </p>
                </div>
              </div>

              <button
                v-if="lastTicketUrl"
                type="button"
                class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                @click="openTicket"
              >
                Abrir ticket PDF
              </button>

              <button
                type="button"
                class="inline-flex items-center justify-center rounded-2xl bg-[#1f1d2b] px-4 py-3 text-sm font-bold text-white transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="saleForm.processing || !canCharge"
                @click="submitSale"
              >
                {{ saleForm.processing ? "Guardando..." : "Cobrar venta" }}
              </button>
            </div>
          </div>
        </aside>
      </div>
    </template>
  </div>
</template>
