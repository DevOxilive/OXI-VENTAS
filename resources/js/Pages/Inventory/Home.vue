<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import ProductModal from '@/Components/Inventory/ProductModal.vue'
import ProductTable from '@/Components/Inventory/ProductTable.vue'
import StockEntryModal from '@/Components/Inventory/BranchProducts/StockEntryModal.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import { useProductActions } from '@/Composables/Inventory/useProductActions'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { usePermissions } from '@/Composables/usePermissions'
import { getProductToolbarConfig } from '@/config/ToolbarConfigs/productToolbarConfig'
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribeRealtime } from '@/realtime'

defineOptions({ layout: AdminLayout })

let unsubscribeProductChanged = null

function cloneProductsPayload(payload) {
  return {
    ...(payload ?? {}),
    data: Array.isArray(payload?.data)
      ? payload.data.map((item) => ({ ...item }))
      : [],
    links: Array.isArray(payload?.links) ? [...payload.links] : [],
  }
}

function sortProducts(items) {
  return [...items].sort((left, right) => {
    return Number(right?.branch_product_id ?? 0) - Number(left?.branch_product_id ?? 0)
  })
}

function normalizeFilterIds(value) {
  return (Array.isArray(value) ? value : value ? [value] : [])
    .map((item) => String(item))
    .filter(Boolean)
}

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
  productDepartmentsDB: {
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
  canManagePricing: {
    type: Boolean,
    default: false,
  },
})

const productsState = ref(cloneProductsPayload(props.productsDB))
const showStockEntryModal = ref(false)
const selectedStockProduct = ref(null)
const branch = computed(() => props.branch ?? {})
const categoriesDB = computed(() => props.categoriesDB ?? [])
const productDepartmentsDB = computed(() => props.productDepartmentsDB ?? [])
const branchesDB = computed(() => props.branchesDB ?? [])

watch(
  () => props.productsDB,
  (value) => {
    productsState.value = cloneProductsPayload(value)
  },
  { deep: true }
)

const search = ref(props.filters.search ?? '')
const productDepartmentFilter = ref(normalizeFilterIds(props.filters.product_department_id))
const categoryFilter = ref(normalizeFilterIds(props.filters.category_id))
const recordsToShow = ref(Number(props.filters.per_page ?? 25))
const { handlePageChange } = useGlobalTablePagination({
  only: ['productsDB', 'filters', 'categoriesDB', 'productDepartmentsDB'],
})

const products = computed(() => productsState.value?.data ?? [])
const currentPage = computed(() => productsState.value?.current_page ?? 1)
const totalProducts = computed(() => productsState.value?.total ?? products.value.length)
const toolbarCategories = computed(() => {
  if (!productDepartmentFilter.value.length) {
    return categoriesDB.value
  }

  const selectedDepartmentIds = productDepartmentFilter.value.map(String)

  return categoriesDB.value.filter((category) => {
    return selectedDepartmentIds.includes(String(category.product_department_id))
  })
})

const productToolbarConfig = computed(() =>
  getProductToolbarConfig({
    branch: branch.value,
    productDepartments: productDepartmentsDB.value,
    productDepartmentFilter: productDepartmentFilter.value,
    categories: toolbarCategories.value,
    categoryFilter: categoryFilter.value,
    canCreate: can('inventory.products.create'),
  })
)

function handleProductToolbarFilter({ key, value }) {
  if (key === 'productDepartmentFilter') {
    productDepartmentFilter.value = value

    if (value.length) {
      categoryFilter.value = categoryFilter.value.filter((categoryId) => {
        const category = categoriesDB.value.find((item) => String(item.id) === String(categoryId))

        return category && value.map(String).includes(String(category.product_department_id))
      })
    }
  }

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
  if (action === 'stock-entry' && can('inventory.branches.stock-in')) {
    openStockEntryModal(row)
  }

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

function buildStockEntryProduct(product) {
  return {
    ...product,
    id: product.branch_product_id,
    product_id: product.id,
    assigned_branch_ids: product.branch_ids ?? [product.branch_id].filter(Boolean),
  }
}

function openStockEntryModal(product) {
  if (!product?.branch_product_id) return

  selectedStockProduct.value = buildStockEntryProduct(product)
  showStockEntryModal.value = true
}

function closeStockEntryModal() {
  showStockEntryModal.value = false
  selectedStockProduct.value = null
  reloadProducts(currentPage.value)
}

function reloadProducts(pageOrUrl = 1) {
  const isUrl = typeof pageOrUrl === 'string'
  const requestUrl = isUrl
    ? pageOrUrl
    : route('inventory.branches.products.index', {
      branch: branch.value.slug,
    })
  const requestData = isUrl
    ? {}
    : {
      search: search.value,
      product_department_id: productDepartmentFilter.value.length ? productDepartmentFilter.value : undefined,
      category_id: categoryFilter.value.length ? categoryFilter.value : undefined,
      per_page: recordsToShow.value,
      page: pageOrUrl,
    }

  router.get(
    requestUrl,
    requestData,
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['productsDB', 'filters', 'categoriesDB', 'productDepartmentsDB'],
    }
  )
}

function upsertProduct(product) {
  if (!product?.branch_product_id) return

  const nextItems = [...products.value]
  const existingIndex = nextItems.findIndex((item) => {
    return Number(item.branch_product_id) === Number(product.branch_product_id)
  })

  if (existingIndex >= 0) {
    nextItems[existingIndex] = {
      ...nextItems[existingIndex],
      ...product,
    }
  } else {
    nextItems.push(product)
  }

  const sortedItems = sortProducts(nextItems)
  const currentTotal = Number(productsState.value?.total ?? nextItems.length)

  productsState.value = {
    ...(productsState.value ?? {}),
    data: sortedItems,
    total: existingIndex >= 0 ? currentTotal : currentTotal + 1,
  }
}

function removeProduct(productId) {
  const nextItems = products.value.filter((item) => {
    return Number(item.id) !== Number(productId)
  })

  const currentTotal = Number(productsState.value?.total ?? products.value.length)
  const removed = nextItems.length !== products.value.length

  productsState.value = {
    ...(productsState.value ?? {}),
    data: nextItems,
    total: removed ? Math.max(0, currentTotal - 1) : currentTotal,
  }
}

async function fetchProductSnapshot(productId) {
  if (!productId || !branch.value?.slug) return null

  try {
    const { data } = await window.axios.get(
      route('inventory.branches.products.snapshot', {
        branch: branch.value.slug,
        productId,
      })
    )

    return data?.product ?? null
  } catch (error) {
    console.error('No se pudo actualizar el producto en tiempo real', error)
    return null
  }
}

let searchTimer = null

watch(search, () => {
  clearTimeout(searchTimer)

  searchTimer = setTimeout(() => {
    reloadProducts(1)
  }, 150)
})

watch([productDepartmentFilter, categoryFilter, recordsToShow], () => {
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
  const updateOnProductChange = async (event) => {
    if (!shouldReloadForProductEvent(event)) return

    if (
      event.action === 'deleted' &&
      selectedProduct.value?.id === event.productId
    ) {
      closeModal()
    }

    if (event.action === 'deleted') {
      removeProduct(event.productId ?? event.product_id)
      return
    }

    const product = await fetchProductSnapshot(event.productId ?? event.product_id)

    if (!product) {
      removeProduct(event.productId ?? event.product_id)
      return
    }

    upsertProduct(product)
  }

  unsubscribeProductChanged = subscribeRealtime(
    REALTIME_CHANNELS.inventoryProducts,
    REALTIME_EVENTS.productChanged,
    updateOnProductChange,
  )
})

onBeforeUnmount(() => {
  clearTimeout(searchTimer)

  unsubscribeProductChanged?.()
})
</script>

<template>
  <PageLayout>
    <template #toolbar>
      <GlobalToolbar v-bind="productToolbarConfig" :search="search" :records-per-page="recordsToShow"
        :filtered-records="products.length" :total-records="totalProducts"
        @update:search="search = $event" @update:filter="handleProductToolbarFilter"
        @update:records-per-page="recordsToShow = $event" @action="handleProductToolbarAction" />
    </template>

    <ProductTable :items="products" :pagination="productsState" @page-change="handlePageChange"
      @action="handleProductAction" />

    <StockEntryModal v-if="showStockEntryModal && selectedStockProduct && can('inventory.branches.stock-in')"
      :product="selectedStockProduct" :branches="branchesDB" :current-branch="branch" @close="closeStockEntryModal" />

    <ProductModal v-if="
      showModal &&
      (
        (modalMode === 'create' && can('inventory.products.create')) ||
        (modalMode === 'edit' && can('inventory.products.update')) ||
        (modalMode === 'view' && can('inventory.products.view'))
      )
    " :mode="modalMode" :product="selectedProduct" :categoriesDB="categoriesDB"
      :productDepartmentsDB="productDepartmentsDB"
      :branchesDB="branchesDB" :branch="branch" :can-manage-pricing="canManagePricing" @close="closeModal" />
  </PageLayout>
</template>
