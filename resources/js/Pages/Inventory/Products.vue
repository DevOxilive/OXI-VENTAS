<script setup>
import { computed, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";

import AdminLayout from "@/Layouts/AdminLayout.vue";
import ProductTable from '@/Components/Inventory/ProductTable.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import { usePermissions } from '@/Composables/usePermissions'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { getProductToolbarConfig } from "@/config/ToolbarConfigs/productToolbarConfig";
import ProductRegisterModal from "@/Components/Inventory/ProductRegisterModal.vue";
import PageLayout from '@/Layouts/PageLayout.vue'
import {
  ToastAlert,
} from '@/Components/Modales/UniversalActionModal'
import { confirmModalAction } from '@/Components/Modales/useModalConfig'
defineOptions({
  layout: AdminLayout,
});

const { can } = usePermissions()
const page = usePage()

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
const search = ref(props.filters?.search ?? "");
const branchFilter = ref("");
const categoryFilter = ref("");
const stockFilter = ref("");
const recordsToShow = ref(Number(props.filters?.per_page ?? 50));
const { handlePageChange } = useGlobalTablePagination()
const branches = computed(() => page.props.branches || [])

const localProducts = ref([]);

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

const sourceProducts = computed(() => {
  if (Array.isArray(props.productsDB)) {
    return props.productsDB;
  }

  return props.productsDB?.data ?? [];
});

watch(
  sourceProducts,
  (products) => {
    localProducts.value = [...products];
  },
  { immediate: true },
);

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

const filteredProducts = computed(() => {
  return localProducts.value.filter((product) => {
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
  const requestData = pageUrl
    ? {}
    : {
      search: search.value || undefined,
      per_page: recordsToShow.value,
    }

  router.get(
    request,
    requestData,
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
    branch_ids: props.branch?.id ? [props.branch.id] : [],
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
    localProducts.value.push({
      ...product.value,
      id: Date.now(),
    });
  } else if (modalMode.value === "edit") {
    const index = localProducts.value.findIndex(
      p => p.id === product.value.id
    );

    if (index !== -1) {
      localProducts.value[index] = {
        ...product.value
      };
    }
  }

  closeModal();
  ToastAlert({
    title: modalMode.value === 'create'
      ? 'Producto creado correctamente'
      : 'Producto actualizado correctamente',
  })
}

async function deleteProduct(selectedProduct) {
  const result = await confirmModalAction({
    mode: 'delete',
    title: 'Eliminar producto',
    message: `?Deseas eliminar ${selectedProduct.name}?`,
    confirmText: 'S?, eliminar',
    cancelText: 'Cancelar',
    confirmButtonColor: '#ef4444',
  })

  if (!result.isConfirmed) return;

  localProducts.value = localProducts.value.filter(
    p => p.id !== selectedProduct.id
  );

  ToastAlert({
    title: 'Producto eliminado correctamente',
  })
}
</script>

<template>
  <PageLayout>
    <template #toolbar>
      <GlobalToolbar v-bind="productToolbarConfig" :search="search" :records-per-page="recordsToShow"
        :filtered-records="filteredProducts.length" :total-records="localProducts.length" @update:search="search = $event"
        @update:filter="handleProductToolbarFilter" @update:records-per-page="recordsToShow = $event"
        @action="handleProductToolbarAction" />
    </template>

    <ProductTable :items="filteredProducts" :pagination="productsDB" @page-change="handlePageChange"
      @action="handleProductTableAction" />

    <ProductRegisterModal v-if="showModal" :modo="modalMode" :product="product" :frontend-errors="frontendErrors"
      :branch="branch" :branches="branches"
      @close="closeModal" @save="submitProduct" @validate="validateField" />
  </PageLayout>
</template>
