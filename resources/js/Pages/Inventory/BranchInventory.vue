<script setup>
import { computed } from 'vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import PageLayout from '@/Layouts/PageLayout.vue'

import { usePermissions } from '@/Composables/usePermissions'
import { useBranchInventory } from '@/Composables/Inventory/useBranchInventory'

import { inventoryTableConfig } from '@/config/TableConfigs/inventoryTableConfig'
import { getInventoryToolbarConfig } from '@/config/ToolbarConfigs/inventoryToolbarConfig'

import InventoryStatsCards from '@/Components/Inventory/BranchProducts/InventoryStatsCards.vue'
import InventoryAlertsModal from '@/Components/Inventory/BranchProducts/InventoryAlertsModal.vue'
import StockEntryModal from '@/Components/Inventory/BranchProducts/StockEntryModal.vue'
import StockExitModal from '@/Components/Inventory/BranchProducts/StockExitModal.vue'
import ProductMovementsModal from '@/Components/Inventory/BranchProducts/ProductMovementsModal.vue'
import ProductBatchesModal from '@/Components/Inventory/BranchProducts/ProductBatchesModal.vue'
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

const { can } = usePermissions()

const {
    categories,
    currentBranch,

    showEntryModal,
    showExitModal,
    showMovementsModal,
    showConfigModal,

    liveSelectedMovementProduct,
    liveSelectedMovementsProduct,
    liveSelectedConfigProduct,
    liveSelectedBatch,

    closeConfigModal,
    adjustBatch,

    search,
    categoryFilter,
    stockFilter,
    statusFilter,
    expirationStatusFilter,
    inactiveCandidateFilter,
    recordsToShow,

    paginationLinks,
    hasPagination,
    visualProducts,
    filteredProducts,
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
} = useBranchInventory(props)

const inventoryToolbarConfig = computed(() =>
    getInventoryToolbarConfig({
        branch: currentBranch.value,
        categories: categories.value,
        categoryFilter: categoryFilter.value,
        stockFilter: stockFilter.value,
        statusFilter: statusFilter.value,
        expirationStatusFilter: expirationStatusFilter.value,
        inactiveCandidateFilter: inactiveCandidateFilter.value,
    })
)

function handleInventoryToolbarFilter({ key, value }) {
    if (key === 'categoryFilter') categoryFilter.value = value
    if (key === 'stockFilter') stockFilter.value = value
    if (key === 'statusFilter') statusFilter.value = value
    if (key === 'expirationStatusFilter') expirationStatusFilter.value = value
    if (key === 'inactiveCandidateFilter') inactiveCandidateFilter.value = value
}

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
</script>
<template>
    <PageLayout>
        <template #toolbar>
            <div class="space-y-5">
                <InventoryStatsCards :alerts="alerts" @open-alert="openAlertModal" />

                <GlobalToolbar v-bind="inventoryToolbarConfig" :search="search" :records-per-page="recordsToShow"
                    :filtered-records="filteredProducts.length" :total-records="visualProducts.length"
                    @update:search="search = $event" @update:filter="handleInventoryToolbarFilter"
                    @update:records-per-page="recordsToShow = $event" />
            </div>
        </template>

        <GlobalTable :items="filteredProducts" v-bind="inventoryTableConfig" :pagination="branchProductsDB"
            @page-change="goToPage" @action="handleInventoryAction" />

        <StockEntryModal v-if="showEntryModal && liveSelectedMovementProduct && can('inventory.branches.create')"
            :product="liveSelectedMovementProduct" :branches="branchesDB" :current-branch="currentBranch"
            @close="closeEntryModal" />

        <StockExitModal v-if="showExitModal && liveSelectedMovementProduct && can('inventory.branches.update')"
            :product="liveSelectedMovementProduct" @close="closeExitModal" />

        <ProductMovementsModal
            v-if="showMovementsModal && liveSelectedMovementsProduct && can('inventory.branches.view')"
            :product="liveSelectedMovementsProduct" @close="closeMovementsModal" />

        <ProductBatchesModal v-if="showProductBatchesModal && liveSelectedBatchesProduct"
            :product="liveSelectedBatchesProduct" :selected-batch="liveSelectedBatch" :form="batchAdjustmentForm"
            :frontend-errors="batchAdjustmentErrors" :total-errors="batchAdjustmentTotalErrors"
            :processing="batchAdjustmentProcessing"
            :is-seasonal="batchAdjustmentIsSeasonal" :calculated-quantity="batchAdjustmentCalculatedQuantity"
            :adjustment-text="batchAdjustmentText" :quantity-result-color="batchAdjustmentQuantityResultColor"
            :set-adjustment-type="setBatchAdjustmentType"
            :validate-field="validateBatchAdjustmentField" @select-batch="adjustBatch" @save="saveEditedBatch"
            @close="closeProductBatchesModal" />

        <InventoryAlertsModal v-if="showAlertModal" :title="selectedAlertTitle" :type="selectedAlertType"
            :batches="selectedAlertBatches" @close="closeAlertModal" />

        <EditBranchProductConfigModal
            v-if="showConfigModal && liveSelectedConfigProduct && can('inventory.branches.update')"
            :product="liveSelectedConfigProduct" @close="closeConfigModal" />
    </PageLayout>
</template>
