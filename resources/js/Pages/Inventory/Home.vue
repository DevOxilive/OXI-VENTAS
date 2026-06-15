<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import ProductModal from '@/Components/Inventory/ProductModal.vue'
import ProductFilters from '@/Components/Inventory/ProductFilters.vue'
import ProductTable from '@/Components/Inventory/ProductTable.vue'
import ProductMobileCards from '@/Components/Inventory/ProductMobileCards.vue'

import { useProductActions } from '@/Composables/Inventory/useProductActions'
import { usePermissions } from '@/Composables/usePermissions'
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

defineOptions({ layout: AdminLayout })

const { can } = usePermissions()

const props = defineProps({
  productsDB: {
    type: [Array, Object],
    default: () => ({ data: [] }),
  },
  categoriesDB: {
    type: Array,
    default: () => [],
  },
  subcategoriesDB: {
    type: Array,
    default: () => [],
  },
  branchesDB: {
    type: Array,
    default: () => [],
  },
  branch: {
    type: Object,
    default: () => ({}),
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
})

const branch = computed(() => props.branch ?? {})
const categoriesDB = computed(() => props.categoriesDB ?? [])
const subcategoriesDB = computed(() => props.subcategoriesDB ?? [])
const branchesDB = computed(() => props.branchesDB ?? [])

const search = ref(props.filters.search ?? '')
const categoryFilter = ref(props.filters.category_id ?? 'Todas')
const recordsToShow = ref(props.filters.per_page ?? 50)

const products = computed(() => props.productsDB?.data ?? [])
const currentPage = computed(() => props.productsDB?.current_page ?? 1)
const totalPages = computed(() => props.productsDB?.last_page ?? 1)

function reloadProducts(page = 1) {
  router.get(
    route('inventory.branches.products.index', {
      branch: branch.value.slug,
    }),
    {
      search: search.value,
      category_id: categoryFilter.value === 'Todas' ? null : categoryFilter.value,
      per_page: recordsToShow.value,
      page,
    },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['productsDB', 'filters'],
    }
  )
}

let searchTimer = null

watch(search, () => {
  clearTimeout(searchTimer)

  searchTimer = setTimeout(() => {
    reloadProducts(1)
  }, 400)
})

watch([categoryFilter, recordsToShow], () => {
  reloadProducts(1)
})

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
  :branch="branch"
  :search="search"
  :category-filter="categoryFilter"
  :categoriesDB="categoriesDB"
  :records-to-show="recordsToShow"
  :can-create="can('inventory.products.create')"
  @update:search="search = $event"
  @update:category-filter="categoryFilter = $event"
  @update:records-to-show="recordsToShow = $event"
  @create="can('inventory.products.create') && openCreateModal()"
/>
      <!-- TABLA CON SCROLL -->
      <div class="mt-5 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="max-h-[520px] overflow-y-auto">
      <ProductTable :products="products" :can-view="can('inventory.products.view')" :can-edit="can('inventory.products.update')" :can-delete="can('inventory.products.delete')"
 @view="can('inventory.products.view') && openViewModal($event)"
  @edit="can('inventory.products.update') && openEditModal($event)"
  @delete="can('inventory.products.delete') && deleteProduct($event)"
/>
        </div>
      </div>

      <!-- MOBILE -->
       <ProductMobileCards :products="products" :can-view="can('inventory.products.view')" :can-edit="can('inventory.products.update')" :can-delete="can('inventory.products.delete')"
  @view="can('inventory.products.view') && openViewModal($event)"
  @edit="can('inventory.products.update') && openEditModal($event)"
  @delete="can('inventory.products.delete') && deleteProduct($event)"
/>
      <!-- PAGINACIÓN -->
      <div class="flex items-center justify-between mt-6 px-2">
        <button @click="reloadProducts(currentPage - 1)" :disabled="currentPage === 1"
          class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-sm disabled:opacity-40">
          Anterior
        </button>

        <div class="text-sm text-slate-500">
          Página {{ currentPage }} de {{ totalPages }}
        </div>

        <button @click="reloadProducts(currentPage + 1)" :disabled="currentPage === totalPages"
          class="px-4 py-2 rounded-xl border border-slate-300 bg-white text-sm disabled:opacity-40">
          Siguiente
        </button>
      </div>
    </div>

    <ProductModal v-if="
      showModal &&
      (
     (modalMode === 'create' && can('inventory.products.create')) ||
(modalMode === 'edit' && can('inventory.products.update')) ||
(modalMode === 'view' && can('inventory.products.view'))
      )
    " :mode="modalMode" :product="selectedProduct" :categoriesDB="categoriesDB" :subcategoriesDB="subcategoriesDB"
      :branchesDB="branchesDB" :branch="branch" @close="closeModal" />
  </div>
</template>