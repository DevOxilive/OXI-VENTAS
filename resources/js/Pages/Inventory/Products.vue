<script setup>
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";

import AdminLayout from "@/Layouts/AdminLayout.vue";

import ProductToolbar from "@/Components/Inventory/BranchProducts/ProductToolbar.vue";
import ProductTable from "@/Components/Inventory/BranchProducts/ProductTable.vue";
import ProductMobileCards from "@/Components/Inventory/BranchProducts/ProductMobileCards.vue";
import ProductRegisterModal from "@/Components/Inventory/BranchProducts/ProductRegisterModal.vue";

defineOptions({
  layout: AdminLayout,
});

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

const products = computed(() => {
  if (Array.isArray(props.productsDB)) {
    return props.productsDB;
  }

  return props.productsDB?.data ?? [];
});

const paginationLinks = computed(() => {
  return Array.isArray(props.productsDB?.links)
    ? props.productsDB.links
    : [];
});

const hasPagination = computed(() => {
  return !Array.isArray(props.productsDB) && paginationLinks.value.length > 0;
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

function reloadProducts() {
  router.get(
    window.location.pathname,
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

function goToPage(url) {
  if (!url) return;

  router.visit(url, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
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
function exportExcel() {
  console.log("Exportar excel");
}

function adjustStock(selectedProduct) {
  console.log("Ajustar stock", selectedProduct);
}

function deleteProduct(selectedProduct) {
  console.log("Eliminar producto", selectedProduct);
}
</script>


<template>
  <section class="max-w-[1030px] mx-auto py-8 px-6 bg-white min-h-[calc(100vh-90px)]">

    <div class="bg-white rounded-3xl border border-slate-200 overflow-hidden">
      <ProductFilters
        v-model:search="search"
        v-model:category-filter="categoryFilter"
        v-model:records-to-show="recordsToShow"
        @create="openCreateModal"
      />

      <ProductTable
      :products="products"
        @view="openViewModal"
        @edit="openEditModal"
        @delete="deleteProduct"
      />
    </div>

    <ProductToolbar :total="props.productsDB?.total ?? filteredProducts.length" v-model:records-to-show="recordsToShow"
      @create="openCreateModal" />

    <ProductTable :filtered-products="filteredProducts" v-model:search="search" v-model:branch-filter="branchFilter"
      v-model:category-filter="categoryFilter" v-model:stock-filter="stockFilter" @view="openViewModal"
      @edit="openEditModal" @adjust="adjustStock" @delete="deleteProduct" />


    <ProductMobileCards :filtered-products="filteredProducts" @view="openViewModal" @edit="openEditModal"
      @adjust="adjustStock" @delete="deleteProduct" />

    <div v-if="hasPagination" class="flex flex-wrap items-center justify-center gap-2 mt-6">
      <button v-for="link in paginationLinks" :key="link.label" type="button" :disabled="!link.url"
        class="px-3 py-2 rounded-lg text-sm border transition disabled:opacity-40 disabled:cursor-not-allowed" :class="link.active
          ? 'bg-slate-900 text-white border-slate-900'
          : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'" @click="goToPage(link.url)"
        v-html="link.label" />
    </div>

    <ProductRegisterModal v-if="showModal" :modo="modalMode" :product="product" :frontend-errors="frontendErrors"
      @close="closeModal" @save="submitProduct" @validate="validateField" />
  </section>
</template>