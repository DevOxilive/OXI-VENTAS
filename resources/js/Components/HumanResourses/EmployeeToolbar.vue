<script setup>
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import { getEmployeeToolbarConfig } from '@/config/ToolbarConfigs/employeeToolbarConfig'

defineProps({
    search: String,
    recordsPerPage: Number,
    filteredRecords: Number,
    totalRecords: Number,
    canCreate: Boolean,
    canExport: Boolean,
})

defineEmits([
    'action',
    'update:search',
    'update:records-per-page',
])
</script>

<template>
    <GlobalToolbar title="Empleados" subtitle="Administración del personal registrado"
        v-bind="getEmployeeToolbarConfig({ canCreate, canExport })" :search="search" :records-per-page="recordsPerPage"
        :filtered-records="filteredRecords" :total-records="totalRecords" @action="$emit('action', $event)"
        @update:search="$emit('update:search', $event)"
        @update:records-per-page="$emit('update:records-per-page', $event)" />
</template>