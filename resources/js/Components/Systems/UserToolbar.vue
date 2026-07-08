<script setup>
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import { getUsersToolbarConfig } from '@/config/ToolbarConfigs/usersToolbarConfig'

defineProps({
    search: String,
    recordsPerPage: Number,
    filteredRecords: Number,
    totalRecords: Number,
    roles: {
        type: Array,
        default: () => [],
    },
    activeFilters: {
        type: Object,
        default: () => ({}),
    },
})

defineEmits([
    'update:search',
    'update:filter',
    'update:records-per-page',
])
</script>

<template>
    <GlobalToolbar title="Registro de Usuarios" subtitle="Administra empleados disponibles y usuarios del sistema"
        v-bind="getUsersToolbarConfig({ roles, activeFilters })" :search="search" :records-per-page="recordsPerPage"
        :records-per-page-options="[10, 25, 50, 100]" :filtered-records="filteredRecords" :total-records="totalRecords"
        @update:search="$emit('update:search', $event)" @update:filter="$emit('update:filter', $event)"
        @update:records-per-page="$emit('update:records-per-page', $event)" />
</template>
