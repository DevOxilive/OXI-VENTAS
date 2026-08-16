<script setup>
import { computed } from 'vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { inventoryReportTableConfig } from '@/config/TableConfigs/inventoryReportTableConfig'
import { inventoryMovementReportTableConfig } from '@/config/TableConfigs/inventoryMovementReportTableConfig'

const props = defineProps({
    rows: {
        type: Array,
        default: () => [],
    },

    pagination: {
        type: [Object, Array],
        default: null,
    },

    loading: {
        type: Boolean,
        default: false,
    },

    reportType: {
        type: String,
        default: 'dashboard',
    },
})

defineEmits([
    'page-change',
    'action',
    'row-click',
])

const tableConfig = computed(() => (
    props.reportType === 'movements'
        ? inventoryMovementReportTableConfig
        : inventoryReportTableConfig
))
</script>

<template>
    <GlobalTable :items="rows" v-bind="tableConfig" :pagination="pagination" :loading="loading"
        @page-change="$emit('page-change', $event)" @action="$emit('action', $event)"
        @row-click="$emit('row-click', $event)" />
</template>
