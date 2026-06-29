<script setup>
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import { getUsersToolbarConfig } from '@/config/ToolbarConfigs/usersToolbarConfig'

defineProps({
    viewMode: String,
    search: String,
    recordsPerPage: Number,
    filteredRecords: Number,
    totalRecords: Number,
})

defineEmits([
    'update:active-tab',
    'update:search',
    'update:records-per-page',
])
</script>

<template>
    <GlobalToolbar title="Registro de Usuarios" subtitle="Administra empleados disponibles y usuarios del sistema"
        v-bind="getUsersToolbarConfig({ viewMode })" :tabs="[
            { key: 'employees', label: 'Empleados' },
            { key: 'users', label: 'Usuarios' },
        ]" :active-tab="viewMode" :search="search" :records-per-page="recordsPerPage"
        :records-per-page-options="[10, 25, 50, 100]" :filtered-records="filteredRecords" :total-records="totalRecords"
        @update:active-tab="$emit('update:active-tab', $event)" @update:search="$emit('update:search', $event)"
        @update:records-per-page="$emit('update:records-per-page', $event)" />
</template>