<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { inventoryReportTableConfig } from '@/config/TableConfigs/inventoryReportTableConfig'

defineProps({
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
})

defineEmits([
    'page-change',
    'action',
    'row-click',
])
</script>

<template>
    <GlobalTable :items="rows" v-bind="inventoryReportTableConfig" :pagination="pagination" :loading="loading"
        @page-change="$emit('page-change', $event)" @action="$emit('action', $event)"
        @row-click="$emit('row-click', $event)" />
</template>