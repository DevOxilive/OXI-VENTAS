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
})

defineEmits([
    'back',
    'update:search',
    'update:filter',
    'action',
])

const toolbarConfig = computed(() =>
    getInventoryReportToolbarConfig({
        branch: props.branch,
        filters: props.filters,
        categories: props.categories,
    }),
)
</script>

<template>
    <GlobalToolbar
        v-bind="toolbarConfig"
        :search="filters.search"
        @back="$emit('back')"
        @update:search="$emit('update:search', $event)"
        @update:filter="$emit('update:filter', $event)"
        @action="$emit('action', $event)"
    />
</template>
