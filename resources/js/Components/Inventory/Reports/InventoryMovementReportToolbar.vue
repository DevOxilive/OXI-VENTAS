<script setup>
import { computed } from 'vue'

import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import { getInventoryMovementReportToolbarConfig } from '@/config/ToolbarConfigs/inventoryMovementReportToolbarConfig'

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
    users: {
        type: Array,
        default: () => [],
    },
    movementTypes: {
        type: Array,
        default: () => [],
    },
    movementReasons: {
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
    getInventoryMovementReportToolbarConfig({
        branch: props.branch,
        filters: props.filters,
        categories: props.categories,
        products: props.products,
        users: props.users,
        movementTypes: props.movementTypes,
        movementReasons: props.movementReasons,
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
