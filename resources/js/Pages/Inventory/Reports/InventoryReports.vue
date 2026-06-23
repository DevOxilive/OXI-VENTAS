<script setup>
import { Head } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import InventoryReportToolbar from '@/Components/Inventory/Reports/InventoryReportToolbar.vue'
import ReportTable from '@/Components/Inventory/Reports/ReportTable.vue'
import { useInventoryReport } from '@/Composables/Inventory/useInventoryReport'

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
    reports: {
        type: Object,
        default: () => ({}),
    },
    reportHistory: {
        type: Array,
        default: () => [],
    },
    activeReport: {
        type: String,
        default: 'dashboard',
    },
})

const {
    filtersState,
    pageTitle,
    categoryOptions,
    productOptions,
    tableRows,
    tablePagination,
    backToReportsCenter,
    updateSearch,
    updateFilter,
    reloadReport,
    handleToolbarAction,
} = useInventoryReport(props)
</script>

<template>
    <Head :title="pageTitle" />

    <PageLayout>
        <template #toolbar>
            <InventoryReportToolbar
                :branch="currentBranch"
                :filters="filtersState"
                :categories="categoryOptions"
                :products="productOptions"
                @back="backToReportsCenter"
                @update:search="updateSearch"
                @update:filter="updateFilter"
                @action="handleToolbarAction"
            />
        </template>

        <section class="space-y-5">
            <ReportTable
                :rows="tableRows"
                :report-type="filtersState.reportType"
                :pagination="tablePagination"
                @page-change="reloadReport"
            />
        </section>
    </PageLayout>
</template>
