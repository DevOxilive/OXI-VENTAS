<script setup>
import ToolbarDesktop from './ToolbarDesktop.vue'
import ToolbarMobile from './ToolbarMobile.vue'

defineProps({
    title: String,
    subtitle: String,

    search: {
        type: String,
        default: '',
    },
    searchPlaceholder: {
        type: String,
        default: 'Buscar...',
    },
    showSearch: {
        type: Boolean,
        default: true,
    },

    filters: {
        type: Array,
        default: () => [],
    },
    actions: {
        type: Array,
        default: () => [],
    },

    recordsPerPage: {
        type: Number,
        default: 50,
    },
    recordsPerPageOptions: {
        type: Array,
        default: () => [10, 25, 50, 100],
    },
    showRecordsPerPage: {
        type: Boolean,
        default: true,
    },

    totalRecords: {
        type: Number,
        default: 0,
    },
    filteredRecords: {
        type: Number,
        default: 0,
    },
    showCounter: {
        type: Boolean,
        default: true,
    },
})

defineEmits([
    'update:search',
    'update:filter',
    'update:recordsPerPage',
    'action',
])
</script>

<template>
    <div>
        <ToolbarDesktop :title="title" :subtitle="subtitle" :search="search" :search-placeholder="searchPlaceholder"
            :show-search="showSearch" :filters="filters" :actions="actions" :records-per-page="recordsPerPage"
            :records-per-page-options="recordsPerPageOptions" :show-records-per-page="showRecordsPerPage"
            :total-records="totalRecords" :filtered-records="filteredRecords" :show-counter="showCounter"
            @update:search="$emit('update:search', $event)" @update:filter="$emit('update:filter', $event)"
            @update:records-per-page="$emit('update:recordsPerPage', $event)" @action="$emit('action', $event)" />

        <ToolbarMobile :title="title" :subtitle="subtitle" :search="search" :search-placeholder="searchPlaceholder"
            :show-search="showSearch" :filters="filters" :actions="actions" :records-per-page="recordsPerPage"
            :records-per-page-options="recordsPerPageOptions" :show-records-per-page="showRecordsPerPage"
            :total-records="totalRecords" :filtered-records="filteredRecords" :show-counter="showCounter"
            @update:search="$emit('update:search', $event)" @update:filter="$emit('update:filter', $event)"
            @update:records-per-page="$emit('update:recordsPerPage', $event)" @action="$emit('action', $event)" />
    </div>
</template>