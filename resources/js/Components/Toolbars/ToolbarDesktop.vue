<script setup>
import { useToolbarConfig } from './useToolbarConfig'
import { getToolbarActionClasses } from './toolbarClasses'

const props = defineProps({
    title: String,
    subtitle: String,
    search: String,
    searchPlaceholder: String,
    showSearch: Boolean,
    filters: Array,
    actions: Array,
    tabs: {
        type: Array,
        default: () => [],
    },
    activeTab: {
        type: String,
        default: '',
    },
    recordsPerPage: Number,
    recordsPerPageOptions: Array,
    showRecordsPerPage: Boolean,
    totalRecords: Number,
    filteredRecords: Number,
    showCounter: Boolean,
})

defineEmits([
    'update:search',
    'update:filter',
    'update:records-per-page',
    'update:active-tab',
    'action',
])

const {
    visibleFilters,
    visibleActions,
    hasActions,
    hasSearch,
    hasRecordsPerPage,
    getOptionLabel,
    getOptionValue,
} = useToolbarConfig(props)
</script>

<template>
    <section class="hidden md:block bg-white border border-slate-200 rounded-2xl shadow-sm p-5 space-y-4">
        <div class="flex items-start justify-between gap-6">
            <div class="min-w-0">
                <h2 v-if="title" class="text-xl font-black text-slate-800">
                    {{ title }}
                </h2>

                <p v-if="subtitle" class="text-sm text-slate-500 mt-1">
                    {{ subtitle }}
                </p>
            </div>

            <div v-if="hasActions" class="flex items-center justify-end gap-2 shrink-0">
                <button v-for="action in visibleActions" :key="action.id" type="button"
                    class="h-10 px-4 rounded-xl text-sm font-bold transition flex items-center gap-2 whitespace-nowrap"
                    :class="getToolbarActionClasses(action.variant)" @click="$emit('action', action.id)">
                    <span v-if="action.icon" class="material-symbols-outlined text-[18px]">
                        {{ action.icon }}
                    </span>

                    {{ action.label }}
                </button>
            </div>
        </div>

        <div v-if="tabs.length" class="overflow-x-auto">
            <div class="flex min-w-max gap-2">
                <button v-for="tab in tabs" :key="tab.key" type="button"
                    class="rounded-xl px-4 py-2 text-sm font-bold transition flex items-center gap-2" :class="activeTab === tab.key
                        ? 'bg-slate-900 text-white'
                        : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                    @click="$emit('update:active-tab', tab.key)">
                    <span v-if="tab.icon" class="material-symbols-outlined text-[18px]">
                        {{ tab.icon }}
                    </span>

                    {{ tab.label }}
                </button>
            </div>
        </div>

        <div v-if="hasSearch || hasRecordsPerPage" class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-3 items-center">
            <input v-if="hasSearch" :value="search" type="text" :placeholder="searchPlaceholder"
                class="h-11 w-full border border-slate-300 rounded-xl px-4 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                @input="$emit('update:search', $event.target.value)" />

            <div v-if="hasRecordsPerPage"
                class="h-11 flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-3">
                <span class="text-sm text-slate-500 whitespace-nowrap">
                    Mostrar
                </span>

                <select :value="recordsPerPage" class="bg-transparent text-sm font-semibold text-slate-700 outline-none"
                    @change="$emit('update:records-per-page', Number($event.target.value))">
                    <option v-for="option in recordsPerPageOptions" :key="option" :value="option">
                        {{ option }}
                    </option>
                </select>

                <span class="text-sm text-slate-500 whitespace-nowrap">
                    filas
                </span>
            </div>
        </div>

        <div v-if="visibleFilters.length" class="grid grid-cols-1 gap-3" :class="{
            'sm:grid-cols-1': visibleFilters.length === 1,
            'sm:grid-cols-2': visibleFilters.length === 2,
            'sm:grid-cols-3': visibleFilters.length === 3,
            'sm:grid-cols-2 lg:grid-cols-4': visibleFilters.length === 4,
            'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5': visibleFilters.length === 5,
            'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6': visibleFilters.length >= 6,
        }">
            <select v-for="filter in visibleFilters" :key="filter.key" :value="filter.value ?? ''"
                class="h-11 w-full border border-slate-300 rounded-xl px-3 text-sm bg-white outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                @change="$emit('update:filter', { key: filter.key, value: $event.target.value })">
                <option :value="filter.emptyValue ?? ''">
                    {{ filter.placeholder || filter.label }}
                </option>

                <option v-for="option in filter.options || []" :key="getOptionValue(option, filter)"
                    :value="getOptionValue(option, filter)">
                    {{ getOptionLabel(option, filter) }}
                </option>
            </select>
        </div>

        <p v-if="showCounter" class="text-xs text-slate-400">
            {{ filteredRecords }} de {{ totalRecords }} registros
        </p>
    </section>
</template>