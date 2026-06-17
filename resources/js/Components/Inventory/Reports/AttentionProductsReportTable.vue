<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { attentionProductsReportTableConfig } from '@/config/TableConfigs/attentionProductsReportTableConfig'

defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <GlobalTable :items="rows" :columns="attentionProductsReportTableConfig.columns"
        :actions="attentionProductsReportTableConfig.actions"
        :mobile-card-header-field="attentionProductsReportTableConfig.mobileCardHeaderField"
        :no-data-message="attentionProductsReportTableConfig.noDataMessage"
        :row-key="attentionProductsReportTableConfig.rowKey" />
</template>