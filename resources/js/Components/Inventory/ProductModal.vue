<script setup>
import { useForm } from "@inertiajs/vue3";
import { watch, computed, ref } from "vue";
import GlobalModal from "@/Components/Modales/GlobalModal.vue";
import InputField from "@/Components/Forms/InputField.vue";
import SelectField from "@/Components/Forms/SelectField.vue";
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
  if (!form.image) return null;

  if (form.image instanceof File) {
    return URL.createObjectURL(form.image);
  }

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
    <div>
      <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <!-- CÓDIGOS -->
        <div>
          <label class="block text-sm font-semibold text-slate-600 mb-2">
            Códigos de barras
          </label>

          <div class="space-y-3 max-h-[145px] overflow-y-auto pr-1">
            <div
              v-for="(barcode, index) in form.barcodes"
              :key="index"
              class="flex items-start gap-2"
            >
              <div class="flex-1">
                <InputField
                  label=""
                  field="barcode"
                  v-model="form.barcodes[index]"
                  icon="barcode_scanner"
                  :error="null"
                  :readonly="mode === 'view'"
                />
              </div>

              <button
                v-if="form.barcodes.length > 1 && mode !== 'view'"
                type="button"
                @click="removeBarcode(index)"
                class="mt-[2px] h-[42px] min-w-[42px] rounded-xl border border-slate-300 bg-white text-black hover:bg-slate-100 transition"
              >
                -
              </button>
            </div>
          </div>

          <button
            v-if="mode !== 'view'"
            type="button"
            @click="addBarcode"
            class="mt-3 w-full h-11 rounded-xl border-2 border-dashed border-slate-300 bg-white text-slate-600 font-medium hover:border-black hover:text-black transition"
          >
            Agregar código alterno
          </button>
        </div>

        <!-- IMAGEN -->
        <div>
          <label class="block text-sm font-semibold text-slate-600 mb-2">
            Imagen del producto
          </label>

          <div
            class="border-2 border-dashed border-slate-300 rounded-2xl px-6 py-4 flex items-center gap-5 bg-slate-50"
          >
            <template v-if="imagePreview">
              <img
                :src="imagePreview"
                :class="
                  mode === 'view'
                    ? 'w-40 h-40 object-contain rounded-2xl border bg-white'
                    : 'w-28 h-28 object-contain rounded-2xl shadow border bg-white'
                "
              />
            </template>

            <template v-else>
              <div
                class="w-28 h-28 rounded-2xl bg-white border flex items-center justify-center"
              >
                <span class="material-symbols-outlined text-4xl text-slate-400">
                  image
                </span>
              </div>
            </template>
            <div v-if="mode !== 'view'" class="flex-1">
              <p class="text-sm font-semibold text-slate-900">
                Imagen del producto
              </p>

              <p class="text-xs text-slate-500 mt-1">JPG, PNG o WEBP</p>

              <template v-if="mode !== 'view'">
                <input
                  type="file"
                  accept="image/*"
                  class="mt-3 text-sm"
                  @change="form.image = $event.target.files[0]"
                />
              </template>

              <template v-else>
                <p class="mt-3 text-xs text-green-600 font-medium">
                  âœ“ Imagen cargada
                </p>
              </template>
            </div>
          </div>
        </div>
        <!-- SUCURSALES -->
        <div v-if="mode !== 'view'" class="md:col-span-2">
          <div class="mb-3 flex flex-col gap-3 rounded-[28px] border border-slate-200 bg-gradient-to-br from-slate-50 via-white to-blue-50/70 p-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <label class="block text-sm font-semibold text-slate-700">
              Sucursales donde se agregará
              </label>
              <p class="mt-1 text-xs text-slate-500">
                La sucursal actual queda seleccionada y protegida automáticamente.
              </p>
            </div>

            <button
              type="button"
              class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:-translate-y-[1px] hover:border-slate-300 hover:bg-slate-100"
              @click="
                form.branch_ids.length === branchesDB.length
                  ? (form.branch_ids = [props.branch?.id].filter(Boolean))
                  : (form.branch_ids = branchesDB.map((branch) => branch.id))
              "
            >
              {{
                form.branch_ids.length === branchesDB.length
                  ? "Quitar todas"
                  : "Seleccionar todas"
              }}
            </button>
          </div>

          <div
            class="max-h-[220px] overflow-y-auto rounded-[28px] border border-slate-200 bg-white p-3 shadow-inner shadow-slate-100"
          >
            <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
              <label
                v-for="branchItem in branchesDB"
                :key="branchItem.id"
                class="group relative flex items-center gap-3 overflow-hidden rounded-2xl border px-4 py-3 transition"
                :class="
                  isCurrentBranch(branchItem.id)
                    ? 'cursor-not-allowed border-blue-200 bg-blue-50 shadow-sm shadow-blue-100/80'
                    : form.branch_ids.includes(branchItem.id)
                      ? 'cursor-pointer border-slate-900 bg-slate-900 text-white shadow-lg shadow-slate-300/50'
                      : 'cursor-pointer border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                "
              >
                <input
                  type="checkbox"
                  :value="branchItem.id"
                  v-model="form.branch_ids"
                  :disabled="isCurrentBranch(branchItem.id)"
                  class="h-4 w-4 rounded border-slate-300 text-slate-900 focus:ring-slate-400 disabled:opacity-70"
                />

                <div class="min-w-0">
                  <p
                    class="truncate text-sm font-semibold"
                    :class="
                      isCurrentBranch(branchItem.id)
                        ? 'text-blue-900'
                        : form.branch_ids.includes(branchItem.id)
                          ? 'text-white'
                          : 'text-slate-800'
                    "
                  >
                    {{ branchItem.name }}
                  </p>

                  <p
                    class="mt-1 text-xs"
                    :class="
                      isCurrentBranch(branchItem.id)
                        ? 'text-blue-700'
                        : form.branch_ids.includes(branchItem.id)
                          ? 'text-slate-200'
                          : 'text-slate-500'
                    "
                  >
                    {{
                      isCurrentBranch(branchItem.id)
                        ? 'Sucursal actual'
                        : form.branch_ids.includes(branchItem.id)
                          ? 'Producto disponible aquí'
                          : 'Marcar para habilitar'
                    }}
                  </p>
                </div>

                <span
                  v-if="isCurrentBranch(branchItem.id)"
                  class="ml-auto inline-flex shrink-0 items-center rounded-full bg-white/80 px-2.5 py-1 text-[11px] font-semibold text-blue-700"
                >
                  Fija
                </span>

                <span
                  v-else-if="form.branch_ids.includes(branchItem.id)"
                  class="ml-auto inline-flex shrink-0 items-center rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold text-white"
                >
                  Activa
                </span>
              </label>
            </div>
          </div>

          <p v-if="form.errors.branch_ids" class="text-red-500 text-xs mt-2">
            {{ form.errors.branch_ids }}
          </p>
        </div>

        <div class="relative">
          <div class="flex items-center justify-between mb-2">
            <label class="text-sm font-semibold text-slate-600">
              Categoría
            </label>

            <button
              v-if="mode !== 'view'"
              type="button"
              @click="toggleCategoryInputMode"
              class="text-xs px-3 py-1 rounded-lg bg-slate-100 hover:bg-slate-200"
            >
              {{
                categoryInputMode === "select"
                  ? "+ Nueva categoría"
                  : "Usar categoría existente"
              }}
            </button>
          </div>

          <template v-if="categoryInputMode === 'select'">
            <SelectField
              label=""
              field="category_id"
              v-model="form.category_id"
              :options="categoriesDB"
              placeholder="Selecciona una categoría"
              :disabled="mode === 'view'"
              :error="form.errors.category_id"
            />
          </template>

          <template v-else>
            <InputField
              label=""
              field="category_name"
              v-model="form.category_name"
              placeholder="Escribe el nombre de la categoría"
              :error="form.errors.category_name"
              :readonly="mode === 'view'"
            />
          </template>
        </div>

        <InputField
          label="Nombre"
          field="name"
          v-model="form.name"
          :error="form.errors.name"
          :readonly="mode === 'view'"
        />

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

        <div class="md:col-span-2 grid grid-cols-1 gap-5 md:grid-cols-2">
          <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
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
          </div>

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
      </div>

    </div>
  </GlobalModal>
</template>
