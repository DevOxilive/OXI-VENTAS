<script setup>
import { useForm } from "@inertiajs/vue3";
import { watch, computed } from "vue";
import InputField from "@/Components/Forms/InputField.vue";
import SelectField from "@/Components/Forms/SelectField.vue";
import {
    ToastAlert,
    ErrorAlert
} from '@/Components/Modales/UniversalActionModal'

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
  stock: 0,
  category_id: "",
  cost: "",
  sale_price: "",
  entry_date: new Date().toISOString().slice(0, 10),
  active: true,
  image: null,

  quantity: null,
  kilos: null,
  grams: null,

});

watch(
  () => props.product,
  (product) => {
    form.reset();

    if (!product) return;

    form.barcodes = product.barcodes?.length
      ? product.barcodes
      : [product.barcode ?? ""];

    form.unit = product.unit ?? "";
    form.name = product.name ?? "";
  form.branch_ids = product?.branch_ids?.length
  ? [...product.branch_ids]
  : []

ensureCurrentBranchSelected()
    form.stock = product.stock ?? 0;
    form.category_id = product.category_id ?? "";
    form.cost = product.cost ?? "";
    form.sale_price = product.price ?? "";
    form.entry_date =
      product.entry_date ?? new Date().toISOString().slice(0, 10);
    form.active = true;
    form.image = product.image ?? null;

    form.quantity = product.quantity ?? null;
    form.kilos = product.kilos ?? null;
    form.liters = product.liters ?? null;

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
const imagePreview = computed(() => {
  if (!form.image) return null;

  if (form.image instanceof File) {
    return URL.createObjectURL(form.image);
  }

  return form.image;
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
function ensureCurrentBranchSelected() {
  const currentBranchId = props.branch?.id

  if (!currentBranchId) return

  const exists = form.branch_ids.some(
    branchId => Number(branchId) === Number(currentBranchId)
  )

  if (!exists) {
    form.branch_ids.push(currentBranchId)
  }
}
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
ensureCurrentBranchSelected()
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

    const barcodeError = form.errors['barcodes.0']

    if (barcodeError) {

        ErrorAlert({
    title: "Código ya registrado",
    message: `
        <div style="text-align:left;line-height:1.7;">
            ${barcodeError}
        </div>
    `
})

        form.clearErrors('barcodes.0')
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
        errors['barcodes.0'] ||
        errors.barcodes ||
        form.errors['barcodes.0']

    if (barcodeError) {
        ErrorAlert({
            title: "Código ya registrado",
            message: barcodeError,
        }).then(() => {
            form.clearErrors('barcodes.0')
            form.clearErrors('barcodes')
        })

        return
    }

    ErrorAlert({
        title: "Error al crear producto",
        message:
            errors.name ||
            errors.category_id ||
            errors.unit ||
            errors.cost ||
            errors.sale_price ||
            errors.branch_ids ||
            "Revisa los datos capturados",
    })
},
      }
    );
}
}
</script>

<template>
  <div class="fixed inset-0 bg-black/40 z-[9999] flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-5xl shadow-2xl overflow-hidden">
      
      <div class="flex items-center justify-between px-6 py-5 border-b">
        <h2 class="text-2xl font-bold text-slate-800">
          {{ mode === "create" ? "Nuevo producto" : mode === "edit" ? "Editar producto" : "Ver producto" }}
        </h2>

        <button @click="$emit('close')" class="text-slate-400 hover:text-slate-700 text-2xl">
          ×
        </button>
      </div>

      <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">

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
                −
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

          <div class="border-2 border-dashed border-slate-300 rounded-2xl px-6 py-4 flex items-center gap-5 bg-slate-50">
            <template v-if="imagePreview">
              <img
                :src="imagePreview"
:class="mode === 'view'
  ? 'w-40 h-40 object-contain rounded-2xl border bg-white'
  : 'w-28 h-28 object-contain rounded-2xl shadow border bg-white'"              />
            </template>

            <template v-else>
              <div class="w-28 h-28 rounded-2xl bg-white border flex items-center justify-center">
                <span class="material-symbols-outlined text-4xl text-slate-400">
                  image
                </span>
              </div>
            </template>
<div v-if="mode !== 'view'" class="flex-1">

              <p class="text-sm font-semibold text-slate-900">
                Imagen del producto
              </p>

              <p class="text-xs text-slate-500 mt-1">
                JPG, PNG o WEBP
              </p>

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
                  ✓ Imagen cargada
                </p>
              </template>
            </div>
          </div>
        </div>
<!-- SUCURSALES -->
<div v-if="mode !== 'view'" class="md:col-span-2">
<div class="flex items-center justify-between mb-2">
    <label class="block text-sm font-semibold text-slate-600">
      Sucursales donde se agregará
    </label>

    <button
      type="button"
      class="text-sm font-semibold text-slate-700 hover:text-black"
    @click="
  form.branch_ids.length === branchesDB.length
    ? form.branch_ids = [props.branch?.id].filter(Boolean)
    : form.branch_ids = branchesDB.map(branch => branch.id)
"
    >
      {{ form.branch_ids.length === branchesDB.length ? 'Quitar todas' : 'Seleccionar todas' }}
    </button>
  </div>

  <div class="max-h-[130px] overflow-y-auto border border-slate-200 rounded-2xl p-3 bg-slate-50">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
      <label
        v-for="branchItem in branchesDB"
        :key="branchItem.id"
        class="flex items-center gap-3 border rounded-xl px-4 py-3 bg-white cursor-pointer hover:bg-slate-100"
      >
  <input
  type="checkbox"
  :value="branchItem.id"
  v-model="form.branch_ids"
  :disabled="isCurrentBranch(branchItem.id)"
  class="rounded border-slate-300 disabled:opacity-60"
/>

     {{ branchItem.name }}
<span
  v-if="isCurrentBranch(branchItem.id)"
  class="text-xs text-slate-400 ml-1"
>
  actual
</span>
      </label>
    </div>
  </div>

  <p
    v-if="form.errors.branch_ids"
    class="text-red-500 text-xs mt-2"
  >
    {{ form.errors.branch_ids }}
  </p>
</div>
        <SelectField
          label="Categoría"
          field="category_id"
          v-model="form.category_id"
          :error="form.errors.category_id"
          :disabled="mode === 'view'"
          :options="categoriesDB.map((category) => ({
            value: category.id,
            label: category.name,
          }))"
          placeholder="Selecciona categoría"
        />

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
          label="Precio inicial"
          field="cost"
          v-model="form.cost"
          prefix="$"
          :error="form.errors.cost"
          type="text"
          step="0.01"
          :readonly="mode === 'view'"
        />

       <InputField
  label="Precio venta"
  field="sale_price"
  v-model="form.sale_price"
  prefix="$"
  :error="invalidPrice ? 'El precio de venta no puede ser menor al precio inicial' : form.errors.sale_price"
  type="text"
  step="0.01"
  :readonly="mode === 'view'"
/>

      </div>

      <div class="flex justify-end gap-3 px-6 py-5 border-t">
        <button
          @click="$emit('close')"
          class="px-5 py-3 rounded-2xl bg-slate-200 text-slate-700 font-semibold"
        >
          Cancelar
        </button>

        <button
          v-if="mode !== 'view'"
          @click="submit"
          class="px-5 py-3 rounded-2xl bg-slate-900 text-white font-semibold"
        >
          {{ mode === "create" ? "Crear producto" : "Actualizar producto" }}
        </button>
      </div>

    </div>
  </div>
</template>