<script setup>
import { computed, ref, watch } from "vue";

import AdminLayout from "@/Layouts/AdminLayout.vue";

import ProductToolbar from "@/Components/Inventory/ProductToolbar.vue";
import ProductTable from "@/Components/Inventory/ProductTable.vue";
import ProductMobileCards from "@/Components/Inventory/ProductMobileCards.vue";
import ProductRegisterModal from "@/Components/Inventory/ProductRegisterModal.vue";

defineOptions({
  layout: AdminLayout,
});

const showModal = ref(false);
const modalMode = ref("create");
const frontendErrors = ref({});
const processing = ref(false);

const search = ref("");
const branchFilter = ref("");
const categoryFilter = ref("");
const stockFilter = ref("");

const recordsToShow = ref(10);
const currentPage = ref(1);

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

const productsDB = ref([
  {
    id: 1,
    code: "OXI-001",
    barcode: "750100000001",
    name: "Tanque de oxígeno 680L",
    category: "Oxígeno medicinal",
    branch: "Sucursal Centro",
    stock: 8,
    minStock: 10,
    salePrice: 1450,
    expirationDate: "2026-07-20",
    status: "Stock bajo",
  },
  {
    id: 2,
    code: "OXI-002",
    barcode: "750100000002",
    name: "Concentrador de oxígeno",
    category: "Equipo médico",
    branch: "Sucursal Norte",
    stock: 12,
    minStock: 5,
    salePrice: 12500,
    expirationDate: "",
    status: "Disponible",
  },
  {
    id: 3,
    code: "OXI-003",
    barcode: "750100000003",
    name: "Mascarilla nebulizadora",
    category: "Accesorios",
    branch: "Sucursal Sur",
    stock: 0,
    minStock: 15,
    salePrice: 120,
    expirationDate: "2026-06-01",
    status: "Agotado",
  },
]);
const filteredProducts = computed(() => {

  return productsDB.value.filter((product) => {

    const term = search.value.toLowerCase();

    const matchesSearch =
      product.name.toLowerCase().includes(term) ||
      product.code.toLowerCase().includes(term);

    const matchesBranch =
      !branchFilter.value || product.branch === branchFilter.value;

    const matchesCategory =
      !categoryFilter.value || product.category === categoryFilter.value;

    const matchesStock =
      !stockFilter.value || product.status === stockFilter.value;

    return matchesSearch && matchesBranch && matchesCategory && matchesStock;
    })

  
});

const totalPages = computed(() => {
  return Math.max(
    1,
    Math.ceil(filteredProducts.value.length / recordsToShow.value)
  );
});

const paginatedProducts = computed(() => {
  const start = (currentPage.value - 1) * recordsToShow.value;
  const end = start + recordsToShow.value;

  return filteredProducts.value.slice(start, end);
});


watch(
  [search, branchFilter, categoryFilter, stockFilter, recordsToShow],
  () => {
    currentPage.value = 1;
  }
);

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

function openEditModal(selectedProduct) {
  product.value = {
    ...selectedProduct,
    errors: {},
  };

  modalMode.value = "edit";
  showModal.value = true;
}

function openViewModal(selectedProduct) {
  product.value = {
    ...selectedProduct,
    errors: {},
  };

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
  console.log("Guardar producto");
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
  <section class="space-y-5">
    <div>
      <h1 class="text-xl font-bold text-slate-800">Productos</h1>

      <p class="text-sm text-slate-500 mt-1">
        Gestión general de productos, stock, sucursales y control operativo.
      </p>
    </div>
   <ProductToolbar
    :total="filteredProducts.length"
    v-model:records-to-show="recordsToShow"
    @create="openCreateModal"
/>

    <ProductTable
    :filtered-products="paginatedProducts"
      v-model:search="search"
      v-model:branch-filter="branchFilter"
      v-model:category-filter="categoryFilter"
      v-model:stock-filter="stockFilter"
      @view="openViewModal"
      @edit="openEditModal"
      @adjust="adjustStock"
      @delete="deleteProduct"
    />

    <ProductMobileCards
   :filtered-products="paginatedProducts"
      @view="openViewModal"
      @edit="openEditModal"
      @adjust="adjustStock"
      @delete="deleteProduct"
    />
<div class="flex items-center justify-between mt-6">

    <button
        @click="currentPage--"
        :disabled="currentPage === 1"
        class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-sm disabled:opacity-40"
    >
        Anterior
    </button>

    <div class="text-sm text-slate-500">
        Página {{ currentPage }} de {{ totalPages }}
    </div>

    <button
        @click="currentPage++"
        :disabled="currentPage === totalPages"
        class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-sm disabled:opacity-40"
    >
        Siguiente
    </button>

</div>
    
    <ProductRegisterModal
      v-if="showModal"
      :modo="modalMode"
      :product="product"
      :frontend-errors="frontendErrors"
      @close="closeModal"
      @save="submitProduct"
      @validate="validateField"
    />
  </section>
</template>