<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { getUsersTableConfig } from '@/config/ToolbarConfigs/usersTableConfig';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    pagination: {
        type: Object,
        default: () => ({}),
    },
    viewMode: {
        type: String,
        required: true,
    },
    can: {
        type: Function,
        required: true,
    },
})

defineEmits([
    'page-change',
    'action',
    'row-click',
])
</script>

<template>
    <GlobalTable :items="items" v-bind="getUsersTableConfig({
        viewMode: props.viewMode,
        can: props.can,
    })" :pagination="pagination" @page-change="$emit('page-change', $event)" @action="$emit('action', $event)"
        @row-click="$emit('row-click', $event)" />
</template>