<script setup>
import { computed } from 'vue'

import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import { getInventoryMovementReportToolbarConfig } from '@/config/ToolbarConfigs/inventoryMovementReportToolbarConfig'

const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    categories: {
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
    'update:records-per-page',
    'action',
])

const toolbarConfig = computed(() =>
    getInventoryMovementReportToolbarConfig({
        filters: props.filters,
        categories: props.categories,
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
        :records-per-page="filters.perPage"
        @back="$emit('back')"
        @update:search="$emit('update:search', $event)"
        @update:filter="$emit('update:filter', $event)"
        @update:records-per-page="$emit('update:records-per-page', $event)"
        @action="$emit('action', $event)"
    />
</template>
