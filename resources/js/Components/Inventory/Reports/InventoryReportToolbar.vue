<script setup>
import { computed } from 'vue'

import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import { getInventoryReportToolbarConfig } from '@/config/ToolbarConfigs/inventoryReportToolbarConfig'

const props = defineProps({
    branch: {
        type: Object,
        default: null,
    },
    filters: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
    products: {
        type: Array,
        default: () => [],
    },
})

defineEmits([
    'back',
    'update:search',
    'update:filter',
    'update:records-per-page',
    'action',
])

const toolbarConfig = computed(() =>
    getInventoryReportToolbarConfig({
        branch: props.branch,
        filters: props.filters,
        categories: props.categories,
        products: props.products,
    }),
)
</script>

<template>
    <GlobalToolbar
        v-bind="toolbarConfig"
        :search="filters.search"
        :records-per-page="filters.perPage"
        @back="$emit('back')"
        @update:search="$emit('update:search', $event)"
        @update:filter="$emit('update:filter', $event)"
        @update:records-per-page="$emit('update:records-per-page', $event)"
        @action="$emit('action', $event)"
    />
</template>
