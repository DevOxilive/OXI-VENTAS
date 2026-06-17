<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { expirationsReportTableConfig } from '@/config/TableConfigs/expirationsReportTableConfig'

defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <GlobalTable :items="rows" :columns="expirationsReportTableConfig.columns"
        :actions="expirationsReportTableConfig.actions"
        :mobile-card-header-field="expirationsReportTableConfig.mobileCardHeaderField"
        :no-data-message="expirationsReportTableConfig.noDataMessage" :row-key="expirationsReportTableConfig.rowKey" />
</template>