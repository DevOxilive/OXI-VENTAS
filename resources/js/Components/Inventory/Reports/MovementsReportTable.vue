<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { movementsReportTableConfig } from '@/config/TableConfigs/movementsReportTableConfig'

defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <GlobalTable :items="rows" :columns="movementsReportTableConfig.columns"
        :actions="movementsReportTableConfig.actions"
        :mobile-card-header-field="movementsReportTableConfig.mobileCardHeaderField"
        :no-data-message="movementsReportTableConfig.noDataMessage" :row-key="movementsReportTableConfig.rowKey" />
</template>