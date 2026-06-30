<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import ProductModal from '@/Components/Inventory/ProductModal.vue'
import ProductTable from '@/Components/Inventory/ProductTable.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import { useProductActions } from '@/Composables/Inventory/useProductActions'
import { usePermissions } from '@/Composables/usePermissions'
import { getProductToolbarConfig } from '@/config/ToolbarConfigs/productToolbarConfig'

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
const recordsToShow = ref(Number(props.filters.per_page ?? 50))

const products = computed(() => props.productsDB?.data ?? [])
const currentPage = computed(() => props.productsDB?.current_page ?? 1)

const productToolbarConfig = computed(() =>
  getProductToolbarConfig({
    branch: branch.value,
    categories: categoriesDB.value,
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

function handleProductAction({ action, row }) {
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

function reloadProducts(pageOrUrl = 1) {
  const isUrl = typeof pageOrUrl === 'string'

  router.get(
    isUrl
      ? pageOrUrl
      : route('inventory.branches.products.index', {
        branch: branch.value.slug,
      }),
    {
      search: search.value,
      category_id: categoryFilter.value === 'Todas' ? null : categoryFilter.value,
      per_page: recordsToShow.value,
      page: isUrl ? undefined : pageOrUrl,
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

function shouldReloadForProductEvent(event) {
  const branchIds = event.branchIds ?? event.branch_ids ?? []

  if (!branchIds.length) return true

  return branchIds.map(Number).includes(Number(branch.value.id))
}

onMounted(() => {
  if (!window.Echo) return

  const reloadOnProductChange = (event) => {
    if (!shouldReloadForProductEvent(event)) return

    if (
      showModal.value &&
      ['created', 'updated'].includes(event.action)
    ) {
      closeModal()
    }

    if (
      event.action === 'deleted' &&
      selectedProduct.value?.id === event.productId
    ) {
      closeModal()
    }

    reloadProducts(currentPage.value)
  }

  window.Echo.channel('inventory.products')
    .listen('.product.changed', reloadOnProductChange)
})

onBeforeUnmount(() => {
  clearTimeout(searchTimer)

  if (!window.Echo) return

  window.Echo.leave('inventory.products')
})
</script>

<template>
  <PageLayout>
    <template #toolbar>
      <GlobalToolbar v-bind="productToolbarConfig" :search="search" :records-per-page="recordsToShow"
        :filtered-records="products.length" :total-records="productsDB?.total ?? products.length"
        @update:search="search = $event" @update:filter="handleProductToolbarFilter"
        @update:records-per-page="recordsToShow = $event" @action="handleProductToolbarAction" />
    </template>

    <ProductTable :items="products" :pagination="productsDB" @page-change="reloadProducts"
      @action="handleProductAction" />

    <ProductModal v-if="
      showModal &&
      (
        (modalMode === 'create' && can('inventory.products.create')) ||
        (modalMode === 'edit' && can('inventory.products.update')) ||
        (modalMode === 'view' && can('inventory.products.view'))
      )
    " :mode="modalMode" :product="selectedProduct" :categoriesDB="categoriesDB" :subcategoriesDB="subcategoriesDB"
      :branchesDB="branchesDB" :branch="branch" @close="closeModal" />
  </PageLayout>
</template>
