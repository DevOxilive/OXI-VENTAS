<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import ProductModal from '@/Components/Inventory/ProductModal.vue'
import ProductTable from '@/Components/Inventory/ProductTable.vue'
import StockEntryModal from '@/Components/Inventory/BranchProducts/StockEntryModal.vue'
import ProductBatchesModal from '@/Components/Inventory/BranchProducts/ProductBatchesModal.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import { ErrorAlert } from '@/Components/Modales/UniversalActionModal'
import { useBatchAdjustmentModal } from '@/Composables/Inventory/useBatchAdjustmentModal'
import { useProductActions } from '@/Composables/Inventory/useProductActions'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { usePermissions } from '@/Composables/usePermissions'
import { getProductToolbarConfig } from '@/config/ToolbarConfigs/productToolbarConfig'
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribeRealtime, subscribeRealtimeReconnect } from '@/realtime'
import { createInventoryRealtimeState } from '@/utils/inventoryRealtimeState.mjs'
import { createInventoryRealtimeBatch, createInventoryPageRefresh, readInventoryPage, inventoryPartialHeaders } from '@/utils/inventoryRealtimeBatch.mjs'

defineOptions({ layout: AdminLayout })

let unsubscribeProductChanged = null
let unsubscribeStockUpdated = null
let unsubscribeReconnect = null
let lastSearchVisit = { key: '', at: 0 }
let realtimeMounted = false
const realtimeState = createInventoryRealtimeState()
const page = usePage()

function cloneProductsPayload(payload) {
  return {
    ...(payload ?? {}),
    data: Array.isArray(payload?.data)
      ? payload.data.map((item) => ({ ...item }))
      : [],
    links: Array.isArray(payload?.links) ? [...payload.links] : [],
  }
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
const selectedStockBranch = ref(null)
const branch = computed(() => props.branch ?? {})
const categoriesDB = computed(() => props.categoriesDB ?? [])
const productDepartmentsDB = computed(() => props.productDepartmentsDB ?? [])
const branchesDB = computed(() => props.branchesDB ?? [])

watch(
  () => props.productsDB,
  (value) => {
    realtimeState.invalidateAll()
    pageRefresh.invalidate()
    productsState.value = cloneProductsPayload(value)
  },
  { deep: true }
)

const search = ref(props.filters.search ?? '')
const productDepartmentFilter = ref(normalizeFilterIds(props.filters.product_department_id))
const categoryFilter = ref(normalizeFilterIds(props.filters.category_id))
const recordsToShow = ref(Number(props.filters.per_page ?? 25))
const { handlePageChange: visitProductPage } = useGlobalTablePagination({
  only: ['productsDB', 'filters'],
})
function handlePageChange(url) {
  pageRefresh.invalidate()
  visitProductPage(url)
}

const products = computed(() => productsState.value?.data ?? [])
const currentPage = computed(() => productsState.value?.current_page ?? 1)
const totalProducts = computed(() => productsState.value?.total ?? products.value.length)
const showProductBatchesModal = ref(false)
const selectedBatchesProduct = ref(null)
const selectedBatchProducts = computed(() => selectedBatchesProduct.value
  ? [selectedBatchesProduct.value]
  : [])
const {
  liveSelectedBatch,
  processing: batchAdjustmentProcessing,
  form: batchAdjustmentForm,
  frontendErrors: batchAdjustmentErrors,
  totalErrors: batchAdjustmentTotalErrors,
  isSeasonal: batchAdjustmentIsSeasonal,
  calculatedQuantity: batchAdjustmentCalculatedQuantity,
  adjustmentText: batchAdjustmentText,
  quantityResultColor: batchAdjustmentQuantityResultColor,
  adjustBatch,
  clearSelectedBatch,
  setAdjustmentType: setBatchAdjustmentType,
  validateField: validateBatchAdjustmentField,
  saveEditedBatch,
} = useBatchAdjustmentModal(selectedBatchProducts)
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

  if (action === 'batches' && can('inventory.branches.batches.update')) {
    openProductBatchesModal(row)
  }

  if (action === 'edit' && can('inventory.products.update')) {
    openEditModal(row)
  }

  if (action === 'delete' && can('inventory.products.delete')) {
    deleteProduct(row)
  }
}

async function openProductBatchesModal(product) {
  if (!product?.branch_product_id) return
  const branchId = branch.value.id

  try {
    const { data } = await window.axios.get(
      route('inventory.branch-inventory.details', {
        branchProduct: product.branch_product_id,
      }),
      { params: { include_movements: 0 } }
    )

    if (!realtimeState.isActive() || Number(branch.value.id) !== Number(branchId)) return

    clearSelectedBatch()
    selectedBatchesProduct.value = {
      ...product,
      ...data,
      id: product.id,
      branch_product_id: product.branch_product_id,
      name: product.name ?? data?.product?.name ?? 'Producto',
      inventory_unit: data?.product?.inventory_unit ?? product.inventory_unit ?? 'pza',
      unit: data?.product?.unit ?? product.unit ?? 'pza',
      batches: data?.batches ?? [],
    }
    showProductBatchesModal.value = true
  } catch (error) {
    if (!realtimeState.isActive() || Number(branch.value.id) !== Number(branchId)) return
    console.error('No se pudieron cargar los lotes del producto', error)
    ErrorAlert({
      title: 'No se pudieron cargar los lotes',
      message: 'Actualiza la página e intenta nuevamente.',
    })
  }
}

function closeProductBatchesModal() {
  clearSelectedBatch()
  showProductBatchesModal.value = false
  selectedBatchesProduct.value = null
}

function saveBatchChanges() {
  saveEditedBatch(closeProductBatchesModal)
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
  selectedStockBranch.value = { ...branch.value }
  showStockEntryModal.value = true
}

function closeStockEntryModal() {
  showStockEntryModal.value = false
  selectedStockProduct.value = null
  selectedStockBranch.value = null
}

function reloadProducts(pageOrUrl = 1) {
  clearTimeout(searchTimer)
  pageRefresh.invalidate()
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
  const requestKey = JSON.stringify([requestUrl, requestData])
  if (requestKey === lastSearchVisit.key && Date.now() - lastSearchVisit.at < 300) return
  lastSearchVisit = { key: requestKey, at: Date.now() }

  router.get(
    requestUrl,
    requestData,
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ['productsDB', 'filters'],
    }
  )
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

let searchTimer = null
const pageRefresh = createInventoryPageRefresh({
  getContext: () => JSON.stringify([window.location.href, page.component, branch.value.id, search.value,
    productDepartmentFilter.value, categoryFilter.value, recordsToShow.value]),
  load: async ({ signal }) => {
    const { data } = await window.axios.get(window.location.href, {
      signal,
      headers: inventoryPartialHeaders(page, ['productsDB', 'filters']),
    })
    return readInventoryPage(data, page.component, 'productsDB')
  },
  apply: (value) => { productsState.value = cloneProductsPayload(value) },
  onError: (error) => console.error('No se pudieron actualizar los productos', error),
})
const realtimeBatch = createInventoryRealtimeBatch({
  flush: async (events) => {
    const branchId = branch.value.id
    // La búsqueda pendiente traerá su propia página con los filtros nuevos.
    if (search.value === (props.filters.search ?? '')) await pageRefresh.refresh()
    if (!realtimeState.isActive() || Number(branch.value.id) !== Number(branchId)) return
    if (showProductBatchesModal.value && selectedBatchesProduct.value && events.some((event) => (
      event.reconnect || Number(event.productId ?? event.product_id) === Number(selectedBatchesProduct.value.id)
    ))) {
      const product = selectedBatchesProduct.value
      await realtimeState.request(`details:${product.branch_product_id}`, {
        load: async () => {
          const { data } = await window.axios.get(route('inventory.branch-inventory.details', {
            branchProduct: product.branch_product_id,
          }), { params: { include_movements: 0 } })
          return data
        },
        onSuccess: (data) => {
          if (selectedBatchesProduct.value?.branch_product_id === product.branch_product_id) {
            selectedBatchesProduct.value = { ...product, ...data, id: product.id, batches: data.batches ?? [] }
          }
        },
        onError: (error) => console.error('No se pudieron actualizar los lotes', error),
      })
    }
  },
})

function handleSearchEnter(event) {
  if (event.key !== 'Enter' || event.target?.tagName !== 'INPUT' || event.target?.placeholder !== productToolbarConfig.value.searchPlaceholder) return
  reloadProducts(1)
}

watch(search, () => {
  clearTimeout(searchTimer)
  pageRefresh.invalidate()

  searchTimer = setTimeout(() => {
    reloadProducts(1)
  }, /^\d{8,14}$/.test(search.value.trim()) ? 0 : 250)
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
const selectedProductBranch = ref({ ...branch.value })
watch(showModal, (open) => {
  if (open) selectedProductBranch.value = { ...branch.value }
})

function shouldReloadForProductEvent(event) {
  const branchIds = event.branchIds ?? event.branch_ids ?? []

  if (!branchIds.length) return true

  return branchIds.map(Number).includes(Number(branch.value.id))
}

function subscribeForCurrentBranch() {
  unsubscribeProductChanged?.()
  unsubscribeStockUpdated?.()
  unsubscribeProductChanged = null
  unsubscribeStockUpdated = null
  const subscribedBranchId = branch.value.id
  const updateOnProductChange = async (event) => {
    if (Number(branch.value.id) !== Number(subscribedBranchId)) return
    if (!shouldReloadForProductEvent(event)) return
    const productId = event.productId ?? event.product_id
    if (!productId) return

    if (
      event.action === 'deleted' &&
      Number(selectedProduct.value?.id) === Number(productId)
    ) {
      closeModal()
    }

    if (event.action === 'deleted') {
      realtimeState.invalidate(`product:${productId}`)
      removeProduct(productId)
      realtimeBatch.add(event)
      return
    }

    realtimeBatch.add(event)
  }

  unsubscribeProductChanged = subscribeRealtime(
    REALTIME_CHANNELS.inventoryProducts,
    REALTIME_EVENTS.productChanged,
    updateOnProductChange,
  )
  if (branch.value.id) {
    unsubscribeStockUpdated = subscribeRealtime(
      REALTIME_CHANNELS.inventoryBranch(branch.value.id),
      REALTIME_EVENTS.stockUpdated,
      (event) => {
        if (Number(branch.value.id) === Number(subscribedBranchId)) realtimeBatch.add(event)
      },
    )
  }
}

watch(() => branch.value.id, () => {
  clearTimeout(searchTimer)
  realtimeState.invalidateAll()
  pageRefresh.invalidate()
  realtimeBatch.clear()
  lastSearchVisit = { key: '', at: 0 }
  if (realtimeMounted) subscribeForCurrentBranch()
}, { flush: 'post' })

onMounted(() => {
  realtimeMounted = true
  subscribeForCurrentBranch()
  unsubscribeReconnect = subscribeRealtimeReconnect(() => realtimeBatch.add({ reconnect: true }))
})

onBeforeUnmount(() => {
  clearTimeout(searchTimer)
  realtimeMounted = false
  realtimeState.dispose()
  pageRefresh.dispose()
  realtimeBatch.dispose()

  unsubscribeProductChanged?.()
  unsubscribeStockUpdated?.()
  unsubscribeReconnect?.()
})
</script>

<template>
  <PageLayout>
    <template #toolbar>
      <GlobalToolbar v-bind="productToolbarConfig" :search="search" :records-per-page="recordsToShow" @keydown="handleSearchEnter"
        select-search-on-enter
        :filtered-records="products.length" :total-records="totalProducts"
        @update:search="search = $event" @update:filter="handleProductToolbarFilter"
        @update:records-per-page="recordsToShow = $event" @action="handleProductToolbarAction" />
    </template>

    <ProductTable :items="products" :pagination="productsState" @page-change="handlePageChange"
      @action="handleProductAction" />

    <StockEntryModal v-if="showStockEntryModal && selectedStockProduct && can('inventory.branches.stock-in')"
      :product="selectedStockProduct" :branches="branchesDB" :current-branch="selectedStockBranch" @close="closeStockEntryModal" />

    <ProductBatchesModal
      v-if="showProductBatchesModal && selectedBatchesProduct && can('inventory.branches.batches.update')"
      :product="selectedBatchesProduct" :selected-batch="liveSelectedBatch" :form="batchAdjustmentForm"
      :frontend-errors="batchAdjustmentErrors" :total-errors="batchAdjustmentTotalErrors"
      :processing="batchAdjustmentProcessing" :is-seasonal="batchAdjustmentIsSeasonal"
      :calculated-quantity="batchAdjustmentCalculatedQuantity" :adjustment-text="batchAdjustmentText"
      :quantity-result-color="batchAdjustmentQuantityResultColor" :set-adjustment-type="setBatchAdjustmentType"
      :validate-field="validateBatchAdjustmentField" @select-batch="adjustBatch" @save="saveBatchChanges"
      @close="closeProductBatchesModal" />

    <ProductModal v-if="
      showModal &&
      (
        (modalMode === 'create' && can('inventory.products.create')) ||
        (modalMode === 'edit' && can('inventory.products.update')) ||
        (modalMode === 'view' && can('inventory.products.view'))
      )
    " :mode="modalMode" :product="selectedProduct" :categoriesDB="categoriesDB"
      :productDepartmentsDB="productDepartmentsDB"
      :branchesDB="branchesDB" :branch="selectedProductBranch" :can-manage-pricing="canManagePricing" @close="closeModal" />
  </PageLayout>
</template>
