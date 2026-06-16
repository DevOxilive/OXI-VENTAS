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
    <section class="hidden md:block bg-white border border-slate-200 rounded-2xl shadow-sm p-4 space-y-4">
        <div v-if="title || subtitle || hasActions" class="flex items-start justify-between gap-4">
            <div>
                <h2 v-if="title" class="text-lg font-bold text-slate-800">
                    {{ title }}
                </h2>

                <p v-if="subtitle" class="text-sm text-slate-500 mt-1">
                    {{ subtitle }}
                </p>
            </div>

            <div v-if="hasActions" class="flex items-center gap-2">
                <button v-for="action in visibleActions" :key="action.id" type="button"
                    class="px-4 py-2 rounded-xl text-sm font-bold transition flex items-center gap-2"
                    :class="getToolbarActionClasses(action.variant)" @click="$emit('action', action.id)">
                    <span v-if="action.icon" class="material-symbols-outlined text-[18px]">
                        {{ action.icon }}
                    </span>

                    {{ action.label }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-3">
            <input v-if="hasSearch" :value="search" type="text" :placeholder="searchPlaceholder"
                class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                @input="$emit('update:search', $event.target.value)" />

            <div v-if="hasRecordsPerPage" class="flex items-center gap-2">
                <span class="text-sm text-slate-500">
                    Mostrar
                </span>

                <select :value="recordsPerPage"
                    class="border border-slate-300 rounded-xl px-3 py-2.5 text-sm bg-white outline-none"
                    @change="$emit('update:records-per-page', Number($event.target.value))">
                    <option v-for="option in recordsPerPageOptions" :key="option" :value="option">
                        {{ option }}
                    </option>
                </select>
            </div>
        </div>

        <div v-if="visibleFilters.length" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <select v-for="filter in visibleFilters" :key="filter.key" :value="filter.value ?? ''"
                class="border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white outline-none"
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