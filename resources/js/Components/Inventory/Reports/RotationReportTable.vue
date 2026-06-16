<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { rotationReportTableConfig } from '@/config/TableConfigs/rotationReportTableConfig'

defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <GlobalTable :items="rows" :columns="rotationReportTableConfig.columns" :actions="rotationReportTableConfig.actions"
        :mobile-card-header-field="rotationReportTableConfig.mobileCardHeaderField"
        :no-data-message="rotationReportTableConfig.noDataMessage" :row-key="rotationReportTableConfig.rowKey" />
</template>