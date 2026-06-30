<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { productTableConfig } from '@/config/TableConfigs/productTableConfig'

defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    pagination: {
        type: [Object, Array],
        default: null,
    },
    loading: Boolean,
})

defineEmits([
    'page-change',
    'action',
    'row-click',
])
</script>

<template>
    <GlobalTable :items="items" v-bind="productTableConfig" :pagination="pagination" :loading="loading"
        @page-change="$emit('page-change', $event)" @action="$emit('action', $event)"
        @row-click="$emit('row-click', $event)" />
</template>
