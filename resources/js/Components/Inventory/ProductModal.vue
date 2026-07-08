<script setup>
import { useForm } from "@inertiajs/vue3";
import { watch, computed, ref, onBeforeUnmount } from "vue";
import GlobalModal from "@/Components/Modales/GlobalModal.vue";
import InputField from "@/Components/Forms/InputField.vue";
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
});

const emit = defineEmits(["close"]);
const form = useForm({
  barcodes: [""],
  branch_ids: [],
  unit: "",
  name: "",
  min_stock: 0,
  category_id: "",
  category_name: "",
  cost: "",
  sale_price: "",
  entry_date: new Date().toISOString().slice(0, 10),
  active: true,
  image: null,
  quantity: null,
  kilos: null,
  grams: null,
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
  const cost = parseDecimal(form.cost);
  const percentage = parseDecimal(marginPercentage.value);

  if (cost === null || percentage === null) return;

  syncingPricing.value = true;
  form.sale_price = formatDecimal(cost * (1 + percentage / 100));
  syncingPricing.value = false;
}

function syncPercentageFromSalePrice() {
  const cost = parseDecimal(form.cost);
  const salePrice = parseDecimal(form.sale_price);

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

function initializePricingFields(costValue, salePriceValue) {
  syncingPricing.value = true;

  const cost = parseDecimal(costValue);
  const salePrice = parseDecimal(salePriceValue);
  const storedMargin = parseDecimal(props.product?.margin_percentage);

  if (storedMargin !== null) {
    marginPercentage.value = formatDecimal(storedMargin);
  } else if (cost !== null && salePrice !== null && cost > 0) {
    marginPercentage.value = formatDecimal(((salePrice - cost) / cost) * 100);
  } else {
    marginPercentage.value = "";
  }

  pricingDriver.value = "sale_price";
  syncingPricing.value = false;
}

function handleMarginPercentageChange(value) {
  pricingDriver.value = "percentage";
  marginPercentage.value = value;
}

function handleSalePriceChange(value) {
  pricingDriver.value = "sale_price";
  form.sale_price = value;
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

    form.unit = product.unit ?? "";
    form.name = product.name ?? "";
    form.branch_ids = product?.branch_ids?.length
      ? product.branch_ids.map(Number)
      : [product.branch_id].filter(Boolean).map(Number);
    ensureCurrentBranchSelected();

    form.min_stock = product.min_stock ?? 0;
    form.category_id = product.category_id ?? "";
    form.category_name = "";
    form.cost = product.cost ?? "";
    form.sale_price = product.price ?? "";
    form.entry_date =
      product.entry_date ?? new Date().toISOString().slice(0, 10);
    form.active = true;
    form.image = product.image ?? null;

    form.quantity = product.quantity ?? null;
    form.kilos = product.kilos ?? null;
    form.liters = product.liters ?? null;
    initializePricingFields(form.cost, form.sale_price);
  },
  { immediate: true }
);

const units = [
  { label: "Pieza", value: "pza" },
  { label: "Caja", value: "cj" },
  { label: "Kilogramo", value: "kg" },
  { label: "Gramo", value: "g" },
  { label: "Litro", value: "l" },
];

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
  const cost = Number(form.cost || 0);
  const salePrice = Number(form.sale_price || 0);

  return salePrice > 0 && cost > 0 && salePrice < cost;
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
  form.unit = "";
  form.name = "";
  form.min_stock = 0;
  form.category_id = "";
  form.category_name = "";
  form.cost = "";
  form.sale_price = "";
  form.entry_date = new Date().toISOString().slice(0, 10);
  form.active = true;
  form.image = null;
  form.quantity = null;
  form.kilos = null;
  form.liters = null;
  initializePricingFields(form.cost, form.sale_price);
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

watch(
  () => [props.mode, props.branch?.id],
  ([mode]) => {
    if (mode !== "create") return;
    setCreateDefaults();
  },
  { immediate: true }
);

watch(
  () => form.cost,
  () => {
    if (syncingPricing.value) return;

    if (pricingDriver.value === "sale_price") {
      syncPercentageFromSalePrice();
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
  () => form.sale_price,
  () => {
    if (syncingPricing.value || pricingDriver.value !== "sale_price") return;
    syncPercentageFromSalePrice();
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
                errors.unit ||
                errors.cost ||
                errors.sale_price ||
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
    <div class="rounded-[28px] border border-slate-200 bg-white p-4 shadow-[0_20px_60px_-40px_rgba(15,23,42,0.35)] md:p-5 xl:p-6">
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
            <h3 class="text-sm font-semibold text-slate-900">Imagen</h3>
            <p class="mt-1 text-xs text-slate-500">Foto opcional del producto.</p>
          </div>

          <button
            type="button"
            class="flex min-h-[286px] w-full items-center justify-center rounded-[24px] border border-dashed bg-slate-50/80 p-4 text-left transition"
            :class="
              mode === 'view'
                ? 'cursor-default border-slate-200'
                : isDragActive
                  ? 'border-slate-900 bg-slate-100'
                  : 'border-slate-300 hover:border-slate-400'
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
                class="h-[248px] w-full rounded-[18px] bg-white object-contain"
              />
            </template>

            <template v-else>
              <div class="flex h-[248px] w-full flex-col items-center justify-center rounded-[18px] bg-white px-4 text-center">
                <span class="material-symbols-outlined text-[42px] text-slate-300">
                  image
                </span>
                <p class="mt-3 text-sm font-semibold text-slate-700">
                  {{ mode === "view" ? "Sin imagen" : "Seleccionar o arrastrar archivo" }}
                </p>
                <p v-if="mode !== 'view'" class="mt-1 text-xs text-slate-500">
                  JPG, PNG o WEBP
                </p>
              </div>
            </template>
          </button>
        </section>

        <section class="space-y-3 xl:hidden">
          <div>
            <h3 class="text-sm font-semibold text-slate-900">Imagen</h3>
            <p class="mt-1 text-xs text-slate-500">Foto opcional del producto.</p>
          </div>

          <button
            type="button"
            class="flex min-h-[180px] w-full items-center justify-center rounded-[22px] border border-dashed bg-slate-50/80 p-4 text-left transition"
            :class="
              mode === 'view'
                ? 'cursor-default border-slate-200'
                : isDragActive
                  ? 'border-slate-900 bg-slate-100'
                  : 'border-slate-300 hover:border-slate-400'
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
                class="h-[148px] w-full rounded-[16px] bg-white object-contain"
              />
            </template>

            <template v-else>
              <div class="flex h-[148px] w-full flex-col items-center justify-center rounded-[16px] bg-white px-4 text-center">
                <span class="material-symbols-outlined text-[36px] text-slate-300">
                  image
                </span>
                <p class="mt-2 text-sm font-semibold text-slate-700">
                  {{ mode === "view" ? "Sin imagen" : "Seleccionar o arrastrar archivo" }}
                </p>
                <p v-if="mode !== 'view'" class="mt-1 text-xs text-slate-500">JPG, PNG o WEBP</p>
              </div>
            </template>
          </button>
        </section>

        <section class="space-y-3">
          <div>
            <h3 class="text-sm font-semibold text-slate-900">Códigos de barras</h3>
            <p class="text-xs text-slate-500">Principal y alternos del producto.</p>
          </div>

          <div class="rounded-[22px] border border-slate-200 bg-slate-50/60 p-3">
            <div class="mb-3 flex items-center justify-between gap-3">
              <p class="text-xs font-medium text-slate-500">
                {{ form.barcodes.length > 1 ? `${form.barcodes.length} códigos capturados` : 'Captura el código principal' }}
              </p>

              <button
                v-if="mode !== 'view'"
                type="button"
                @click="addBarcode"
                class="inline-flex items-center justify-center rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-900 hover:text-slate-900"
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
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-2">
              <h3 class="text-sm font-semibold text-slate-900">Datos básicos</h3>
            </div>

            <div class="pt-0.5 text-right">
              <p class="text-xs text-slate-500">Identificación, categoría, unidad y precios.</p>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-3 md:grid-cols-2 2xl:grid-cols-3">
            <InputField
              label="Nombre"
              field="name"
              v-model="form.name"
              :error="form.errors.name"
              :readonly="mode === 'view'"
              class="md:col-span-2 2xl:col-span-3"
            />

            <template v-if="categoryInputMode === 'select'">
              <div>
                <div class="mb-1 flex items-center justify-between gap-2">
                  <label for="category_id" class="block text-sm font-semibold text-slate-700">
                    Categoría
                  </label>

                  <button
                    v-if="mode !== 'view'"
                    type="button"
                    @click="toggleCategoryInputMode"
                    class="rounded-lg border border-slate-300 px-2.5 py-1 text-[11px] font-semibold text-slate-700 transition hover:border-slate-900 hover:text-slate-900"
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
                  <label for="category_name" class="block text-sm font-semibold text-slate-700">
                    Categoría
                  </label>

                  <button
                    v-if="mode !== 'view'"
                    type="button"
                    @click="toggleCategoryInputMode"
                    class="rounded-lg border border-slate-300 px-2.5 py-1 text-[11px] font-semibold text-slate-700 transition hover:border-slate-900 hover:text-slate-900"
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
              label="Unidad de medida"
              field="unit"
              v-model="form.unit"
              :options="units"
              placeholder="Selecciona unidad"
              :disabled="mode === 'view'"
            />

            <InputField
              label="Stock mínimo"
              field="min_stock"
              v-model="form.min_stock"
              :error="form.errors.min_stock"
              type="number"
              :readonly="mode === 'view'"
            />

            <InputField
              label="Precio compra"
              field="cost"
              v-model="form.cost"
              prefix="$"
              :error="form.errors.cost"
              type="text"
              step="0.01"
              :readonly="mode === 'view'"
            />

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
              label="Precio venta"
              field="sale_price"
              :model-value="form.sale_price"
              @update:modelValue="handleSalePriceChange"
              prefix="$"
              :error="
                invalidPrice
                  ? 'El precio de venta no puede ser menor al precio inicial'
                  : form.errors.sale_price
              "
              type="text"
              step="0.01"
              :readonly="mode === 'view'"
            />
          </div>
        </section>

        <section class="space-y-3 xl:col-span-3">
          <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-4">
            <div>
              <h3 class="text-sm font-semibold text-slate-900">
                Sucursales donde se agregará
              </h3>
              <p class="text-xs text-slate-500">Selecciona dónde estará disponible este producto.</p>
            </div>

            <button
              v-if="mode !== 'view'"
              type="button"
              class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-900 hover:text-slate-900"
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

          <div class="overflow-y-auto rounded-[20px] border border-slate-200 bg-slate-50/60 p-3 xl:max-h-[176px]">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
              <label
                v-for="branchItem in branchesDB"
                :key="branchItem.id"
                class="flex items-center gap-3 rounded-2xl border px-3 py-2.5 transition"
                :class="
                  isCurrentBranch(branchItem.id)
                    ? 'cursor-not-allowed border-blue-200 bg-blue-50'
                    : form.branch_ids.includes(branchItem.id)
                      ? 'cursor-pointer border-slate-900 bg-slate-900 text-white'
                      : 'cursor-pointer border-slate-200 bg-white hover:border-slate-300'
                "
              >
                <input
                  type="checkbox"
                  :value="branchItem.id"
                  v-model="form.branch_ids"
                  :disabled="mode === 'view' || isCurrentBranch(branchItem.id)"
                  class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-400 disabled:opacity-70"
                />

                <span
                  class="min-w-0 flex-1 truncate text-sm font-medium"
                  :class="
                    isCurrentBranch(branchItem.id)
                      ? 'text-blue-900'
                      : form.branch_ids.includes(branchItem.id)
                        ? 'text-white'
                        : 'text-slate-800'
                  "
                >
                  {{ branchItem.name }}
                </span>

                <span
                  v-if="isCurrentBranch(branchItem.id)"
                  class="rounded-full bg-white/80 px-2 py-0.5 text-[11px] font-semibold text-blue-700"
                >
                  Fija
                </span>
              </label>
            </div>
          </div>

          <p v-if="form.errors.branch_ids" class="text-xs text-red-500">
            {{ form.errors.branch_ids }}
          </p>
        </section>
      </div>
    </div>
  </GlobalModal>
</template>
