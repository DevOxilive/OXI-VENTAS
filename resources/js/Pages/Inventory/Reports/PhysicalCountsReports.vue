<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { usePhysicalCountReports } from '@/Composables/Audits/usePhysicalCountReports'
import { physicalCountReportSummaryTableConfig } from '@/config/TableConfigs/physicalCountReportSummaryTableConfig'
import { physicalCountReportDetailTableConfig } from '@/config/TableConfigs/physicalCountReportDetailTableConfig'
import { physicalCountReportUsersTableConfig } from '@/config/TableConfigs/physicalCountReportUsersTableConfig'
import { physicalCountReportCategoriesTableConfig } from '@/config/TableConfigs/physicalCountReportCategoriesTableConfig'
import { physicalCountReportDifferencesTableConfig } from '@/config/TableConfigs/physicalCountReportDifferencesTableConfig'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    branch: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    audits: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    reportRows: { type: Array, default: () => [] },
    reportPagination: { type: Object, default: null },
    userSummary: { type: Array, default: () => [] },
    categorySummary: { type: Array, default: () => [] },
    topDifferences: { type: Array, default: () => [] },
})

const {
    form,
    toolbarConfig,
    summaryTableItems,
    reportTableItems,
    differencesTableItems,
    backToReportsCenter,
    updateSearch,
    updateFilter,
    handleToolbarAction,
    handlePageChange,
} = usePhysicalCountReports(props)
</script>

<template>
    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
                :active-tab="form.report_type"
                @back="backToReportsCenter"
                @update:search="updateSearch"
                @update:filter="updateFilter"
                @update:active-tab="form.report_type = $event"
                @action="handleToolbarAction"
            />
        </template>

        <section class="space-y-5">
            <template v-if="form.report_type === 'summary'">
                <GlobalTable
                    :items="summaryTableItems"
                    v-bind="physicalCountReportSummaryTableConfig"
                    row-key="id"
                    :show-pagination="false"
                />
            </template>

            <template v-else-if="form.report_type === 'detail'">
                <GlobalTable
                    :items="reportTableItems"
                    v-bind="physicalCountReportDetailTableConfig"
                    :pagination="reportPagination"
                    row-key="id"
                    @page-change="handlePageChange"
                />
            </template>

            <template v-else-if="form.report_type === 'users'">
                <GlobalTable
                    :items="userSummary"
                    v-bind="physicalCountReportUsersTableConfig"
                    row-key="user_name"
                    :show-pagination="false"
                />
            </template>

            <template v-else-if="form.report_type === 'categories'">
                <GlobalTable
                    :items="categorySummary"
                    v-bind="physicalCountReportCategoriesTableConfig"
                    row-key="category_name"
                    :show-pagination="false"
                />
            </template>

            <template v-else-if="form.report_type === 'differences'">
                <GlobalTable
                    :items="differencesTableItems"
                    v-bind="physicalCountReportDifferencesTableConfig"
                    row-key="id"
                    :show-pagination="false"
                />
            </template>
        </section>
    </PageLayout>
</template>
