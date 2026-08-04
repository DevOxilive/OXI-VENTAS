<script setup>
import { useForm } from "@inertiajs/vue3";
import { watch, computed, ref, onBeforeUnmount } from "vue";
import GlobalModal from "@/Components/Modales/GlobalModal.vue";
import InputField from "@/Components/Forms/InputField.vue";
import SelectionCheckboxCard from "@/Components/Forms/SelectionCheckboxCard.vue";
import SelectField from "@/Components/Forms/SelectField.vue";
import ActionIconButton from "@/Components/Forms/ActionIconButton.vue";
import { getProductModalConfig } from "@/config/ModalConfigs/productModalConfig";
import {
  ToastAlert,
  ErrorAlert,
} from "@/Components/Modales/UniversalActionModal";

const props = defineProps({
  mode: String,
  product: Object,
  branch: Object,

  categoriesDB: {
    type: Array,
    default: () => [],
  },
  branchesDB: {
    type: Array,
    default: () => [],
  },
  canManagePricing: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["close"]);
const form = useForm({
  barcodes: [""],
  branch_ids: [],
  inventory_unit: "pza",
  has_box_presentation: false,
  pieces_per_box: "",
  name: "",
  min_stock: 0,
  category_id: "",
  category_name: "",
  cost_per_piece: "",
  sale_price_per_piece: "",
  cost_per_box: "",
  sale_price_per_box: "",
  allow_low_margin: false,
  entry_date: new Date().toISOString().slice(0, 10),
  active: true,
  image: null,
  quantity: null,
  kilos: null,
  grams: null,
  record_version: "",
});
const categoryInputMode = ref("select");
const marginPercentage = ref("");
const pricingDriver = ref("percentage");
const syncingPricing = ref(false);
const fileInput = ref(null);
const isDragActive = ref(false);
const filePreviewUrl = ref(null);

function parseDecimal(value) {
  if (value === null || value === undefined || value === "") return null;

  const parsed = Number(value);

  return Number.isFinite(parsed) ? parsed : null;
}

function formatDecimal(value) {
  if (!Number.isFinite(value)) return "";

  const rounded = Math.round(value * 100) / 100;

  return rounded.toFixed(2).replace(/\.?0+$/, "");
}

function syncSalePriceFromPercentage() {
  const cost = parseDecimal(form.cost_per_piece);
  const percentage = parseDecimal(marginPercentage.value);

  if (cost === null || percentage === null) return;

  syncingPricing.value = true;
  form.sale_price_per_piece = formatDecimal(cost * (1 + percentage / 100));

  if (hasBoxPresentation.value) {
    const boxCost = parseDecimal(form.cost_per_box);

    form.sale_price_per_box = boxCost === null
      ? ""
      : formatDecimal(boxCost * (1 + percentage / 100));
  }

  syncingPricing.value = false;
}

function syncPercentageFromSalePrice(costValue, salePriceValue) {
  const cost = parseDecimal(costValue);
  const salePrice = parseDecimal(salePriceValue);

  if (cost === null || salePrice === null || cost <= 0) {
    syncingPricing.value = true;
    marginPercentage.value = "";
    syncingPricing.value = false;
    return;
  }

  syncingPricing.value = true;
  marginPercentage.value = formatDecimal(((salePrice - cost) / cost) * 100);
  syncingPricing.value = false;
}

function initializePricingFields() {
  syncingPricing.value = true;

  const cost = parseDecimal(form.cost_per_piece);
  const salePrice = parseDecimal(form.sale_price_per_piece);
  const storedMargin = parseDecimal(props.product?.margin_percentage);

  if (storedMargin !== null) {
    marginPercentage.value = formatDecimal(storedMargin);
  } else if (cost !== null && salePrice !== null && cost > 0) {
    marginPercentage.value = formatDecimal(((salePrice - cost) / cost) * 100);
  } else {
    marginPercentage.value = "";
  }

  pricingDriver.value = "percentage";
  syncingPricing.value = false;
}

function handleMarginPercentageChange(value) {
  pricingDriver.value = "percentage";
  marginPercentage.value = value;
}

function handleSalePriceChange(value) {
  pricingDriver.value = "piece_price";
  form.sale_price_per_piece = value;
}

function handleBoxSalePriceChange(value) {
  pricingDriver.value = "box_price";
  form.sale_price_per_box = value;
}

watch(
  () => props.product,
  (product) => {
    form.reset();
    categoryInputMode.value = "select";

    if (!product) return;

    form.barcodes = product.barcodes?.length
      ? product.barcodes
      : [product.barcode ?? ""];

    form.inventory_unit = product.inventory_unit ?? (product.unit === "kg" ? "kg" : "pza");
    form.has_box_presentation = Boolean(product.has_box_presentation ?? product.unit === "cj");
    form.pieces_per_box = product.pieces_per_box ?? "";
    form.name = product.name ?? "";
    form.branch_ids = product?.branch_ids?.length
      ? product.branch_ids.map(Number)
      : [product.branch_id].filter(Boolean).map(Number);
    ensureCurrentBranchSelected();

    form.min_stock = product.min_stock ?? 0;
    form.category_id = product.category_id ?? "";
    form.category_name = "";
    form.cost_per_piece = product.cost_per_piece ?? product.cost ?? "";
    form.sale_price_per_piece = product.sale_price_per_piece ?? product.price ?? "";
    form.cost_per_box = product.cost_per_box ?? "";
    form.sale_price_per_box = product.sale_price_per_box ?? "";
    form.entry_date =
      product.entry_date ?? new Date().toISOString().slice(0, 10);
    form.active = true;
    form.image = product.image ?? null;
    form.record_version = product.record_version ?? "";

    form.quantity = product.quantity ?? null;
    form.kilos = product.kilos ?? null;
    form.liters = product.liters ?? null;
    initializePricingFields();
  },
  { immediate: true }
);

const units = [
  { label: "Pieza", value: "pza" },
  { label: "Kilogramo", value: "kg" },
];

const isKilogramUnit = computed(() => form.inventory_unit === "kg");
const hasBoxPresentation = computed(() => Boolean(form.has_box_presentation));
const marginBelowMinimum = computed(() => {
  const margin = parseDecimal(marginPercentage.value);

  return margin !== null && margin < 10;
});

const modalConfig = computed(() =>
  getProductModalConfig({
    mode: props.mode,
    totalErrors: Object.keys(form.errors || {}).length,
    processing: form.processing,
  })
);

function toggleCategoryInputMode() {
  if (categoryInputMode.value === "select") {
    categoryInputMode.value = "text";
    form.category_id = "";
    form.clearErrors("category_id");
    return;
  }

  categoryInputMode.value = "select";
  form.category_name = "";
  form.clearErrors("category_name");
}
const imagePreview = computed(() => {
  if (filePreviewUrl.value) return filePreviewUrl.value;
  if (!form.image || form.image instanceof File) return null;

  if (
    form.image.startsWith("blob:") ||
    form.image.startsWith("data:") ||
    form.image.startsWith("http://") ||
    form.image.startsWith("https://") ||
    form.image.startsWith("/")
  ) {
    return form.image;
  }

  return `/storage/${form.image.replace(/^\/+/, "")}`;
});

function revokeFilePreview() {
  if (!filePreviewUrl.value) return;

  URL.revokeObjectURL(filePreviewUrl.value);
  filePreviewUrl.value = null;
}

function assignImageFile(file) {
  if (!file) return;

  if (!file.type.startsWith("image/")) {
    ErrorAlert({
      title: "Archivo no válido",
      message: "Solo se permiten imágenes JPG, PNG o WEBP.",
    });
    return;
  }

  revokeFilePreview();
  filePreviewUrl.value = URL.createObjectURL(file);
  form.image = file;
  form.clearErrors("image");
}

function openFilePicker() {
  if (props.mode === "view") return;
  fileInput.value?.click();
}

function handleFileChange(event) {
  const [file] = event.target.files || [];
  assignImageFile(file);
  event.target.value = "";
}

function handleDragOver() {
  if (props.mode === "view") return;
  isDragActive.value = true;
}

function handleDragLeave() {
  isDragActive.value = false;
}

function handleDrop(event) {
  if (props.mode === "view") return;
  isDragActive.value = false;
  const [file] = event.dataTransfer?.files || [];
  assignImageFile(file);
}

const invalidPrice = computed(() => {
  const pieceCost = Number(form.cost_per_piece || 0);
  const piecePrice = Number(form.sale_price_per_piece || 0);
  const boxCost = Number(form.cost_per_box || 0);
  const boxPrice = Number(form.sale_price_per_box || 0);

  return (piecePrice > 0 && pieceCost > 0 && piecePrice < pieceCost)
    || (hasBoxPresentation.value && boxPrice > 0 && boxCost > 0 && boxPrice < boxCost);
});
function addBarcode() {
  form.barcodes.push("");
}
function isCurrentBranch(branchId) {
  return Number(branchId) === Number(props.branch?.id);
}

function removeBarcode(index) {
  if (form.barcodes.length === 1) return;
  form.barcodes.splice(index, 1);
}

function setCreateDefaults() {
  form.barcodes = [""];
  form.branch_ids = [];
  ensureCurrentBranchSelected();
  categoryInputMode.value = "select";
  form.inventory_unit = "pza";
  form.has_box_presentation = false;
  form.pieces_per_box = "";
  form.name = "";
  form.min_stock = 0;
  form.category_id = "";
  form.category_name = "";
  form.cost_per_piece = "";
  form.sale_price_per_piece = "";
  form.cost_per_box = "";
  form.sale_price_per_box = "";
  form.allow_low_margin = false;
  form.entry_date = new Date().toISOString().slice(0, 10);
  form.active = true;
  form.image = null;
  form.quantity = null;
  form.kilos = null;
  form.liters = null;
  initializePricingFields();
}

function ensureCurrentBranchSelected() {
  const currentBranchId = props.branch?.id;

  if (!currentBranchId) return;

  const exists = form.branch_ids.some(
    (branchId) => Number(branchId) === Number(currentBranchId)
  );

  if (!exists) {
    form.branch_ids.push(currentBranchId);
  }
}

function toggleBranchSelection(branchId) {
  if (props.mode === "view" || isCurrentBranch(branchId)) return;

  if (form.branch_ids.includes(branchId)) {
    form.branch_ids = form.branch_ids.filter((id) => id !== branchId);
    return;
  }

  form.branch_ids = [...form.branch_ids, branchId];
}

watch(
  () => [props.mode, props.branch?.id],
  ([mode]) => {
    if (mode !== "create") return;
    setCreateDefaults();
  },
  { immediate: true }
);

watch(
  () => [form.cost_per_piece, form.cost_per_box, form.has_box_presentation],
  () => {
    if (syncingPricing.value) return;

    if (pricingDriver.value === "piece_price") {
      syncPercentageFromSalePrice(form.cost_per_piece, form.sale_price_per_piece);
      syncSalePriceFromPercentage();
      return;
    }

    if (pricingDriver.value === "box_price") {
      syncPercentageFromSalePrice(form.cost_per_box, form.sale_price_per_box);
      syncSalePriceFromPercentage();
      return;
    }

    syncSalePriceFromPercentage();
  }
);

watch(marginPercentage, () => {
  if (syncingPricing.value || pricingDriver.value !== "percentage") return;
  syncSalePriceFromPercentage();
});

watch(
  () => form.sale_price_per_piece,
  () => {
    if (syncingPricing.value || pricingDriver.value !== "piece_price") return;
    syncPercentageFromSalePrice(form.cost_per_piece, form.sale_price_per_piece);
    syncSalePriceFromPercentage();
  }
);

watch(
  () => form.sale_price_per_box,
  () => {
    if (syncingPricing.value || pricingDriver.value !== "box_price") return;
    syncPercentageFromSalePrice(form.cost_per_box, form.sale_price_per_box);
    syncSalePriceFromPercentage();
  }
);

watch(
  () => form.inventory_unit,
  (unit) => {
    if (unit === "kg") {
      form.has_box_presentation = false;
      form.pieces_per_box = "";
      form.cost_per_box = "";
      form.sale_price_per_box = "";
    }
  }
);

watch(
  () => form.image,
  (value) => {
    if (!(value instanceof File)) {
      revokeFilePreview();
    }
  }
);

onBeforeUnmount(() => {
  revokeFilePreview();
});

function submit() {
  const branchSlug = props.branch?.slug;

  if (!branchSlug) {
    console.error("No llegó branch.slug al modal:", props.branch);
    return;
  }

  if (invalidPrice.value) {
    ErrorAlert({
      title: "Precio inválido",
      message: "El precio de venta no puede ser menor al precio inicial.",
    });

    return;
  }

  if (marginBelowMinimum.value && !form.allow_low_margin) {
    ErrorAlert({
      title: "Porcentaje de ganancia menor al permitido",
      message: "El porcentaje mínimo es 10%. Usa el botón de autorización para continuar con un porcentaje menor.",
    });

    return;
  }
  ensureCurrentBranchSelected();
  if (categoryInputMode.value === "text") {
    form.category_id = "";
  } else {
    form.category_name = "";
  }
  if (props.mode === "create") {
    form.post(
      route("inventory.branches.products.store", {
        branch: branchSlug,
      }),
      {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
          ToastAlert({
            title: "Producto creado correctamente",
          });

          emit("close");
        },
        onError: () => {
          const barcodeError = form.errors["barcodes.0"];

          if (barcodeError) {
            ErrorAlert({
              title: "Código ya registrado",
              message: `
        <div style="text-align:left;line-height:1.7;">
            ${barcodeError}
        </div>
    `,
            });

            form.clearErrors("barcodes.0");
          }
        },
      }
    );

    return;
  }

  if (props.mode === "edit") {
    form
      .transform((data) => ({
        ...data,
        image: data.image instanceof File ? data.image : null,
        _method: "PUT",
      }))
      .post(
        route("inventory.branches.products.update", {
          branch: props.product.branch_slug ?? branchSlug,
          product: props.product.id,
        }),
        {
          forceFormData: true,
          preserveScroll: true,
          onSuccess: () => {
            ToastAlert({
              title: "Producto actualizado correctamente",
            });

            emit("close");
          },
          onError: (errors) => {
            const barcodeError =
              errors["barcodes.0"] ||
              errors.barcodes ||
              form.errors["barcodes.0"];

            if (barcodeError) {
              ErrorAlert({
                title: "Código ya registrado",
                message: barcodeError,
              }).then(() => {
                form.clearErrors("barcodes.0");
                form.clearErrors("barcodes");
              });

              return;
            }

            ErrorAlert({
              title: "Error al crear producto",
              message:
                errors.name ||
                errors.category_id ||
                errors.category_name ||
                errors.inventory_unit ||
                errors.cost_per_piece ||
                errors.cost_per_box ||
                errors.sale_price_per_piece ||
                errors.sale_price_per_box ||
                errors.branch_ids ||
                "Revisa los datos capturados",
            });
          },
        }
      );
  }
}
</script>

<template>
  <GlobalModal
    v-bind="modalConfig"
    @save="submit"
    @close="$emit('close')"
  >
    <div class="rounded-[28px] border border-secondary bg-background p-4 shadow-[0_20px_60px_-40px_rgba(15,23,42,0.18)] md:p-5 xl:p-6">
      <input
        ref="fileInput"
        type="file"
        accept="image/*"
        class="hidden"
        @change="handleFileChange"
      />

      <div class="grid grid-cols-1 gap-4 xl:grid-cols-[210px_340px_minmax(0,1fr)]">
        <section class="hidden xl:block">
          <div class="mb-3">
            <h3 class="text-sm font-semibold text-text">Imagen</h3>
          </div>

          <button
            type="button"
            class="flex min-h-[286px] w-full items-center justify-center rounded-[24px] border border-dashed bg-secondary p-4 text-left transition"
            :class="
              mode === 'view'
                ? 'cursor-default border-secondary'
                : isDragActive
                  ? 'border-primary bg-background'
                  : 'border-secondary hover:border-primary'
            "
            :disabled="mode === 'view'"
            @click="openFilePicker"
            @dragover.prevent="handleDragOver"
            @dragleave.prevent="handleDragLeave"
            @drop.prevent="handleDrop"
          >
            <template v-if="imagePreview">
              <img
                :src="imagePreview"
                class="h-[248px] w-full rounded-[18px] bg-background object-contain"
              />
            </template>

            <template v-else>
              <div class="flex h-[248px] w-full flex-col items-center justify-center rounded-[18px] bg-background px-4 text-center">
                <span class="material-symbols-outlined text-[42px] text-text opacity-30">
                  image
                </span>
                <p class="mt-3 text-sm font-semibold text-text">
                  {{ mode === "view" ? "Sin imagen" : "Seleccionar o arrastrar archivo" }}
                </p>
                <p v-if="mode !== 'view'" class="mt-1 text-xs text-text opacity-70">
                  JPG, PNG o WEBP
                </p>
              </div>
            </template>
          </button>
        </section>

        <section class="space-y-3 xl:hidden">
          <div>
            <h3 class="text-sm font-semibold text-text">Imagen</h3>
          </div>

          <button
            type="button"
            class="flex min-h-[180px] w-full items-center justify-center rounded-[22px] border border-dashed bg-secondary p-4 text-left transition"
            :class="
              mode === 'view'
                ? 'cursor-default border-secondary'
                : isDragActive
                  ? 'border-primary bg-background'
                  : 'border-secondary hover:border-primary'
            "
            :disabled="mode === 'view'"
            @click="openFilePicker"
            @dragover.prevent="handleDragOver"
            @dragleave.prevent="handleDragLeave"
            @drop.prevent="handleDrop"
          >
            <template v-if="imagePreview">
              <img
                :src="imagePreview"
                class="h-[148px] w-full rounded-[16px] bg-background object-contain"
              />
            </template>

            <template v-else>
              <div class="flex h-[148px] w-full flex-col items-center justify-center rounded-[16px] bg-background px-4 text-center">
                <span class="material-symbols-outlined text-[36px] text-text opacity-30">
                  image
                </span>
                <p class="mt-2 text-sm font-semibold text-text">
                  {{ mode === "view" ? "Sin imagen" : "Seleccionar o arrastrar archivo" }}
                </p>
                <p v-if="mode !== 'view'" class="mt-1 text-xs text-text opacity-70">JPG, PNG o WEBP</p>
              </div>
            </template>
          </button>
        </section>

        <section class="space-y-3">
          <div>
            <h3 class="text-sm font-semibold text-text">Códigos de barras</h3>
          </div>

          <div class="rounded-[22px] border border-secondary bg-secondary p-3">
            <div class="mb-3 flex items-center justify-between gap-3">
              <p class="text-xs font-medium text-text opacity-70">
                {{ form.barcodes.length > 1 ? `${form.barcodes.length} códigos capturados` : 'Captura el código principal' }}
              </p>

              <button
                v-if="mode !== 'view'"
                type="button"
                @click="addBarcode"
                class="inline-flex items-center justify-center rounded-xl border border-secondary bg-background px-3 py-2 text-xs font-semibold text-text transition hover:border-primary hover:text-primary"
              >
                Agregar código
              </button>
            </div>

            <div class="max-h-[250px] space-y-2 overflow-y-auto pr-1">
              <div
                v-for="(barcode, index) in form.barcodes"
                :key="index"
                class="flex items-start gap-2"
              >
                <div class="flex-1">
                  <InputField
                    :label="index === 0 ? 'Código principal' : `Alterno ${index}`"
                    field="barcode"
                    v-model="form.barcodes[index]"
                    icon="barcode_scanner"
                    :error="null"
                    :readonly="mode === 'view'"
                  />
                </div>

                <ActionIconButton
                  v-if="form.barcodes.length > 1 && mode !== 'view'"
                  class="mt-7 shrink-0"
                  icon="delete"
                  title="Eliminar código"
                  variant="red"
                  @click="removeBarcode(index)"
                />
              </div>
            </div>
          </div>
        </section>

        <section class="space-y-4">
          <div>
            <h3 class="text-sm font-semibold text-text">Datos básicos</h3>
          </div>

          <div class="grid grid-cols-1 gap-3 md:grid-cols-2 2xl:grid-cols-3">
            <InputField
              label="Nombre"
              field="name"
              validation-field="product_name"
              v-model="form.name"
              :error="form.errors.name"
              :preserve-case="true"
              :readonly="mode === 'view'"
              class="md:col-span-2 2xl:col-span-3"
            />

            <template v-if="categoryInputMode === 'select'">
              <div>
                <div class="mb-1 flex items-center justify-between gap-2">
                  <label for="category_id" class="block text-sm font-semibold text-text">
                    Categoría
                  </label>

                  <button
                    v-if="mode !== 'view'"
                    type="button"
                    @click="toggleCategoryInputMode"
                    class="rounded-lg border border-secondary bg-background px-2.5 py-1 text-[11px] font-semibold text-text transition hover:border-primary hover:text-primary"
                  >
                    + Nueva categoría
                  </button>
                </div>

                <SelectField
                  label="Categoría"
                  field="category_id"
                  v-model="form.category_id"
                  :options="categoriesDB"
                  placeholder="Selecciona una categoría"
                  :disabled="mode === 'view'"
                  :error="form.errors.category_id"
                  :hide-label="true"
                />
              </div>
            </template>

            <template v-else>
              <div>
                <div class="mb-1 flex items-center justify-between gap-2">
                  <label for="category_name" class="block text-sm font-semibold text-text">
                    Categoría
                  </label>

                  <button
                    v-if="mode !== 'view'"
                    type="button"
                    @click="toggleCategoryInputMode"
                    class="rounded-lg border border-secondary bg-background px-2.5 py-1 text-[11px] font-semibold text-text transition hover:border-primary hover:text-primary"
                  >
                    Usar existente
                  </button>
                </div>

                <InputField
                  label="Categoría"
                  field="category_name"
                  v-model="form.category_name"
                  placeholder="Escribe la categoría"
                  :error="form.errors.category_name"
                  :readonly="mode === 'view'"
                  :hide-label="true"
                />
              </div>
            </template>

            <SelectField
              label="Unidad base de inventario"
              field="inventory_unit"
              v-model="form.inventory_unit"
              :options="units"
              placeholder="Selecciona unidad base"
              :disabled="mode === 'view'"
            />

            <label
              v-if="!isKilogramUnit"
              class="flex min-h-[72px] items-center gap-3 rounded-xl border border-secondary bg-secondary px-4 py-3"
            >
              <input
                v-model="form.has_box_presentation"
                type="checkbox"
                class="h-5 w-5 accent-primary"
                :disabled="mode === 'view'"
              />
              <span>
                <span class="block text-sm font-semibold text-text">Presentación por caja</span>
                <span class="block text-xs text-text opacity-70">Permite capturar y vender por caja sin cambiar el inventario base.</span>
              </span>
            </label>

            <InputField
              v-if="hasBoxPresentation"
              label="Piezas por caja"
              field="pieces_per_box"
              validation-field="quantity"
              v-model="form.pieces_per_box"
              :error="form.errors.pieces_per_box"
              type="text"
              inputmode="numeric"
              placeholder="Ej. 12"
              :readonly="mode === 'view'"
            />

            <InputField
              label="Stock mínimo"
              field="min_stock"
              :validation-field="isKilogramUnit ? 'kilogram_quantity' : undefined"
              v-model="form.min_stock"
              :error="form.errors.min_stock"
              type="text"
              :inputmode="isKilogramUnit ? 'decimal' : 'numeric'"
              :readonly="mode === 'view'"
            />

            <InputField
              :label="isKilogramUnit ? 'Precio compra por kilogramo' : 'Precio compra por pieza'"
              field="cost_per_piece"
              v-model="form.cost_per_piece"
              prefix="$"
              :error="form.errors.cost_per_piece"
              type="text"
              step="0.01"
              :readonly="mode === 'view'"
            />

            <InputField
              v-if="hasBoxPresentation"
              label="Precio compra por caja"
              field="cost_per_box"
              v-model="form.cost_per_box"
              prefix="$"
              :error="form.errors.cost_per_box"
              type="text"
              step="0.01"
              :readonly="mode === 'view'"
            />

            <template v-if="canManagePricing">
              <InputField
                label="Porcentaje"
                field="margin_percentage"
                :model-value="marginPercentage"
                @update:modelValue="handleMarginPercentageChange"
                suffix="%"
                type="text"
                step="0.01"
                :readonly="mode === 'view'"
              />

              <InputField
                :label="isKilogramUnit ? 'Precio venta por kilogramo' : 'Precio venta por pieza'"
                field="sale_price_per_piece"
                :model-value="form.sale_price_per_piece"
                @update:modelValue="handleSalePriceChange"
                prefix="$"
                :error="
                  invalidPrice
                    ? 'El precio de venta no puede ser menor al precio de compra'
                    : form.errors.sale_price_per_piece
                "
                type="text"
                step="0.01"
                :readonly="mode === 'view'"
              />

              <template v-if="hasBoxPresentation">
                <InputField
                  label="Precio venta por caja"
                  field="sale_price_per_box"
                  :model-value="form.sale_price_per_box"
                  @update:modelValue="handleBoxSalePriceChange"
                  prefix="$"
                  :error="invalidPrice ? 'El precio de venta no puede ser menor al precio de compra' : form.errors.sale_price_per_box"
                  type="text"
                  step="0.01"
                  :readonly="mode === 'view'"
                />
              </template>

              <div
                v-if="marginBelowMinimum && mode !== 'view'"
                class="rounded-xl border border-primary bg-secondary p-3 md:col-span-2 2xl:col-span-3"
              >
                <p class="text-sm font-semibold text-primary">
                  El porcentaje de ganancia es menor al 10%.
                </p>
                <p class="mt-1 text-xs text-text opacity-75">
                  Esta excepción debe autorizarse antes de guardar el producto.
                </p>
                <button
                  type="button"
                  class="mt-3 rounded-lg border px-3 py-2 text-xs font-semibold transition"
                  :class="form.allow_low_margin
                    ? 'border-accent bg-accent text-white'
                    : 'border-primary bg-background text-primary hover:bg-secondary'"
                  @click="form.allow_low_margin = !form.allow_low_margin"
                >
                  {{ form.allow_low_margin ? 'Excepción autorizada' : 'Autorizar porcentaje menor al 10%' }}
                </button>
              </div>
            </template>
          </div>
        </section>

        <section class="space-y-3 xl:col-span-3">
          <div class="flex items-center justify-between gap-3 border-t border-secondary pt-4">
            <div>
              <h3 class="text-sm font-semibold text-text">
                Sucursales donde se agregará
              </h3>
              <p class="text-xs text-text opacity-70">Selecciona dónde estará disponible este producto.</p>
            </div>

            <button
              v-if="mode !== 'view'"
              type="button"
              class="inline-flex items-center justify-center rounded-xl border border-secondary bg-background px-4 py-2 text-sm font-semibold text-text transition hover:border-primary hover:text-primary"
              @click="
                form.branch_ids.length === branchesDB.length
                  ? (form.branch_ids = [props.branch?.id].filter(Boolean))
                  : (form.branch_ids = branchesDB.map((branch) => branch.id))
              "
            >
              {{
                form.branch_ids.length === branchesDB.length
                  ? 'Quitar todas'
                  : 'Seleccionar todas'
              }}
            </button>
          </div>

          <div class="overflow-y-auto rounded-[20px] border border-secondary bg-secondary p-3 xl:max-h-[176px]">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
              <SelectionCheckboxCard
                v-for="branchItem in branchesDB"
                :key="branchItem.id"
                compact
                variant="solid"
                :checked="form.branch_ids.includes(branchItem.id)"
                :disabled="mode === 'view' || isCurrentBranch(branchItem.id)"
                :highlighted="isCurrentBranch(branchItem.id)"
                :title="branchItem.name"
                :description="
                  isCurrentBranch(branchItem.id)
                    ? 'Sucursal actual'
                    : form.branch_ids.includes(branchItem.id)
                      ? 'Disponible para este producto'
                      : 'Haz clic para agregar'
                "
                :badge="isCurrentBranch(branchItem.id) ? 'Fija' : ''"
                @toggle="toggleBranchSelection(branchItem.id)"
              />
            </div>
          </div>

          <p v-if="form.errors.branch_ids" class="text-xs text-primary">
            {{ form.errors.branch_ids }}
          </p>
        </section>
      </div>
    </div>
  </GlobalModal>
</template>
