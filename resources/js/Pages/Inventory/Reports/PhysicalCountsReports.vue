<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import AuditReportConfigurationTable from '@/Components/Audits/PhysicalCounts/AuditReportConfigurationTable.vue'
import { usePhysicalCountReports } from '@/Composables/Audits/usePhysicalCountReports'
import { computed, ref } from 'vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    branch: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    audits: { type: Array, default: () => [] },
    auditPagination: { type: Object, default: null },
    categories: { type: Array, default: () => [] },
    reportRows: { type: Array, default: () => [] },
    reportPagination: { type: Object, default: null },
    userSummary: { type: Array, default: () => [] },
    categorySummary: { type: Array, default: () => [] },
    branchSummary: { type: Array, default: () => [] },
    auditSummary: { type: Array, default: () => [] },
    roundSummary: { type: Array, default: () => [] },
    topDifferences: { type: Array, default: () => [] },
})

const {
    form,
    toolbarConfig,
    auditConfigurations,
    backToReportsCenter,
    updateSearch,
    updateRecordsPerPage,
    changePage,
    exportReport,
} = usePhysicalCountReports(props)

const removedAuditIds = ref([])
const visibleAudits = computed(() =>
    props.audits.filter((audit) => !removedAuditIds.value.includes(Number(audit.id))),
)

function removeAuditFromTable(auditId) {
    const normalizedId = Number(auditId)
    if (!removedAuditIds.value.includes(normalizedId)) {
        removedAuditIds.value = [...removedAuditIds.value, normalizedId]
    }
}
</script>

<template>
    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
                @back="backToReportsCenter"
                @update:search="updateSearch"
                @update:records-per-page="updateRecordsPerPage"
            />
        </template>

        <section class="space-y-5">
            <AuditReportConfigurationTable
                v-model="auditConfigurations"
                :audits="visibleAudits"
                :categories="categories"
                :pagination="auditPagination"
                @export="({ type, auditId }) => exportReport(type, auditId)"
                @remove="removeAuditFromTable"
                @page-change="changePage"
            />
        </section>
    </PageLayout>
</template>
