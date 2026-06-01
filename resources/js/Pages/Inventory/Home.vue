<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'

import ProductFilters from '@/Components/Inventory/ProductFilters.vue'
import InventoryTable from '@/Components/Inventory/ProductTable.vue'
import InventoryMobileCards from '@/Components/Inventory/ProductMobileCards.vue'

import ProductModal from '@/Components/Inventory/ProductModal.vue'

import { useProductFilters } from '@/Composables/Inventory/useProductFilters'
import { useProductActions } from '@/Composables/Inventory/useProductActions'
import { usePermissions } from '@/Composables/usePermissions'

import { computed } from 'vue'
defineOptions({ layout: AdminLayout })
const { can } = usePermissions()
const props = defineProps({
    productsDB: {
        type: Array,
        default: () => []
    },
    categoriesDB: Array,
    subcategoriesDB: Array,
    branchesDB: Array,
    branch: Object,
})

const branch = computed(() => props.branch ?? {})
const productsDB = computed(() => props.productsDB ?? [])

const categoriesDB = computed(() => props.categoriesDB ?? [])
const subcategoriesDB = computed(() => props.subcategoriesDB ?? [])
const branchesDB = computed(() => props.branchesDB ?? [])


const {
    search,
    categoryFilter,
    subcategoryFilter,
    filteredProducts,
    paginatedProducts,
    recordsToShow,
    currentPage,
    totalPages,
} = useProductFilters(productsDB)

const {
    showModal,
    modalMode,
    selectedProduct,
    openCreateModal,
    openEditModal,
    openViewModal,
    closeModal,
    deleteProduct,
} = useProductActions()

</script>
<template>
  <div class="relative px-8 py-6">

    <div class="bg-[#f8f5f9] rounded-3xl p-6 min-h-[calc(100vh-180px)]">
      <ProductFilters
        v-model:records-to-show="recordsToShow"
        :search="search"
        :categoryFilter="categoryFilter"
        :categoriesDB="categoriesDB"
        @update:search="search = $event"
        @update:categoryFilter="categoryFilter = $event"
        @create="openCreateModal"
      />

     <div class="mt-5 bg-white rounded-2xl border border-slate-200 overflow-hidden">
  <div class="max-h-[520px] overflow-y-auto">
    <InventoryTable
      :products="paginatedProducts"
      @view="openViewModal"
      @edit="openEditModal"
      @delete="deleteProduct"
    />
  </div>
</div>
      <InventoryMobileCards
        :products="paginatedProducts"
        @view="openViewModal"
        @edit="openEditModal"
        @delete="deleteProduct"
      />

      <div class="flex items-center justify-between mt-6 px-2">
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
    </div>

    <ProductModal
      v-if="
        showModal &&
        (
          (modalMode === 'create' && can('inventario.crear')) ||
          (modalMode === 'edit' && can('inventario.editar')) ||
          (modalMode === 'view' && can('inventario.ver'))
        )
      "
      :mode="modalMode"
      :product="selectedProduct"
      :categoriesDB="categoriesDB"
      :subcategoriesDB="subcategoriesDB"
      :branchesDB="branchesDB"
      :branch="branch"
      @close="closeModal"
    />
  </div>
</template>