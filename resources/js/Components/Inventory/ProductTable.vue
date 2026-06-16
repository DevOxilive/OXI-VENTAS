<script setup>
import { computed } from "vue";
import GlobalTable from "@/Components/Tables/GlobalTable.vue";

const props = defineProps({
  products: {
    type: Array,
    default: () => [],
  },
  canView: {
    type: Boolean,
    default: false,
  },
  canEdit: {
    type: Boolean,
    default: false,
  },
  canDelete: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["view", "edit", "delete"]);

const columns = [
  {
    key: "barcode",
    label: "Código barras",
    format: "text",
    fallback: "Sin código",
    mobileLabel: "Código",
  },
  {
    key: "image",
    label: "Imagen",
    format: "badge",
    formatOptions: {
      labelMap: {
        true: "✓ Con imagen",
        false: "✕ Sin imagen",
      },
      statusMap: {
        true: "green",
        false: "slate",
      },
    },
    mobileDisplay: false,
  },
  {
    key: "name",
    label: "Producto",
    format: "text",
    subKey: "presentation",
    mobileSecondary: true,
  },
  {
    key: "category_name",
    label: "Categoría",
    format: "badge",
    fallback: "Sin categoría",
    mobileBadge: true,
  },
  {
    key: "unit",
    label: "Unidad de medida",
    format: "text",
    fallback: "Sin unidad",
    mobileDisplay: false,
  },
  {
    key: "cost",
    label: "Precio inicial",
    format: "currency",
    formatOptions: {
      decimals: 2,
      fallback: "$0.00",
    },
    mobileDisplay: false,
  },
  {
    key: "price",
    label: "Precio venta",
    format: "currency",
    formatOptions: {
      decimals: 2,
      fallback: "$0.00",
    },
    mobileLabel: "Precio",
  },
];

const actions = computed(() => [
  {
    id: "view",
    label: "Ver",
    icon: "visibility",
    variant: "blue",
    hidden: () => !props.canView,
    mobile: "button",
  },
  {
    id: "edit",
    label: "Editar",
    icon: "edit",
    variant: "amber",
    hidden: () => !props.canEdit,
    mobile: "button",
  },
  {
    id: "delete",
    label: "Eliminar",
    icon: "delete",
    variant: "red",
    hidden: () => !props.canDelete,
    mobile: "button",
  },
]);

function handleTableAction({ action, row }) {
  emit(action, row);
}
</script>

<template>
  <GlobalTable :items="products" :columns="columns" :actions="actions" row-key="id" mobile-card-header-field="name"
    no-data-message="No se encontraron productos" @action="handleTableAction" />
</template>