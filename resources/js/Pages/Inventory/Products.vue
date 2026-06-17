<script setup>
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";

import AdminLayout from "@/Layouts/AdminLayout.vue";
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import { usePermissions } from '@/Composables/usePermissions'
import { getProductToolbarConfig } from "@/config/ToolbarConfigs/productToolbarConfig";
import { productTableConfig } from '@/config/TableConfigs/productTableConfig'
import ProductRegisterModal from "@/Components/Inventory/ProductRegisterModal.vue";
import PageLayout from '@/Layouts/PageLayout.vue'

defineOptions({
  layout: AdminLayout,
});
const { can } = usePermissions()

const props = defineProps({
  productsDB: {
    type: [Object, Array],
    default: () => ({ data: [] }),
  },
  filters: {
    type: Object,
    default: () => ({
      search: "",
      per_page: 50,
    }),
  },
  categoriesDB: {
    type: Array,
    default: () => [],
  },
  branch: {
    type: Object,
    default: () => ({}),
  },
});

const showModal = ref(false);
const modalMode = ref("create");
const frontendErrors = ref({});
const processing = ref(false);

const search = ref(props.filters?.search ?? "");
const branchFilter = ref("");
const categoryFilter = ref("");
const stockFilter = ref("");

const recordsToShow = ref(Number(props.filters?.per_page ?? 50));

const product = ref({
  code: "",
  barcode: "",
  name: "",
  category: "",
  unit: "",
  status: "Disponible",
  branch: "",
  stock: "",
  minStock: "",
  lot: "",
  expirationDate: "",
  purchasePrice: "",
  salePrice: "",
  supplier: "",
  responsible: "",
  notes: "",
  errors: {},
});

const productToolbarConfig = computed(() =>
  getProductToolbarConfig({
    branch: props.branch,
    categories: props.categoriesDB ?? [],
    categoryFilter: categoryFilter.value,
    canCreate: can('inventory.products.create'),
  })
)

function handleProductToolbarFilter({ key, value }) {
  if (key === 'categoryFilter') {
    categoryFilter.value = value
  }
}

function handleProductToolbarAction(action) {
  if (action === 'create' && can('inventory.products.create')) {
    openCreateModal()
  }
}
function handleProductTableAction({ action, row }) {
  if (action === 'view' && can('inventory.products.view')) {
    openViewModal(row)
  }

  if (action === 'edit' && can('inventory.products.update')) {
    openEditModal(row)
  }

  if (action === 'delete' && can('inventory.products.delete')) {
    deleteProduct(row)
  }
}
const products = computed(() => {
  if (Array.isArray(props.productsDB)) {
    return props.productsDB;
  }

  return props.productsDB?.data ?? [];
});

const filteredProducts = computed(() => {
  return products.value.filter((product) => {
    const matchesBranch =
      !branchFilter.value || product.branch_name === branchFilter.value || product.branch === branchFilter.value;

    const matchesCategory =
      !categoryFilter.value || product.category_name === categoryFilter.value || product.category === categoryFilter.value;

    const matchesStock =
      !stockFilter.value || product.status === stockFilter.value;

    return matchesBranch && matchesCategory && matchesStock;
  });
});

let searchTimeout = null;

watch(search, () => {
  clearTimeout(searchTimeout);

  searchTimeout = setTimeout(() => {
    reloadProducts();
  }, 400);
});

watch(recordsToShow, () => {
  reloadProducts();
});

function reloadProducts(pageUrl = null) {
  const request = pageUrl ?? window.location.pathname

  router.get(
    request,
    {
      search: search.value || undefined,
      per_page: recordsToShow.value,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  );
}

function resetProduct() {
  product.value = {
    code: "",
    barcode: "",
    name: "",
    category: "",
    unit: "",
    status: "Disponible",
    branch: "",
    stock: "",
    minStock: "",
    lot: "",
    expirationDate: "",
    purchasePrice: "",
    salePrice: "",
    supplier: "",
    responsible: "",
    notes: "",
    errors: {},
  };

  frontendErrors.value = {};
}
function openCreateModal() {
  resetProduct();
  modalMode.value = "create";
  showModal.value = true;
}

function normalizeProduct(selectedProduct) {
  return {
    ...selectedProduct,

    code: selectedProduct.code || "",
    barcode:
      selectedProduct.barcode ||
      selectedProduct.bar_code ||
      selectedProduct.codigo_barras ||
      "",

    name: selectedProduct.name || "",

    category:
      selectedProduct.category_name ||
      selectedProduct.category ||
      "",

    unit: selectedProduct.unit || "",

    status: selectedProduct.status || "Disponible",

    branch:
      selectedProduct.branch ||
      selectedProduct.branch_name ||
      "",

    stock: selectedProduct.stock || 0,

    minStock:
      selectedProduct.minimum_stock ||
      selectedProduct.minStock ||
      0,

    lot: selectedProduct.lot || "",

    expirationDate:
      selectedProduct.expiration_date ||
      selectedProduct.expirationDate ||
      "",

    purchasePrice:
      selectedProduct.cost ||
      selectedProduct.purchasePrice ||
      "",

    salePrice:
      selectedProduct.price ||
      selectedProduct.salePrice ||
      "",

    supplier: selectedProduct.supplier || "",

    responsible: selectedProduct.responsible || "",

    notes: selectedProduct.notes || "",

    errors: {},
  };
}

function openEditModal(selectedProduct) {
  product.value = normalizeProduct(selectedProduct);

  modalMode.value = "edit";
  showModal.value = true;
}

function openViewModal(selectedProduct) {
  product.value = normalizeProduct(selectedProduct);

  modalMode.value = "view";
  showModal.value = true;
}

function closeModal() {
  showModal.value = false;
}

function validateField(field) {
  frontendErrors.value[field] = "";
}

function submitProduct() {

  if (modalMode.value === "create") {

    productsDB.value.push({
      ...product.value,
      id: Date.now(),
    });

  } else if (modalMode.value === "edit") {

    const index = productsDB.value.findIndex(
      p => p.id === product.value.id
    );

    if (index !== -1) {
      productsDB.value[index] = {
        ...product.value
      };
    }
  }

  closeModal();
}

function deleteProduct(selectedProduct) {

  const confirmed = confirm(
    `¿Eliminar ${selectedProduct.name}?`
  );

  if (!confirmed) return;

  productsDB.value = productsDB.value.filter(
    p => p.id !== selectedProduct.id
  );
}
</script>
<template>
  <PageLayout>
    <template #toolbar>
      <GlobalToolbar v-bind="productToolbarConfig" :search="search" :records-per-page="recordsToShow"
        :filtered-records="filteredProducts.length" :total-records="products.length" @update:search="search = $event"
        @update:filter="handleProductToolbarFilter" @update:records-per-page="recordsToShow = $event"
        @action="handleProductToolbarAction" />
    </template>

    <GlobalTable :items="filteredProducts" v-bind="productTableConfig" :pagination="productsDB"
      @page-change="reloadProducts" @action="handleProductTableAction" />

    <ProductRegisterModal v-if="showModal" :modo="modalMode" :product="product" :frontend-errors="frontendErrors"
      @close="closeModal" @save="submitProduct" @validate="validateField" />
  </PageLayout>
</template>