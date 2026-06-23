<script setup>
import { Head } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import InventoryMovementReportToolbar from '@/Components/Inventory/Reports/InventoryMovementReportToolbar.vue'
import MovementReportTable from '@/Components/Inventory/Reports/MovementReportTable.vue'
import { useInventoryMovementReport } from '@/Composables/Inventory/useInventoryMovementReport'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    currentBranch: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    catalogs: {
        type: Object,
        default: () => ({}),
    },
    movements: {
        type: [Array, Object],
        default: () => [],
    },
})

const {
    filtersState,
    pageTitle,
    categoryOptions,
    productOptions,
    userOptions,
    movementTypeOptions,
    movementReasonOptions,
    tableRows,
    tablePagination,
    backToReportsCenter,
    updateSearch,
    updateFilter,
    reloadReport,
    handleToolbarAction,
} = useInventoryMovementReport(props)
</script>

<template>
    <Head :title="pageTitle" />

    <PageLayout>
        <template #toolbar>
            <InventoryMovementReportToolbar
                :branch="currentBranch"
                :filters="filtersState"
                :categories="categoryOptions"
                :products="productOptions"
                :users="userOptions"
                :movement-types="movementTypeOptions"
                :movement-reasons="movementReasonOptions"
                @back="backToReportsCenter"
                @update:search="updateSearch"
                @update:filter="updateFilter"
                @action="handleToolbarAction"
            />
        </template>

        <MovementReportTable
            :rows="tableRows"
            :pagination="tablePagination"
            @page-change="reloadReport"
        />
    </PageLayout>
</template>
