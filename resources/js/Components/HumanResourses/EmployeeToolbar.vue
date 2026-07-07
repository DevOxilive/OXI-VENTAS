<script setup>
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import { getEmployeeToolbarConfig } from '@/config/ToolbarConfigs/employeeToolbarConfig'

defineProps({
    search: String,
    recordsPerPage: Number,
    filteredRecords: Number,
    totalRecords: Number,
    activeFilters: {
        type: Object,
        default: () => ({}),
    },
    positions: {
        type: Array,
        default: () => [],
    },
    departments: {
        type: Array,
        default: () => [],
    },
    statuses: {
        type: Array,
        default: () => [],
    },
    canCreate: Boolean,
    canExport: Boolean,
})

defineEmits([
    'action',
    'update:search',
    'update:filter',
    'update:records-per-page',
])
</script>

<template>
    <GlobalToolbar title="Empleados" subtitle="Administracion del personal registrado"
        v-bind="getEmployeeToolbarConfig({ canCreate, canExport, activeFilters, positions, departments, statuses })"
        :search="search" :records-per-page="recordsPerPage" :filtered-records="filteredRecords"
        :total-records="totalRecords" @action="$emit('action', $event)"
        @update:search="$emit('update:search', $event)" @update:filter="$emit('update:filter', $event)"
        @update:records-per-page="$emit('update:records-per-page', $event)" />
</template>
