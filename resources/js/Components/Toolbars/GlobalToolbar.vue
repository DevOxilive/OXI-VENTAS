<script setup>
import { computed, useSlots } from 'vue'
import ToolbarMobile from './ToolbarMobile.vue'
import ToolbarDesktop from './ToolbarDesktop.vue'

const slots = useSlots()
const hasActionsSlot = computed(() => Boolean(slots.actions))

defineProps({
    icon: String,
    title: String,
    subtitle: String,

    backButton: {
        type: Boolean,
        default: false,
    },

    backLabel: {
        type: String,
        default: 'Regresar',
    },

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
    compactFilters: {
        type: Boolean,
        default: false,
    },

    filters: {
        type: Array,
        default: () => [],
    },
    actions: {
        type: Array,
        default: () => [],
    },

    tabs: {
        type: Array,
        default: () => [],
    },
    activeTab: {
        type: String,
        default: '',
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
    'back',
    'update:search',
    'update:filter',
    'update:records-per-page',
    'update:active-tab',
    'action',
])
</script>

<template>
    <div>
        <ToolbarDesktop :icon="icon" :title="title" :subtitle="subtitle" :back-button="backButton" :back-label="backLabel"
            :search="search" :search-placeholder="searchPlaceholder" :show-search="showSearch" :filters="filters"
            :actions="actions" :tabs="tabs" :active-tab="activeTab" :records-per-page="recordsPerPage"
            :compact-filters="compactFilters"
            :records-per-page-options="recordsPerPageOptions" :show-records-per-page="showRecordsPerPage"
            :total-records="totalRecords" :filtered-records="filteredRecords" :show-counter="showCounter"
            @back="$emit('back')" @update:search="$emit('update:search', $event)"
            @update:filter="$emit('update:filter', $event)"
            @update:records-per-page="$emit('update:records-per-page', $event)"
            @update:active-tab="$emit('update:active-tab', $event)" @action="$emit('action', $event)">
            <template v-if="hasActionsSlot" #actions><slot name="actions" /></template>
        </ToolbarDesktop>

        <ToolbarMobile :icon="icon" :title="title" :subtitle="subtitle" :back-button="backButton" :back-label="backLabel"
            :search="search" :search-placeholder="searchPlaceholder" :show-search="showSearch" :filters="filters"
            :actions="actions" :tabs="tabs" :active-tab="activeTab" :records-per-page="recordsPerPage"
            :compact-filters="compactFilters"
            :records-per-page-options="recordsPerPageOptions" :show-records-per-page="showRecordsPerPage"
            :total-records="totalRecords" :filtered-records="filteredRecords" :show-counter="showCounter"
            @back="$emit('back')" @update:search="$emit('update:search', $event)"
            @update:filter="$emit('update:filter', $event)"
            @update:records-per-page="$emit('update:records-per-page', $event)"
            @update:active-tab="$emit('update:active-tab', $event)" @action="$emit('action', $event)">
            <template v-if="hasActionsSlot" #actions><slot name="actions" /></template>
        </ToolbarMobile>
    </div>
</template>
