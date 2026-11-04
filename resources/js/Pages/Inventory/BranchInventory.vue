<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import PageLayout from '@/Layouts/PageLayout.vue'

import { usePermissions } from '@/Composables/usePermissions'
import { useBranchInventory } from '@/Composables/Inventory/useBranchInventory'
import { useProductActions } from '@/Composables/Inventory/useProductActions'

import { inventoryTableConfig } from '@/config/TableConfigs/inventoryTableConfig'
import { getInventoryToolbarConfig } from '@/config/ToolbarConfigs/inventoryToolbarConfig'

import InventoryStatsCards from '@/Components/Inventory/BranchProducts/InventoryStatsCards.vue'
import InventoryAlertsModal from '@/Components/Inventory/BranchProducts/InventoryAlertsModal.vue'
import StockEntryModal from '@/Components/Inventory/BranchProducts/StockEntryModal.vue'
import StockExitModal from '@/Components/Inventory/BranchProducts/StockExitModal.vue'
import ProductMovementsModal from '@/Components/Inventory/BranchProducts/ProductMovementsModal.vue'
import ProductBatchesModal from '@/Components/Inventory/BranchProducts/ProductBatchesModal.vue'
import EditBranchProductConfigModal from '@/Components/Inventory/BranchProducts/EditBranchProductConfigModal.vue'
import ProductModal from '@/Components/Inventory/ProductModal.vue'
import { ErrorAlert } from '@/Components/Modales/UniversalActionModal'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    branchProductsDB: {
        type: [Object, Array],
        default: () => ({ data: [] }),
    },
    canUseInventory: Boolean,
    branchesDB: {
        type: Array,
        default: () => [],
    },
    currentBranch: {
        type: Object,
        default: null,
    },
    categoriesDB: {
        type: Array,
        default: () => [],
    },
    productDepartmentsDB: {
        type: Array,
        default: () => [],
    },
    catalogCategoriesDB: {
        type: Array,
        default: () => [],
    },
    catalogProductDepartmentsDB: {
        type: Array,
        default: () => [],
    },
    inventoryStats: {
        type: Object,
        default: () => ({
            total_products: 0,
            total_stock: 0,
            inventory_value: 0,
            low_stock: 0,
            out_of_stock: 0,
            expiring_soon: 0,
            inactive_candidates: 0,
        }),
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            product_department: [],
            category: '',
            stock: '',
            per_page: 25,
        }),
    },
    inventoryAlerts: {
        type: Object,
        default: () => ({
            expired_batches: 0,
            near_expiration_batches: 0,
            low_stock_products: 0,
            inactive_candidate_products: 0,
            expired_batches_list: [],
            near_expiration_batches_list: [],
            low_stock_products_list: [],
            inactive_candidate_products_list: [],
        }),
    },
    canManagePricing: {
        type: Boolean,
        default: false,
    },
})

const { can } = usePermissions()
const inventoryToolbar = ref(null)

function focusInventorySearch() {
    nextTick(() => inventoryToolbar.value?.focusSearch({ select: true }))
}

const {
    categories,
    productDepartments,
    currentBranch,

    showEntryModal,
    showExitModal,
    showMovementsModal,
    showConfigModal,

    liveSelectedMovementProduct,
    selectedMovementBranch,
    liveSelectedMovementsProduct,
    liveSelectedConfigProduct,
    liveSelectedBatch,

    closeConfigModal,
    adjustBatch,

    search,
    productDepartmentFilter,
    categoryFilter,
    stockFilter,
    recordsToShow,

    paginationLinks,
    hasPagination,
    visualProducts,
    filteredProducts,
    inventoryPagination,
    movementLoading,
    historyError,
    loadMovementPage,
    handleSearchEnter,
    alerts,

    selectedAlertType,
    selectedAlertTitle,
    selectedAlertBatches,
    showAlertModal,

    openAlertModal,
    closeAlertModal,

    openEntryModal,
    closeEntryModal,
    openExitModal,
    closeExitModal,
    openMovementsModal,
    closeMovementsModal,

    goToPage,

    showProductBatchesModal,
    liveSelectedBatchesProduct,
    openProductBatchesModal,
    closeProductBatchesModal,
    batchAdjustmentProcessing,
    batchAdjustmentForm,
    batchAdjustmentErrors,
    batchAdjustmentTotalErrors,
    batchAdjustmentIsSeasonal,
    batchAdjustmentCalculatedQuantity,
    batchAdjustmentText,
    batchAdjustmentQuantityResultColor,
    setBatchAdjustmentType,
    validateBatchAdjustmentField,
    saveEditedBatch,
} = useBranchInventory(props, { onReloaded: focusInventorySearch })

const showProductViewModal = ref(false)
const showProductCreateModal = ref(false)
const showProductEditModal = ref(false)
const selectedProduct = ref(null)
const { deleteProduct } = useProductActions()

const inventoryModals = [showEntryModal, showExitModal, showConfigModal, showProductBatchesModal, showMovementsModal, showAlertModal, showProductViewModal, showProductCreateModal, showProductEditModal]
watch(inventoryModals, async (open, previous) => {
    if (!previous.some(Boolean) || open.some(Boolean)) return
    await nextTick()
    if (!inventoryModals.some((modal) => modal.value)) inventoryToolbar.value?.focusSearch({ select: true })
}, { flush: 'post' })

onMounted(focusInventorySearch)

const toolbarCategories = computed(() => {
    if (!productDepartmentFilter.value.length) {
        return categories.value
    }

    const selectedDepartmentIds = productDepartmentFilter.value.map(String)

    return categories.value.filter((category) => {
        return selectedDepartmentIds.includes(String(category.product_department_id))
    })
})

const inventoryColumns = computed(() => props.canUseInventory ? inventoryTableConfig.columns
    : inventoryTableConfig.columns.filter(column => !['status', 'stockLabel'].includes(column.key)))

const inventoryToolbarConfig = computed(() =>
    getInventoryToolbarConfig({
        branch: currentBranch.value,
        productDepartments: productDepartments.value,
        productDepartmentFilter: productDepartmentFilter.value,
        categories: toolbarCategories.value,
        categoryFilter: categoryFilter.value,
        stockFilter: stockFilter.value,
        showStock: props.canUseInventory,
        canCreate: can('inventory.products.create'),
    })
)

function saveBatchChanges() {
    saveEditedBatch(closeProductBatchesModal)
}

function handleInventoryToolbarFilter({ key, value }) {
    if (key === 'productDepartmentFilter') {
        productDepartmentFilter.value = value

        if (value.length) {
            categoryFilter.value = categoryFilter.value.filter((categoryId) => {
                const category = categories.value.find((item) => String(item.id) === String(categoryId))

                return category && value.map(String).includes(String(category.product_department_id))
            })
        }
    }

    if (key === 'categoryFilter') categoryFilter.value = value
    if (key === 'stockFilter') stockFilter.value = value

    // Después de filtrar, el operador puede seguir escaneando o escribiendo
    // sin perder la búsqueda que ya había capturado.
    nextTick(focusInventorySearch)
}

function handleInventoryToolbarAction(action) {
    if (action !== 'create' || !can('inventory.products.create')) return

    selectedProduct.value = null
    showProductCreateModal.value = true
}

function handleInventoryAction({ action, row }) {
    if (action === 'view' && can('inventory.products.view')) {
        openProductView(row)
    }

    if (action === 'delete' && can('inventory.products.delete')) {
        deleteInventoryProduct(row)
    }

    if (action === 'edit' && can('inventory.products.update')) {
        openProductEdit(row)
    }

    if (action === 'entry' && can('inventory.branches.stock-in')) {
        openEntryModal(row)
    }

    if (action === 'exit' && can('inventory.branches.stock-out')) {
        openExitModal(row)
    }

    if (action === 'batches' && can('inventory.branches.batches.update')) {
        openProductBatchesModal(row)
    }

    if (action === 'movements' && can('inventory.branches.view')) {
        openMovementsModal(row)
    }
}

function openProductView(row) {
    openProductSnapshot(row, 'view')
}

function deleteInventoryProduct(row) {
    const product = buildCatalogProduct(row)

    if (product) deleteProduct(product)
}

async function openProductEdit(row) {
    openProductSnapshot(row, 'edit')
}

async function openProductSnapshot(row, mode) {
    if (!row?.product_id || !currentBranch.value?.slug) return

    try {
        const { data } = await window.axios.get(
            route('inventory.branches.products.snapshot', {
                branch: currentBranch.value.slug,
                productId: row.product_id,
            }),
        )

        if (!data?.product) {
            ErrorAlert({
                title: 'Producto no disponible',
                message: 'El producto ya no está activo en esta sucursal.',
            })
            return
        }

        selectedProduct.value = data.product
        if (mode === 'view') showProductViewModal.value = true
        if (mode === 'edit') showProductEditModal.value = true
    } catch {
        ErrorAlert({
            title: 'No se pudo abrir el producto',
            message: 'Actualiza la página y vuelve a intentarlo.',
        })
    }
}

function buildCatalogProduct(row) {
    const product = row?.raw?.product

    if (!product || !currentBranch.value?.slug) return null

    return {
        ...product,
        id: row.product_id,
        branch_product_id: row.id,
        branch_id: currentBranch.value.id,
        branch_slug: currentBranch.value.slug,
        branch_name: currentBranch.value.name,
        branch: currentBranch.value,
        branch_ids: [currentBranch.value.id],
        barcodes: row.barcodes,
        min_stock: row.minStock,
        price: row.price,
        sale_price: product.sale_price,
        sale_price_per_piece: product.sale_price_per_piece ?? product.sale_price,
        sale_price_per_box: product.sale_price_per_box,
        product_department_id: product.category?.product_department_id,
        product_department_name: product.category?.product_department?.name ?? 'Sin departamento',
        category_id: product.category_id,
        category_name: product.category?.name ?? 'Sin categoría',
        image: product.image
            ? route('inventory.products.image', { product: row.product_id })
            : null,
        record_version: product.updated_at,
        catalog_version: product.catalog_version,
    }
}

function closeProductViewModal() {
    showProductViewModal.value = false
    selectedProduct.value = null
}

function closeProductCreateModal() {
    showProductCreateModal.value = false
    selectedProduct.value = null
}

function closeProductEditModal() {
    showProductEditModal.value = false
    selectedProduct.value = null
}
</script>
<template>
    <PageLayout>
        <template #toolbar>
            <div class="space-y-5">
                <InventoryStatsCards v-if="canUseInventory" :alerts="alerts" @open-alert="openAlertModal" />

                <GlobalToolbar ref="inventoryToolbar" v-bind="inventoryToolbarConfig" :search="search" :records-per-page="recordsToShow"
                    @keydown="handleSearchEnter($event, inventoryToolbarConfig.searchPlaceholder)"
                    select-search-on-enter
                    :filtered-records="filteredProducts.length" :total-records="visualProducts.length"
                    @update:search="search = $event" @update:filter="handleInventoryToolbarFilter"
                    @update:records-per-page="recordsToShow = $event" @action="handleInventoryToolbarAction" />
            </div>
        </template>

        <GlobalTable :items="filteredProducts" v-bind="inventoryTableConfig" :columns="inventoryColumns" :pagination="inventoryPagination"
            @page-change="goToPage" @action="handleInventoryAction" />

        <StockEntryModal v-if="showEntryModal && liveSelectedMovementProduct && can('inventory.branches.stock-in')"
            :product="liveSelectedMovementProduct" :branches="branchesDB" :current-branch="selectedMovementBranch"
            @close="closeEntryModal" />

        <StockExitModal v-if="showExitModal && liveSelectedMovementProduct && can('inventory.branches.stock-out')"
            :product="liveSelectedMovementProduct" @close="closeExitModal" />

        <ProductMovementsModal
            v-if="showMovementsModal && liveSelectedMovementsProduct && can('inventory.branches.view')"
            :product="liveSelectedMovementsProduct" :loading="movementLoading" :error="historyError" @retry="loadMovementPage()" @load-page="loadMovementPage" @close="closeMovementsModal" />

        <ProductBatchesModal
            v-if="showProductBatchesModal && liveSelectedBatchesProduct && can('inventory.branches.batches.update')"
            :product="liveSelectedBatchesProduct" :selected-batch="liveSelectedBatch" :form="batchAdjustmentForm"
            :frontend-errors="batchAdjustmentErrors" :total-errors="batchAdjustmentTotalErrors"
            :processing="batchAdjustmentProcessing"
            :is-seasonal="batchAdjustmentIsSeasonal" :calculated-quantity="batchAdjustmentCalculatedQuantity"
            :adjustment-text="batchAdjustmentText" :quantity-result-color="batchAdjustmentQuantityResultColor"
            :set-adjustment-type="setBatchAdjustmentType"
            :validate-field="validateBatchAdjustmentField" @select-batch="adjustBatch" @save="saveBatchChanges"
            @close="closeProductBatchesModal" />

        <ProductModal
            v-if="showProductViewModal && selectedProduct && can('inventory.products.view')"
            mode="view"
            :product="selectedProduct"
            :categoriesDB="catalogCategoriesDB"
            :productDepartmentsDB="catalogProductDepartmentsDB"
            :branchesDB="branchesDB"
            :branch="currentBranch"
            :can-manage-pricing="false"
            @close="closeProductViewModal" />

        <ProductModal
            v-if="showProductCreateModal && can('inventory.products.create')"
            mode="create"
            :product="selectedProduct"
            :categoriesDB="catalogCategoriesDB"
            :productDepartmentsDB="catalogProductDepartmentsDB"
            :branchesDB="branchesDB"
            :branch="currentBranch"
            :can-manage-pricing="canManagePricing"
            @close="closeProductCreateModal" />

        <ProductModal
            v-if="showProductEditModal && selectedProduct && can('inventory.products.update')"
            mode="edit"
            :product="selectedProduct"
            :categoriesDB="catalogCategoriesDB"
            :productDepartmentsDB="catalogProductDepartmentsDB"
            :branchesDB="branchesDB"
            :branch="currentBranch"
            :can-manage-pricing="canManagePricing"
            @close="closeProductEditModal" />

        <InventoryAlertsModal v-if="showAlertModal" :title="selectedAlertTitle" :type="selectedAlertType"
            :batches="selectedAlertBatches" @close="closeAlertModal" />

        <EditBranchProductConfigModal
            v-if="showConfigModal && liveSelectedConfigProduct && can('inventory.branches.config.update')"
            :product="liveSelectedConfigProduct" @close="closeConfigModal" />
    </PageLayout>
</template>
