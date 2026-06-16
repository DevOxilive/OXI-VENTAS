<script setup>
import { ref } from 'vue'
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

const showFilters = ref(false)

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
    <section class="md:hidden bg-white border border-slate-200 rounded-2xl shadow-sm p-4 space-y-4">
        <div v-if="title || subtitle" class="space-y-1">
            <h2 v-if="title" class="text-lg font-bold text-slate-800">
                {{ title }}
            </h2>

            <p v-if="subtitle" class="text-sm text-slate-500">
                {{ subtitle }}
            </p>
        </div>

        <input v-if="hasSearch" :value="search" type="text" :placeholder="searchPlaceholder"
            class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
            @input="$emit('update:search', $event.target.value)" />

        <button v-if="visibleFilters.length" type="button"
            class="w-full px-4 py-2 rounded-xl border border-slate-300 text-sm font-semibold text-slate-700 flex items-center justify-between"
            @click="showFilters = !showFilters">
            <span>Filtros</span>
            <span class="material-symbols-outlined text-[20px]">
                {{ showFilters ? 'expand_less' : 'expand_more' }}
            </span>
        </button>

        <div v-if="showFilters" class="space-y-3">
            <select v-for="filter in visibleFilters" :key="filter.key" :value="filter.value ?? ''"
                class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white outline-none"
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

        <div v-if="hasRecordsPerPage" class="flex items-center justify-between gap-3">
            <span class="text-sm text-slate-500">
                Mostrar registros
            </span>

            <select :value="recordsPerPage"
                class="border border-slate-300 rounded-xl px-3 py-2 text-sm bg-white outline-none"
                @change="$emit('update:records-per-page', Number($event.target.value))">
                <option v-for="option in recordsPerPageOptions" :key="option" :value="option">
                    {{ option }}
                </option>
            </select>
        </div>

        <div v-if="hasActions" class="grid grid-cols-1 gap-2">
            <button v-for="action in visibleActions" :key="action.id" type="button"
                class="w-full px-4 py-2 rounded-xl text-sm font-bold transition flex items-center justify-center gap-2"
                :class="getToolbarActionClasses(action.variant)" @click="$emit('action', action.id)">
                <span v-if="action.icon" class="material-symbols-outlined text-[18px]">
                    {{ action.icon }}
                </span>

                {{ action.label }}
            </button>
        </div>

        <p v-if="showCounter" class="text-xs text-slate-400 text-center">
            {{ filteredRecords }} de {{ totalRecords }} registros
        </p>
    </section>
</template>