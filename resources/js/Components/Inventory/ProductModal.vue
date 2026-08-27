<script setup>
import { useForm } from "@inertiajs/vue3";
import { watch, computed, ref, onBeforeUnmount } from "vue";
import GlobalModal from "@/Components/Modales/GlobalModal.vue";
import InputField from "@/Components/Forms/InputField.vue";
import SelectionCheckboxCard from "@/Components/Forms/SelectionCheckboxCard.vue";
import SelectField from "@/Components/Forms/SelectField.vue";
import ActionIconButton from "@/Components/Forms/ActionIconButton.vue";
import ToggleSwitch from "@/Components/Forms/ToggleSwitch.vue";
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
  productDepartmentsDB: {
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
  product_department_id: "",
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
const activeStep = ref(1);
const modalSections = [
  { id: 1, label: "Datos y precios" },
  { id: 2, label: "Sucursales activas" },
];
const marginPercentage = ref("");
const pricingDriver = ref("percentage");
const syncingPricing = ref(false);
const fileInput = ref(null);
const isDragActive = ref(false);
const filePreviewUrl = ref(null);
const pendingFormSnapshot = ref(null);

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

function displayDecimal(value) {
  if (value === null || value === undefined || value === "") return "";

  return String(value).replace(/(\.\d*?)0+$/, "$1").replace(/\.$/, "");
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

function barcodeFieldError(index) {
  return form.errors[`barcodes.${index}`] || (index === 0 ? form.errors.barcodes : null);
}

function clearBarcodeError(index) {
  form.clearErrors(`barcodes.${index}`);

  if (index === 0) {
    form.clearErrors("barcodes");
  }
}

function clearFieldErrors(...fields) {
  form.clearErrors(...fields);
}

function hasServerErrors() {
  return Object.keys(form.errors || {}).length > 0;
}

function captureFormSnapshot() {
  return {
    barcodes: [...form.barcodes],
    branch_ids: [...form.branch_ids],
    inventory_unit: form.inventory_unit,
    has_box_presentation: form.has_box_presentation,
    pieces_per_box: form.pieces_per_box,
    name: form.name,
    min_stock: form.min_stock,
    product_department_id: form.product_department_id,
    category_id: form.category_id,
    category_name: form.category_name,
    cost_per_piece: form.cost_per_piece,
    sale_price_per_piece: form.sale_price_per_piece,
    cost_per_box: form.cost_per_box,
    sale_price_per_box: form.sale_price_per_box,
    allow_low_margin: form.allow_low_margin,
    entry_date: form.entry_date,
    active: form.active,
    image: form.image,
    quantity: form.quantity,
    kilos: form.kilos,
    grams: form.grams,
    liters: form.liters,
    record_version: form.record_version,
    marginPercentage: marginPercentage.value,
    pricingDriver: pricingDriver.value,
    activeStep: activeStep.value,
  };
}

function restoreFormSnapshot(snapshot) {
  if (!snapshot) return;

  form.barcodes = [...snapshot.barcodes];
  form.branch_ids = [...snapshot.branch_ids];
  form.inventory_unit = snapshot.inventory_unit;
  form.has_box_presentation = snapshot.has_box_presentation;
  form.pieces_per_box = snapshot.pieces_per_box;
  form.name = snapshot.name;
  form.min_stock = snapshot.min_stock;
  form.product_department_id = snapshot.product_department_id;
  form.category_id = snapshot.category_id;
  form.category_name = snapshot.category_name;
  form.cost_per_piece = snapshot.cost_per_piece;
  form.sale_price_per_piece = snapshot.sale_price_per_piece;
  form.cost_per_box = snapshot.cost_per_box;
  form.sale_price_per_box = snapshot.sale_price_per_box;
  form.allow_low_margin = snapshot.allow_low_margin;
  form.entry_date = snapshot.entry_date;
  form.active = snapshot.active;
  form.image = snapshot.image;
  form.quantity = snapshot.quantity;
  form.kilos = snapshot.kilos;
  form.grams = snapshot.grams;
  form.liters = snapshot.liters;
  form.record_version = snapshot.record_version;

  marginPercentage.value = snapshot.marginPercentage;
  pricingDriver.value = snapshot.pricingDriver;
  activeStep.value = snapshot.activeStep;
}

watch(
  () => props.product,
  (product) => {
    if (props.mode === "create" || form.processing || hasServerErrors()) return;

    form.reset();
    activeStep.value = 1;

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

    form.min_stock = displayDecimal(product.min_stock ?? 0);
    form.product_department_id = product.product_department_id ?? "";
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
const selectedBranchIds = computed(() =>
  new Set(form.branch_ids.map((branchId) => Number(branchId)))
);
const categoriesForDepartment = computed(() => {
  if (!form.product_department_id) return [];

  return props.categoriesDB.filter((category) => {
    return Number(category.product_department_id) === Number(form.product_department_id);
  });
});
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

function goToStep(step) {
  activeStep.value = Math.min(2, Math.max(1, Number(step) || 1));
}

function goToFirstInvalidStep(errors) {
  const fields = Object.keys(errors || {});
  if (fields.length > 0 && fields.every((field) => field === "branch_ids")) {
    activeStep.value = 2;
    return;
  }

  activeStep.value = 1;
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
  revokeFilePreview();
  if (fileInput.value) {
    fileInput.value.value = "";
  }

  form.barcodes = [""];
  form.branch_ids = [];
  ensureCurrentBranchSelected();
  form.inventory_unit = "pza";
  form.has_box_presentation = false;
  form.pieces_per_box = "";
  form.name = "";
  form.min_stock = 0;
  form.product_department_id = "";
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

  if (selectedBranchIds.value.has(Number(branchId))) {
    form.branch_ids = form.branch_ids.filter((id) => Number(id) !== Number(branchId));
    return;
  }

  form.branch_ids = [...form.branch_ids, branchId];
}

function isBranchSelected(branchId) {
  return selectedBranchIds.value.has(Number(branchId));
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
  () => form.product_department_id,
  () => {
    const selectedCategoryBelongsToDepartment = categoriesForDepartment.value.some((category) => {
      return Number(category.id) === Number(form.category_id);
    });

    if (!selectedCategoryBelongsToDepartment) {
      form.category_id = "";
    }
  }
);

watch(
  () => form.category_id,
  (categoryId) => {
    if (!categoryId) return;

    const category = props.categoriesDB.find((item) => Number(item.id) === Number(categoryId));

    if (category?.product_department_id) {
      form.product_department_id = category.product_department_id;
    }
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

  if (marginBelowMinimum.value && !form.allow_low_margin) {
    ErrorAlert({
      title: "Porcentaje de ganancia menor al permitido",
      message: "El porcentaje mínimo es 10%. Usa el botón de autorización para continuar con un porcentaje menor.",
    });

    return;
  }
  ensureCurrentBranchSelected();
  pendingFormSnapshot.value = captureFormSnapshot();

  if (props.mode === "create") {
    form.post(
      route("inventory.branches.products.store", {
        branch: branchSlug,
      }),
      {
        forceFormData: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
          pendingFormSnapshot.value = null;

          ToastAlert({
            title: "Producto creado correctamente",
          });

          form.clearErrors();
          activeStep.value = 1;
          setCreateDefaults();
        },
        onError: (errors) => {
          restoreFormSnapshot(pendingFormSnapshot.value);
          pendingFormSnapshot.value = null;
          goToFirstInvalidStep(errors);

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
            form.clearErrors("barcodes");
            return;
          }

          ErrorAlert({
            title: "Error al crear producto",
            message:
              errors.name ||
              errors.product_department_id ||
              errors.category_id ||
              errors.category_name ||
              errors.inventory_unit ||
              errors.cost_per_piece ||
              errors.cost_per_box ||
              errors.sale_price_per_piece ||
              errors.sale_price_per_box ||
              errors.branch_ids ||
              errors.product ||
              "Revisa los datos capturados",
          });
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
          preserveState: true,
          preserveScroll: true,
          onSuccess: () => {
            pendingFormSnapshot.value = null;

            ToastAlert({
              title: "Producto actualizado correctamente",
            });
          },
          onError: (errors) => {
            restoreFormSnapshot(pendingFormSnapshot.value);
            pendingFormSnapshot.value = null;
            goToFirstInvalidStep(errors);

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
                errors.product_department_id ||
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
    :sections="modalSections"
    :active-section="activeStep"
    :show-footer="false"
    @select-section="goToStep"
    @save="submit"
    @close="$emit('close')"
  >
    <div class="bg-background p-4 md:p-5 xl:p-6">
      <input
        ref="fileInput"
        type="file"
        accept="image/*"
        class="hidden"
        @change="handleFileChange"
      />

      <div
        v-show="activeStep === 1"
        class="grid gap-5"
        :class="'grid-cols-1 xl:grid-cols-[220px_minmax(0,1fr)]'"
      >
        <section class="hidden xl:flex xl:flex-col">
          <button
            type="button"
            class="flex h-full min-h-[480px] w-full items-center justify-center rounded-[24px] border border-dashed bg-secondary p-4 text-left transition"
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

        <div class="min-w-0 space-y-4">
        <section class="space-y-3">
          <div class="border-y border-secondary py-4">
            <div class="mb-3 flex items-center justify-between gap-3">
              <span aria-hidden="true"></span>

              <button
                v-if="mode !== 'view'"
                type="button"
                @click="addBarcode"
                class="inline-flex items-center justify-center rounded-xl border border-secondary bg-background px-3 py-2 text-xs font-semibold text-text transition hover:border-primary hover:text-primary"
              >
                Agregar código
              </button>
            </div>

            <div class="max-h-[152px] space-y-2 overflow-y-auto pr-1">
              <div
                v-for="(barcode, index) in form.barcodes"
                :key="index"
                class="flex items-start gap-2"
              >
                <div class="flex-1">
                  <InputField
                    :label="index === 0 ? 'Código principal' : `Alterno ${index}`"
                    :field="`barcodes.${index}`"
                    validation-field="barcode"
                    v-model="form.barcodes[index]"
                    icon="barcode_scanner"
                    :error="barcodeFieldError(index)"
                    :readonly="mode === 'view'"
                    @validate="clearBarcodeError(index)"
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
              @validate="clearFieldErrors('name')"
            />

            <SelectField
              label="Departamento"
              field="product_department_id"
              v-model="form.product_department_id"
              :options="productDepartmentsDB"
              placeholder="Selecciona un departamento"
              :disabled="mode === 'view'"
              :error="form.errors.product_department_id"
              @validate="clearFieldErrors('product_department_id')"
              @change="clearFieldErrors('product_department_id')"
            />

            <SelectField
              label="Categoría"
              field="category_id"
              v-model="form.category_id"
              :options="categoriesForDepartment"
              placeholder="Selecciona una categoría"
              :disabled="mode === 'view' || !form.product_department_id"
              :error="form.errors.category_id || form.errors.category_name"
              @validate="clearFieldErrors('category_id', 'category_name')"
              @change="clearFieldErrors('category_id', 'category_name')"
            />

            <SelectField
              label="Unidad base de inventario"
              field="inventory_unit"
              v-model="form.inventory_unit"
              :options="units"
              placeholder="Selecciona unidad base"
              :disabled="mode === 'view'"
              @validate="clearFieldErrors('inventory_unit')"
              @change="clearFieldErrors('inventory_unit')"
            />

            <div
              v-if="!isKilogramUnit"
              class="relative"
            >
              <label class="mb-1 block text-sm font-semibold text-text">
                Captura y vende por caja
              </label>
              <div class="flex h-[46px] items-center justify-between rounded-xl border border-secondary bg-background px-4">
                <span class="text-xs text-text opacity-70">Inventario en piezas.</span>
                <ToggleSwitch
                  v-model="form.has_box_presentation"
                  :disabled="mode === 'view'"
                />
              </div>
            </div>

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
              @validate="clearFieldErrors('pieces_per_box')"
            />

            <InputField
              label="Stock mínimo"
              field="min_stock"
              :validation-field="isKilogramUnit ? 'kilogram_quantity' : 'product_piece_quantity'"
              v-model="form.min_stock"
              :error="form.errors.min_stock"
              type="text"
              inputmode="decimal"
              :readonly="mode === 'view'"
              @validate="clearFieldErrors('min_stock')"
            />
            <InputField
              :label="isKilogramUnit ? 'Precio compra por kilogramo' : 'Precio compra por pieza'"
              field="cost_per_piece"
              validation-field="product_price"
              v-model="form.cost_per_piece"
              prefix="$"
              :error="form.errors.cost_per_piece"
              type="text"
              step="0.01"
              :readonly="mode === 'view'"
              @validate="clearFieldErrors('cost_per_piece')"
            />

            <InputField
              v-if="hasBoxPresentation"
              label="Precio compra por caja"
              field="cost_per_box"
              validation-field="product_price"
              v-model="form.cost_per_box"
              prefix="$"
              :error="form.errors.cost_per_box"
              type="text"
              step="0.01"
              :readonly="mode === 'view'"
              @validate="clearFieldErrors('cost_per_box')"
            />

            <template v-if="canManagePricing">
              <InputField
                label="Porcentaje de ganancia"
                field="margin_percentage"
                :model-value="marginPercentage"
                @update:modelValue="handleMarginPercentageChange"
                suffix="%"
                type="text"
                step="0.01"
                :readonly="mode === 'view'"
                @validate="clearFieldErrors('sale_price_per_piece')"
              />

              <InputField
                :label="isKilogramUnit ? 'Precio venta por kilogramo' : 'Precio venta por pieza'"
                field="sale_price_per_piece"
                validation-field="product_price"
                :model-value="form.sale_price_per_piece"
                @update:modelValue="handleSalePriceChange"
                prefix="$"
                :error="form.errors.sale_price_per_piece"
                type="text"
                step="0.01"
                :readonly="mode === 'view'"
              />

              <template v-if="hasBoxPresentation">
                <InputField
                  label="Precio venta por caja"
                  field="sale_price_per_box"
                  validation-field="product_price"
                  :model-value="form.sale_price_per_box"
                  @update:modelValue="handleBoxSalePriceChange"
                  prefix="$"
                  :error="form.errors.sale_price_per_box"
                  type="text"
                  step="0.01"
                  :readonly="mode === 'view'"
                  @validate="clearFieldErrors('sale_price_per_box')"
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
        </div>

      </div>

      <section v-show="activeStep === 2" class="space-y-5">
          <div class="flex justify-end">
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

          <div class="overflow-y-auto py-4 xl:max-h-[320px]">
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 xl:grid-cols-3">
              <SelectionCheckboxCard
                v-for="branchItem in branchesDB"
                :key="branchItem.id"
                variant="solid"
                icon="storefront"
                class="min-h-[116px]"
                :checked="isBranchSelected(branchItem.id)"
                :disabled="mode === 'view' || isCurrentBranch(branchItem.id)"
                :highlighted="isCurrentBranch(branchItem.id)"
                :title="branchItem.name"
                :description="
                  isCurrentBranch(branchItem.id)
                    ? 'Sucursal actual'
                    : isBranchSelected(branchItem.id)
                      ? 'Haz clic para eliminar'
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

    <template #footer>
      <div class="flex items-center justify-between gap-3 border-t border-secondary bg-background px-5 py-4 md:px-8">
        <button
          v-if="activeStep > 1"
          type="button"
          class="rounded-xl border border-secondary bg-background px-4 py-2.5 text-sm font-semibold text-text transition hover:border-primary hover:text-primary"
          @click="goToStep(activeStep - 1)"
        >
          Volver a datos
        </button>
        <span v-else />

        <div class="flex items-center gap-3">
          <button
            type="button"
            class="rounded-xl border border-secondary bg-background px-4 py-2.5 text-sm font-semibold text-text transition hover:border-primary hover:text-primary"
            @click="$emit('close')"
          >
            Cancelar
          </button>
          <button
            v-if="activeStep < 2"
            type="button"
            class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:brightness-95"
            @click="goToStep(activeStep + 1)"
          >
            Continuar
          </button>
          <button
            v-else-if="mode !== 'view'"
            type="button"
            class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="form.processing"
            @click="submit"
          >
            {{ form.processing ? 'Guardando...' : mode === 'edit' ? 'Actualizar producto' : 'Guardar producto' }}
          </button>
        </div>
      </div>
    </template>
  </GlobalModal>
</template>
