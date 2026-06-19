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

            <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm md:p-5">
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black text-slate-900">
                            Historial de reportes generados
                        </h2>

                        <p class="text-sm text-slate-500">
                            Los archivos Excel y PDF quedaran disponibles aqui cuando activemos la generacion.
                        </p>
                    </div>

                    <span class="material-symbols-outlined hidden rounded-lg bg-slate-100 p-2 text-slate-500 sm:inline-flex">
                        history
                    </span>
                </div>

                <div
                    v-if="!reportHistory.length"
                    class="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-6 text-center"
                >
                    <p class="text-sm font-black text-slate-500">
                        Aun no hay reportes generados.
                    </p>

                    <p class="mt-1 text-xs text-slate-400">
                        Cuando generes un PDF o Excel, aparecera en este historial.
                    </p>
                </div>
            </section>
        </section>
    </PageLayout>
</template>
