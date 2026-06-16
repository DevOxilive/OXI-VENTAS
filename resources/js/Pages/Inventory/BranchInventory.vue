<script setup>
import { computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { usePermissions } from '@/Composables/usePermissions'
import { useBranchInventory } from '@/Composables/Inventory/useBranchInventory'
import StockExitModal from '@/Components/Inventory/BranchProducts/StockExitModal.vue'
import StockEntryModal from '@/Components/Inventory/BranchProducts/StockEntryModal.vue'
import InventoryToolbar from '@/Components/Inventory/BranchProducts/InventoryToolbar.vue'
import ProductBatchesModal from '@/Components/Inventory/BranchProducts/ProductBatchesModal.vue'
import InventoryStatsCards from '@/Components/Inventory/BranchProducts/InventoryStatsCards.vue'
import InventoryAlertsModal from '@/Components/Inventory/BranchProducts/InventoryAlertsModal.vue'
import ProductMovementsModal from '@/Components/Inventory/BranchProducts/ProductMovementsModal.vue'
import EditBranchProductConfigModal from '@/Components/Inventory/BranchProducts/EditBranchProductConfigModal.vue'


defineOptions({ layout: AdminLayout })


const props = defineProps({
    branchProductsDB: {
        type: [Object, Array],
        default: () => ({ data: [] }),
    },
    productsDB: {
        type: Array,
        default: () => [],
    },
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
    subcategoriesDB: {
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
            category: '',
            subcategory: '',
            stock: '',
            status: '',
            expiration_status: '',
            inactive_candidate: '',
            per_page: 50,
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
})

const inventoryColumns = [
    {
        key: 'code',
        label: 'Código',
        format: 'text',
        fallback: 'Sin código',
        mobileLabel: 'Código',
    },
    {
        key: 'name',
        label: 'Producto',
        format: 'text',
        mobileSecondary: true,
    },
    {
        key: 'category_name',
        label: 'Categoría',
        format: 'text',
        fallback: 'Sin categoría',
        mobileLabel: 'Categoría',
    },
    {
        key: 'stockLabel',
        label: 'Stock',
        format: 'text',
        fallback: '0',
        mobileLabel: 'Stock',
        subKey: 'minStockLabel',
    },
    {
        key: 'status',
        label: 'Estado',
        format: 'badge',
        formatOptions: {
            statusMap: {
                Disponible: 'green',
                'Stock bajo': 'amber',
                Agotado: 'red',
            },
        },
        mobileBadge: true,
    },
]

const inventoryActions = computed(() => [
    {
        id: 'entry',
        label: 'Entrada',
        icon: 'add',
        variant: 'green',
        hidden: () => !can('inventory.branches.create'),
        mobile: 'button',
    },
    {
        id: 'exit',
        label: 'Salida',
        icon: 'remove',
        variant: 'red',
        hidden: () => !can('inventory.branches.update'),
        mobile: 'button',
    },
    {
        id: 'batches',
        label: 'Lotes',
        icon: 'edit',
        variant: 'blue',
        hidden: (product) =>
            !can('inventory.branches.update') || !product.batches?.length,
        mobile: 'button',
    },
    {
        id: 'movements',
        label: 'Historial',
        icon: 'history',
        variant: 'slate',
        hidden: () => !can('inventory.branches.view'),
        mobile: 'button',
    },
])

function handleInventoryAction({ action, row }) {
    if (action === 'entry' && can('inventory.branches.create')) {
        openEntryModal(row)
    }

    if (action === 'exit' && can('inventory.branches.update')) {
        openExitModal(row)
    }

    if (action === 'batches' && can('inventory.branches.update')) {
        openProductBatchesModal(row)
    }

    if (action === 'movements' && can('inventory.branches.view')) {
        openMovementsModal(row)
    }
}

const { can } = usePermissions()

const {
    categories,
    subcategories,
    currentBranch,

    showEntryModal,
    showExitModal,
    showMovementsModal,
    showConfigModal,
    showBatchAdjustmentModal,

    liveSelectedMovementProduct,
    liveSelectedMovementsProduct,
    liveSelectedConfigProduct,
    liveSelectedBatch,

    closeConfigModal,
    closeBatchAdjustmentModal,
    adjustBatch,

    search,
    categoryFilter,
    subcategoryFilter,
    stockFilter,
    statusFilter,
    expirationStatusFilter,
    inactiveCandidateFilter,
    recordsToShow,

    paginationLinks,
    hasPagination,
    visualProducts,
    filteredProducts,
    stats,
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

    viewProduct,
    editProduct,
    deleteProduct,

    showProductBatchesModal,
    liveSelectedBatchesProduct,
    openProductBatchesModal,
    closeProductBatchesModal,
    openBatchAdjustmentFromList,

    batchAdjustmentProcessing,
    batchAdjustmentUsesLot,
    batchAdjustmentForm,
    batchAdjustmentErrors,
    batchAdjustmentTotalErrors,
    batchAdjustmentIsSeasonal,
    batchAdjustmentCalculatedQuantity,
    batchAdjustmentText,
    batchAdjustmentQuantityResultColor,
    toggleBatchAdjustmentLot,
    setBatchAdjustmentType,
    validateBatchAdjustmentField,
    saveEditedBatch,
} = useBranchInventory(props)

</script>

<template>
    <div class="space-y-5">
        <header class="flex flex-col gap-1">
            <h1 class="text-2xl md:text-3xl font-black text-slate-800">
                Inventario
                <span v-if="currentBranch" class="text-slate-500">
                    - {{ currentBranch.name }}
                </span>
            </h1>

            <p class="text-sm text-slate-500">
                Consulta y movimientos por sucursal
            </p>
        </header>

        <InventoryStatsCards :stats="stats" :alerts="alerts" @open-alert="openAlertModal" />

        <InventoryToolbar :filtered-products="filteredProducts" :products-db="visualProducts"
            :records-to-show="recordsToShow" :categories="categories" :subcategories="subcategories" :search="search"
            :category-filter="categoryFilter" :subcategory-filter="subcategoryFilter" :stock-filter="stockFilter"
            :status-filter="statusFilter" :expiration-status-filter="expirationStatusFilter"
            :inactive-candidate-filter="inactiveCandidateFilter" @update:recordsToShow="recordsToShow = $event"
            @update:search="search = $event" @update:categoryFilter="categoryFilter = $event"
            @update:subcategoryFilter="subcategoryFilter = $event" @update:stockFilter="stockFilter = $event"
            @update:statusFilter="statusFilter = $event"
            @update:expirationStatusFilter="expirationStatusFilter = $event"
            @update:inactiveCandidateFilter="inactiveCandidateFilter = $event" />

        <GlobalTable :items="filteredProducts" :columns="inventoryColumns" :actions="inventoryActions" row-key="id"
            mobile-card-header-field="name" no-data-message="No se encontraron productos."
            @action="handleInventoryAction" />

        <nav v-if="hasPagination" class="flex flex-wrap items-center justify-center gap-2 pt-1">
            <button v-for="link in paginationLinks" :key="link.label" type="button" :disabled="!link.url"
                class="px-3 py-2 rounded-lg text-sm border transition disabled:opacity-40 disabled:cursor-not-allowed"
                :class="link.active
                    ? 'bg-slate-900 text-white border-slate-900'
                    : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-50'
                    " @click="goToPage(link.url)" v-html="link.label" />
        </nav>

        <StockEntryModal v-if="showEntryModal && liveSelectedMovementProduct && can('inventory.branches.create')"
            :product="liveSelectedMovementProduct" @close="closeEntryModal" />

        <StockExitModal v-if="showExitModal && liveSelectedMovementProduct && can('inventory.branches.update')"
            :product="liveSelectedMovementProduct" @close="closeExitModal" />

        <ProductMovementsModal
            v-if="showMovementsModal && liveSelectedMovementsProduct && can('inventory.branches.view')"
            :product="liveSelectedMovementsProduct" @close="closeMovementsModal" />

        <ProductBatchesModal
            v-if="showProductBatchesModal && liveSelectedBatchesProduct && can('inventory.branches.update')"
            :product="liveSelectedBatchesProduct" @close="closeProductBatchesModal"
            @adjust-batch="openBatchAdjustmentFromList" />

        <ProductBatchesModal v-if="showProductBatchesModal && liveSelectedBatchesProduct"
            :product="liveSelectedBatchesProduct" :selected-batch="liveSelectedBatch" :form="batchAdjustmentForm"
            :frontend-errors="batchAdjustmentErrors" :total-errors="batchAdjustmentTotalErrors"
            :processing="batchAdjustmentProcessing" :uses-lot="batchAdjustmentUsesLot"
            :is-seasonal="batchAdjustmentIsSeasonal" :calculated-quantity="batchAdjustmentCalculatedQuantity"
            :adjustment-text="batchAdjustmentText" :quantity-result-color="batchAdjustmentQuantityResultColor"
            :toggle-lot="toggleBatchAdjustmentLot" :set-adjustment-type="setBatchAdjustmentType"
            :validate-field="validateBatchAdjustmentField" @select-batch="adjustBatch" @save="saveEditedBatch"
            @close="closeProductBatchesModal" />

        <InventoryAlertsModal v-if="showAlertModal" :title="selectedAlertTitle" :type="selectedAlertType"
            :batches="selectedAlertBatches" @close="closeAlertModal" />

        <EditBranchProductConfigModal
            v-if="showConfigModal && liveSelectedConfigProduct && can('inventory.branches.update')"
            :product="liveSelectedConfigProduct" @close="closeConfigModal" />
    </div>
</template>