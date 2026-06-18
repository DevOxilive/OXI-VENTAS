<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { employeeTableConfig } from '@/config/TableConfigs/employeeTableConfig'

defineProps({
    employees: {
        type: Array,
        default: () => [],
    },
    pagination: {
        type: Object,
        default: () => ({}),
    },
})

defineEmits([
    'page-change',
    'action',
])
</script>

<template>
    <GlobalTable :items="employees" v-bind="employeeTableConfig" :pagination="pagination"
        @page-change="$emit('page-change', $event)" @action="$emit('action', $event)" />
</template>