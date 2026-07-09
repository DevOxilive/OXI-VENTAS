<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useForm } from "@inertiajs/vue3";

import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import { ToastAlert, WarningAlert } from "@/Components/Modales/UniversalActionModal";
import {
  buildLabelPngBase64,
  buildLabelPreviewBlocks,
  createDefaultLabelTemplate,
  getLabelBlockCatalog,
  normalizeLabelTemplate,
  reorderLabelBlocks,
  resolveLabelPrintDimensions,
  saveStoredLabelTemplateSettings,
} from "@/config/labelTemplate";
import {
  getDefaultQzPrinter,
  getQzPrinters,
  getStoredPrinterName,
  printImageLabel,
  saveStoredPrinterName,
} from "@/Composables/useQzTray";

defineOptions({
  layout: AdminLayout,
});

const props = defineProps({
  template: {
    type: Object,
    required: true,
  },
  sampleProduct: {
    type: Object,
    required: true,
  },
  products: {
    type: Array,
    default: () => [],
  },
});

const blockCatalog = getLabelBlockCatalog();
const selectedProductId = ref(props.products[0]?.id || props.sampleProduct?.id || "");
const productSearch = ref("");
const dragIndex = ref(null);
const previewLabel = ref(null);
const activePointerBlockKey = ref(null);
const activeHorizontalKey = ref(null);
const availablePrinters = ref([]);
const selectedPrinterName = ref(getStoredPrinterName());
const printerBridgeReady = ref(false);
const printerBridgeMessage = ref("Conecta QZ Tray para imprimir etiquetas.");
const printing = ref(false);
const printCopies = ref(1);


function withoutCustomTextBlock(settings) {
  const normalized = normalizeLabelTemplate(settings);

  return {
    ...normalized,
    custom_text: "",
    blocks: normalized.blocks.map((block) => (
      block.key === "custom_text"
        ? { ...block, enabled: false }
        : block
    )),
  };
}
const form = useForm({
  name: props.template?.name || "Etiqueta de producto",
  is_active: props.template?.is_active ?? true,
  settings: withoutCustomTextBlock(props.template?.settings || createDefaultLabelTemplate()),
});

const previewTemplate = computed(() => normalizeLabelTemplate({ ...form.settings }));
const editableProductName = ref("");

const baseSelectedProduct = computed(() => (
  props.products.find((product) => String(product.id) === String(selectedProductId.value))
  || props.sampleProduct
));

watch(baseSelectedProduct, (product) => {
  editableProductName.value = product?.name || "";
}, { immediate: true });

const selectedProduct = computed(() => ({
  ...baseSelectedProduct.value,
  name: editableProductName.value.trim() || baseSelectedProduct.value?.name || "",
}));
const previewBlocks = computed(() => buildLabelPreviewBlocks(previewTemplate.value, selectedProduct.value));
const configurableBlocks = computed(() => (
  form.settings.blocks
    .map((block, originalIndex) => ({ ...block, originalIndex }))
    .filter((block) => block.key !== "custom_text")
));
const productOptions = computed(() => props.products.map((product) => ({
  label: `${product.name} - $${Number(product.price || 0).toFixed(2)}`,
  value: product.id,
})));
const filteredProductOptions = computed(() => {
  const query = productSearch.value.trim().toLowerCase();

  if (!query) {
    return productOptions.value;
  }

  return productOptions.value.filter((option) => (
    String(option.label).toLowerCase().includes(query)
    || String(option.value) === String(selectedProductId.value)
  ));
});

const printerOptions = computed(() => availablePrinters.value.map((printerName) => ({
  label: printerName,
  value: printerName,
})));
const PREVIEW_PX_PER_MM = 5;

const previewStyle = computed(() => ({
  width: `${previewTemplate.value.label_width_mm * PREVIEW_PX_PER_MM}px`,
  height: `${previewTemplate.value.label_height_mm * PREVIEW_PX_PER_MM}px`,
  minWidth: `${previewTemplate.value.label_width_mm * PREVIEW_PX_PER_MM}px`,
  aspectRatio: `${previewTemplate.value.label_width_mm} / ${previewTemplate.value.label_height_mm}`,
}));

onMounted(() => {
  initializePrinterBridge({ silent: true });
});

onBeforeUnmount(() => {
  stopHorizontalAdjust();
});

function updateBlock(index, field, value) {
  form.settings.blocks = form.settings.blocks.map((block, blockIndex) =>
    blockIndex === index ? { ...block, [field]: value } : { ...block }
  );
}

function updateBlockByKey(key, field, value) {
  const index = form.settings.blocks.findIndex((block) => block.key === key);

  if (index >= 0) {
    updateBlock(index, field, value);
  }
}



function resetTemplate() {
  const nextSettings = withoutCustomTextBlock(createDefaultLabelTemplate());

  nextSettings.blocks = nextSettings.blocks.map((block) => {
    if (["product_name", "price"].includes(block.key)) {
      return {
        ...block,
        size_percent: 800,
      };
    }

    return block;
  });

  form.defaults({
    name: "Etiqueta de producto",
    is_active: true,
    settings: nextSettings,
  });

  form.reset();
  saveStoredLabelTemplateSettings(nextSettings);

  ToastAlert({
    title: "Plantilla restablecida",
  });
}

function saveTemplate() {
  form.settings = withoutCustomTextBlock({
    ...form.settings,
  });

  form
    .transform((data) => ({
      ...data,
      _method: "put",
    }))
    .post(route("printers.labels.update", props.template.id), {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: () => {
        saveStoredLabelTemplateSettings(form.settings);
        ToastAlert({
          title: "Etiqueta actualizada correctamente",
        });
      },
      onError: () => {
        WarningAlert({
          title: "No se pudo guardar",
          message: "Revisa la configuracion de la etiqueta e intenta nuevamente.",
        });
      },
      onFinish: () => {
        form.transform((data) => data);
      },
    });
}

function startDrag(index) {
  dragIndex.value = index;
}

function dropBlock(targetIndex) {
  if (dragIndex.value === null) {
    return;
  }

  form.settings.blocks = reorderLabelBlocks(form.settings.blocks, dragIndex.value, targetIndex);
  dragIndex.value = null;
}

function clampNumber(value, min, max) {
  const numeric = Number(value);

  if (!Number.isFinite(numeric)) {
    return min;
  }

  return Math.max(min, Math.min(max, Math.round(numeric)));
}
function rowStyle(row) {
  const align = textAlignFromPercent(row.position_percent);

  if (row.type === "barcode") {
    return {
      justifyContent: flexAlignFromPercent(row.position_percent),
      height: `${Math.max(20, row.height_mm * 4)}px`,
    };
  }

  const size = Number(row.size_percent || 100);
  const isProductName = row.block_key === "product_name";
  const isPrice = row.block_key === "price";

  let fontSize = Math.round(size * 0.045);

  if (isPrice) {
    fontSize = Math.round(size * 0.055);
  }

  if (!isProductName && !isPrice) {
    fontSize = Math.round(size * 0.035);
  }

  return {
    fontSize: `${Math.max(7, fontSize)}px`,
    textAlign: align,
    lineHeight: isProductName ? "0.9" : "1",
    maxWidth: "100%",
    overflow: "hidden",
    whiteSpace: isProductName ? "normal" : "nowrap",
    overflowWrap: isProductName ? "break-word" : "normal",
  };
}


function textAlignFromPercent(positionPercent) {
  const position = Number(positionPercent || 0);

  if (position <= 25) return "left";
  if (position >= 75) return "right";
  return "center";
}

function flexAlignFromPercent(positionPercent) {
  const position = Number(positionPercent || 0);

  if (position <= 25) return "flex-start";
  if (position >= 75) return "flex-end";
  return "center";
}

function startHorizontalAdjust(event, blockKey) {
  if (event.target?.closest?.("[data-drag-handle='true']")) {
    return;
  }

  activePointerBlockKey.value = blockKey;
  activeHorizontalKey.value = blockKey;
  updatePreviewBlockPosition(event, blockKey);
  window.addEventListener("pointermove", handlePointerMove);
  window.addEventListener("pointerup", stopHorizontalAdjust);
}

function handlePointerMove(event) {
  if (!activePointerBlockKey.value) {
    return;
  }

  updatePreviewBlockPosition(event, activePointerBlockKey.value);
}

function stopHorizontalAdjust() {
  activePointerBlockKey.value = null;
  activeHorizontalKey.value = null;
  window.removeEventListener("pointermove", handlePointerMove);
  window.removeEventListener("pointerup", stopHorizontalAdjust);
}

function updatePreviewBlockPosition(event, blockKey) {
  const container = previewLabel.value;

  if (!container) {
    return;
  }

  const rect = container.getBoundingClientRect();
  const percent = clampNumber(((event.clientX - rect.left) / rect.width) * 100, 0, 100);

  updateBlockByKey(blockKey, "position_percent", percent);
}

async function initializePrinterBridge({ silent = true } = {}) {
  try {
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
      } catch {
        printerName = "";
      }
    }

    selectedPrinterName.value = printerName || printers[0] || "";
    saveStoredPrinterName(selectedPrinterName.value);
    printerBridgeMessage.value = selectedPrinterName.value
      ? `Impresora lista: ${selectedPrinterName.value}`
      : "QZ Tray conectado. Selecciona una impresora.";
  } catch (error) {
    printerBridgeReady.value = false;
    availablePrinters.value = [];
    printerBridgeMessage.value = "QZ Tray no esta conectado o no respondio.";

    if (!silent) {
      WarningAlert({
        title: "No se pudo conectar QZ Tray",
        message: error?.message || "Abre QZ Tray y vuelve a intentar.",
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

async function printTestLabel() {
  printing.value = true;

  try {
    if (!printerBridgeReady.value || !selectedPrinterName.value) {
      await initializePrinterBridge({ silent: false });
    }

    if (!selectedPrinterName.value) {
      throw new Error("No hay una impresora seleccionada.");
    }

    const image = buildLabelPngBase64(previewTemplate.value, selectedProduct.value);
    await printImageLabel(
      selectedPrinterName.value,
      image,
      resolveLabelPrintDimensions(previewTemplate.value),
      printCopies.value
    );

    ToastAlert({
      title: "Etiqueta enviada a la impresora",
    });
  } catch (error) {
    WarningAlert({
      title: "No se pudo imprimir",
      message: error?.message || "QZ Tray no pudo enviar la etiqueta a la impresora seleccionada.",
    });
  } finally {
    printing.value = false;
  }
}
</script>

<template>
  <PageLayout :padded="false">
    <div class="px-4 py-4 2xl:px-6">
      <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 xl:flex-row xl:items-end xl:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
              Impresoras
            </p>
            <h1 class="mt-1 text-xl font-black text-slate-900">
              Etiquetas
            </h1>
            <p class="mt-1 text-[13px] text-slate-500">
              Edita etiquetas de producto con codigo de barras, categoria, nombre, precio y variables.
            </p>
          </div>

          <div class="flex flex-wrap gap-3">
            <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2">
              <span class="text-xs font-bold uppercase text-slate-500">Cantidad</span>
              <input
                v-model.number="printCopies"
                type="number"
                min="1"
                max="100"
                class="w-16 rounded-xl border border-slate-200 px-2 py-1 text-center text-sm font-bold text-slate-900 outline-none focus:border-slate-400"
              >
            </label>

            <button
              type="button"
              class="rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
              @click="resetTemplate"
            >
              Restablecer
            </button>

            <button
              type="button"
              class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-bold text-emerald-700 transition hover:bg-emerald-100 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="printing"
              @click="printTestLabel"
            >
              {{ printing ? "Imprimiendo..." : "Probar impresion" }}
            </button>

            <button
              type="button"
              class="rounded-2xl bg-[#1f1d2b] px-4 py-2.5 text-sm font-bold text-white transition hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="form.processing"
              @click="saveTemplate"
            >
              {{ form.processing ? "Guardando..." : "Guardar" }}
            </button>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-1 items-start gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(360px,0.72fr)]">
          <div
            class="min-w-0 space-y-4 overflow-y-auto pr-1"
            style="max-height: calc(100vh - 2rem);"
          >
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-3">
              <div class="mb-3">
                <h3 class="text-sm font-bold text-slate-900">Producto para imprimir</h3>
                <p class="mt-1 text-xs text-slate-500">
                  La etiqueta usa el nombre, precio y codigo del producto seleccionado.
                </p>
              </div>

              <input
                v-model="productSearch"
                type="search"
                class="mb-2 w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                placeholder="Buscar producto por nombre"
              >

              <select
                v-model="selectedProductId"
                class="w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-slate-400"
              >
                <option value="" disabled>
                  Selecciona un producto
                </option>
                <option
                  v-for="option in filteredProductOptions"
                  :key="option.value"
                  :value="option.value"
                >
                  {{ option.label }}
                </option>
              </select>
              <label class="mt-3 block">
  <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
    Nombre que saldrá en la etiqueta
  </span>

  <input
    v-model="editableProductName"
    type="text"
    maxlength="240"
    class="mt-2 w-full rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-slate-400"
    placeholder="Nombre personalizado para imprimir"
  >

  <p class="mt-1 text-xs text-slate-500">
    Se carga desde el producto seleccionado, pero puedes modificarlo antes de imprimir.
  </p>
</label>
            </div>

            <div class="grid gap-3 lg:grid-cols-2 2xl:grid-cols-4">
              <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Plantilla</span>
                <input
                  v-model="form.name"
                  type="text"
                  class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                  placeholder="Etiqueta de producto"
                >
              </label>

              <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Activa</span>
                <div class="mt-2 flex justify-end">
                  <input
                    v-model="form.is_active"
                    type="checkbox"
                    class="h-5 w-5 rounded border-slate-300 text-emerald-500 focus:ring-emerald-400"
                  >
                </div>
              </label>

              <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Marca</span>
                <input
                  v-model="form.settings.header_text"
                  type="text"
                  class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                >
              </label>

              <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Pie</span>
                <input
                  v-model="form.settings.footer_text"
                  type="text"
                  class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                >
              </label>
            </div>

         

            <div class="grid gap-3 lg:grid-cols-3">
              <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Medida</span>
                <div class="mt-2 rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm font-black text-slate-900">
            {{ previewTemplate.label_width_mm }} x {{ previewTemplate.label_height_mm }} mm
                </div>
              </label>

              <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Alto codigo</span>
                <input
                  v-model.number="form.settings.barcode_height_mm"
                  type="number"
                  min="6"
                  max="28"
                  class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                >
              </label>

              <label class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
                <span class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">Ancho codigo</span>
                <input
                  v-model.number="form.settings.barcode_width_percent"
                  type="number"
                  min="45"
                  max="100"
                  class="mt-2 w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-slate-400"
                >
              </label>
            </div>

            <label class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 p-3">
              <span class="text-sm font-bold text-slate-900">Borde de etiqueta</span>
              <input
                v-model="form.settings.show_border"
                type="checkbox"
                class="h-5 w-5 rounded border-slate-300 text-emerald-500 focus:ring-emerald-400"
              >
            </label>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3">
              <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-bold text-slate-900">Bloques</h3>
                <span class="text-xs text-slate-400">Arrastra para ordenar</span>
              </div>

              <div class="grid gap-3 lg:grid-cols-3">
                <article
              v-for="block in configurableBlocks"
                  :key="block.key"
                  draggable="true"
                  class="rounded-2xl border border-slate-200 bg-white p-3"
                
                  @dragstart="startDrag(block.originalIndex)"
                     @dragover.prevent
@drop="dropBlock(block.originalIndex)"
               
            
                >
                  <div class="space-y-3">
                    <p class="text-sm font-bold text-slate-900">
                      {{ blockCatalog.find((item) => item.key === block.key)?.label || block.key }}
                    </p>

                    <div class="flex items-center justify-between gap-3">
                      <button
                        type="button"
                        class="flex h-9 w-9 items-center justify-center rounded-xl border transition"
                        :class="block.enabled ? 'border-emerald-200 bg-emerald-50 text-emerald-600' : 'border-slate-200 bg-slate-50 text-slate-400'"
                 @click="updateBlock(block.originalIndex, 'enabled', !block.enabled)"
                      >
                        <span class="material-symbols-outlined text-[18px]">visibility</span>
                      </button>

                     <input
  :value="block.size_percent"
  type="number"
  min="50"
  max="2000"
  step="10"
  class="w-24 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-center text-sm text-slate-900 outline-none transition focus:border-slate-400"
@input="updateBlock(block.originalIndex, 'size_percent', clampNumber($event.target.value, 50, 2000))"
>
                    </div>
                  </div>
                </article>
              </div>
            </div>

          </div>

          <aside class="sticky top-4 h-fit self-start">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
              <div class="border-b border-slate-200 pb-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                      Etiqueta en vivo
                    </p>
                    <h2 class="mt-1 text-lg font-black text-slate-900 lg:text-xl">
                      Vista previa
                    </h2>
                    <p class="mt-1 text-xs text-slate-500 lg:text-sm">
                      {{ previewTemplate.label_width_mm }} x {{ previewTemplate.label_height_mm }} mm
                    </p>
                  </div>

                  <button
                    type="button"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50"
                    @click="initializePrinterBridge({ silent: false })"
                  >
                    Conectar
                  </button>
                </div>

                <div class="mt-3 grid gap-2">
                  <select
                    :value="selectedPrinterName"
                    class="w-full rounded-2xl border border-slate-200 bg-white px-3.5 py-2.5 text-sm text-slate-900 outline-none transition focus:border-slate-400 disabled:bg-slate-100"
                    :disabled="!printerOptions.length"
                    @change="handlePrinterChange"
                  >
                    <option value="">
                      Selecciona impresora
                    </option>
                    <option
                      v-for="option in printerOptions"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select>
                  <p class="text-xs text-slate-500">
                    {{ printerBridgeMessage }}
                  </p>
                </div>
              </div>

        <div class="mt-4 overflow-auto rounded-2xl bg-slate-100 p-4">
  <div
    ref="previewLabel"
    class="mx-auto flex shrink-0 flex-col justify-center overflow-hidden bg-white px-1 py-[2px] text-slate-950 shadow-sm"
    :class="previewTemplate.show_border ? 'border border-slate-950' : 'border border-slate-200'"
    :style="previewStyle"
  >
    <article
      v-for="(previewBlock, index) in previewBlocks"
      :key="previewBlock.key"
      :data-preview-block-key="previewBlock.key"
      draggable="true"
      class="relative cursor-move rounded border border-transparent px-0.5 transition"
      :class="[
        activeHorizontalKey === previewBlock.key ? 'bg-slate-50 ring-1 ring-slate-200' : 'hover:border-slate-200 hover:bg-slate-50/70',
        dragIndex === index ? 'border-slate-300 bg-slate-50/80 opacity-70' : '',
      ]"
      @dragstart="startDrag(index)"
      @dragover.prevent
      @drop="dropBlock(index)"
      @pointerdown.prevent="startHorizontalAdjust($event, previewBlock.key)"
    >
      <div
        v-if="previewBlock.row.type === 'barcode'"
        class="flex overflow-hidden"
        :style="rowStyle(previewBlock.row)"
      >
        <div
          class="h-full bg-[repeating-linear-gradient(90deg,#000_0_2px,#fff_2px_3px,#000_3px_4px,#fff_4px_6px)]"
          :style="{ width: `${previewBlock.row.width_percent}%` }"
        />
      </div>

      <p
        v-else
        class="whitespace-pre-line break-words leading-none"
        :class="previewBlock.row.bold ? 'font-black' : 'font-semibold'"
        :style="rowStyle(previewBlock.row)"
      >
        <template v-if="previewBlock.row.type === 'pair'">
          {{ previewBlock.row.label }}: {{ previewBlock.row.value }}
        </template>
        <template v-else>
          {{ previewBlock.row.text }}
        </template>
      </p>
    </article>
  </div>
               </div>
            </div>
          </aside>
        </div>
      </section>
    </div>
  </PageLayout>
</template>
