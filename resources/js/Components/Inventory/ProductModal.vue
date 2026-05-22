<script setup>
import { useForm } from "@inertiajs/vue3";
import { watch } from "vue";
import InputField from "@/Components/Forms/InputField.vue";
import SelectField from "@/Components/Forms/SelectField.vue";

const props = defineProps({
  mode: String,
  product: Object,
  branch: Object,

  categoriesDB: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["close"]);

const form = useForm({
  barcode: "",
  name: "",
  stock: 0,
  category_id: "",
  cost: "",
  sale_price: "",
  entry_date: new Date().toISOString().slice(0, 10),
  active: true,
  image: null,
});

watch(
  () => props.product,
  (product) => {
    form.reset();

    if (!product) return;

    form.barcode = product.barcode ?? "";
    form.name = product.name ?? "";
    form.stock = product.stock ?? 0;
    form.category_id = product.category_id ?? "";
    form.cost = product.cost ?? "";
    form.sale_price = product.price ?? "";
    form.entry_date =
      product.entry_date ?? new Date().toISOString().slice(0, 10);
    form.active = true;
  },
  { immediate: true }
);

function submit() {
  const branchSlug = props.branch?.slug

  if (!branchSlug) {
    console.error('No llegó branch.slug al modal:', props.branch)
    return
  }

  if (props.mode === "create") {
    form.post(
      route("inventory.branches.products.store", {
        branch: branchSlug,
      }),
      {
        preserveScroll: true,
        onSuccess: () => emit("close"),
        onError: (errors) => {
          console.log("ERRORES CREAR PRODUCTO:", errors);
        },
      }
    )

    return
  }

  if (props.mode === "edit") {
    form.put(
      route("inventory.branches.products.update", {
        branch: props.product.branch_slug ?? branchSlug,
        product: props.product.id,
      }),
      {
        preserveScroll: true,
        onSuccess: () => emit("close"),
        onError: (errors) => {
          console.log("ERRORES PRODUCTO:", errors);
        },
      }
    )
  }
}
</script>

<template>
  <div
    class="fixed inset-0 bg-black/40 z-[9999] flex items-center justify-center p-4"
  >
    <div
      class="bg-white rounded-3xl w-full max-w-4xl shadow-2xl overflow-hidden"
    >
      <div class="flex items-center justify-between px-6 py-5 border-b">
        <h2 class="text-2xl font-bold text-slate-800">
          {{
            mode === "create"
              ? "Nuevo producto"
              : mode === "edit"
              ? "Editar producto"
              : "Ver producto"
          }}
        </h2>

        <button
          @click="$emit('close')"
          class="text-slate-400 hover:text-slate-700 text-2xl"
        >
          ×
        </button>
      </div>

      <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
        <InputField
          label="Código de barras"
          field="barcode"
          v-model="form.barcode"
          icon="barcode_scanner"
          :error="form.errors.barcode"
        />
      <div>

    <label class="block text-sm font-semibold text-slate-600 mb-2">
        Imagen del producto
    </label>

    <div
    class="border-2 border-dashed border-slate-300 rounded-2xl px-6 py-4 flex items-center gap-5 bg-slate-50"
>

        <template v-if="form.image">

    <img
        :src="URL.createObjectURL(form.image)"
        class="w-20 h-20 object-cover rounded-2xl shadow border"
    />

</template>

<template v-else>

    <div
        class="w-20 h-20 rounded-2xl bg-white border flex items-center justify-center"
    >
        <span class="material-symbols-outlined text-4xl text-slate-400">
            image
        </span>
    </div>

</template>

<div class="flex-1">

    <p class="text-sm font-semibold text-slate-700">
        Imagen del producto
    </p>

    <p class="text-xs text-slate-500 mt-1">
        JPG, PNG o WEBP
    </p>

    <input
        type="file"
        accept="image/*"
        class="mt-3 text-sm"
        @change="form.image = $event.target.files[0]"
    />

</div>
</div>

</div>

        <InputField
          label="Nombre"
          field="name"
          v-model="form.name"
          :error="form.errors.name"
          :readonly="mode === 'view'"
        />
        <InputField
          label="Stock"
          field="stock"
          v-model="form.stock"
          icon="package_2"
        />

        <SelectField
          label="Categoría"
          field="category_id"
          v-model="form.category_id"
          :error="form.errors.category_id"
          :disabled="mode === 'view'"
          :options="
            categoriesDB.map((category) => ({
              value: category.id,
              label: category.name,
            }))
          "
          placeholder="Selecciona categoría"
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
          :error="form.errors.sale_price"
          type="text"
          step="0.01"
          :readonly="mode === 'view'"
        />

        <InputField
          label="Fecha de ingreso"
          field="entry_date"
          v-model="form.entry_date"
          :error="form.errors.entry_date"
          type="date"
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