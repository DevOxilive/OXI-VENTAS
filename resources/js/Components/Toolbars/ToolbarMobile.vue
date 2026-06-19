<script setup>
import { ref } from 'vue'
import { useToolbarConfig } from './useToolbarConfig'
import { getToolbarActionClasses } from './toolbarClasses'

const props = defineProps({
    backButton: {
        type: Boolean,
        default: false,
    },

    backLabel: {
        type: String,
        default: 'Volver',
    },
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
    'back',
    'update:search',
    'update:filter',
    'update:records-per-page',
    'update:active-tab',
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

function optionToneClasses(option, active) {
    if (!active) {
        return 'border-slate-200 bg-white text-slate-700'
    }

    const tones = {
        red: 'border-red-200 bg-red-50 text-red-700 ring-2 ring-red-100',
        amber: 'border-amber-200 bg-amber-50 text-amber-700 ring-2 ring-amber-100',
        blue: 'border-blue-200 bg-blue-50 text-blue-700 ring-2 ring-blue-100',
        rose: 'border-rose-200 bg-rose-50 text-rose-700 ring-2 ring-rose-100',
        slate: 'border-slate-300 bg-slate-50 text-slate-700 ring-2 ring-slate-100',
    }

    return tones[option?.tone] ?? tones.slate
}
</script>

<template>
    <section class="md:hidden bg-white border border-slate-200 rounded-2xl shadow-sm p-4 space-y-4">
        <div class="md:hidden space-y-3">
            <div v-if="backButton">
                <button type="button"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-700 shadow-sm transition active:scale-[0.99]"
                    @click="$emit('back')">
                    <span class="material-symbols-outlined text-[19px]">
                        arrow_back
                    </span>

                    {{ backLabel }}
                </button>
            </div>
        </div>
        <div v-if="tabs.length" class="overflow-x-auto">
            <div class="flex min-w-max gap-2">
                <button v-for="tab in tabs" :key="tab.key" type="button"
                    class="h-10 rounded-xl px-4 text-sm font-bold transition flex items-center gap-2" :class="activeTab === tab.key
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

        <div v-if="hasActions" class="grid grid-cols-1 gap-2">
            <button v-for="action in visibleActions" :key="action.id" type="button"
                class="w-full h-11 px-4 rounded-xl text-sm font-bold transition flex items-center justify-center gap-2"
                :class="getToolbarActionClasses(action.variant)" @click="$emit('action', action.id)">
                <span v-if="action.icon" class="material-symbols-outlined text-[18px]">
                    {{ action.icon }}
                </span>

                {{ action.label }}
            </button>
        </div>

        <div v-if="hasSearch || hasRecordsPerPage" class="grid grid-cols-1 gap-3">
            <input v-if="hasSearch" :value="search" type="text" :placeholder="searchPlaceholder"
                class="w-full h-11 border border-slate-300 rounded-xl px-4 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                @input="$emit('update:search', $event.target.value)" />

            <div v-if="hasRecordsPerPage"
                class="h-11 flex items-center justify-between gap-2 bg-slate-50 border border-slate-200 rounded-xl px-3">
                <span class="text-sm text-slate-500">
                    Mostrar filas
                </span>

                <select :value="recordsPerPage" class="bg-transparent text-sm font-semibold text-slate-700 outline-none"
                    @change="$emit('update:records-per-page', Number($event.target.value))">
                    <option v-for="option in recordsPerPageOptions" :key="option" :value="option">
                        {{ option }}
                    </option>
                </select>
            </div>
        </div>

        <button v-if="visibleFilters.length" type="button"
            class="w-full h-11 px-4 rounded-xl border border-slate-300 text-sm font-semibold text-slate-700 flex items-center justify-between"
            @click="showFilters = !showFilters">
            <span>Filtros</span>

            <span class="material-symbols-outlined text-[20px]">
                {{ showFilters ? 'expand_less' : 'expand_more' }}
            </span>
        </button>

        <div v-if="showFilters" class="space-y-3">
            <template v-for="filter in visibleFilters" :key="filter.key">
                <div v-if="filter.type === 'button-group'" class="space-y-2">
                    <p v-if="filter.label" class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">
                        {{ filter.label }}
                    </p>

                    <button v-for="option in filter.options || []" :key="getOptionValue(option, filter)"
                        type="button"
                        class="w-full rounded-xl border px-3 py-2 text-left transition"
                        :class="optionToneClasses(option, String(filter.value ?? '') === String(getOptionValue(option, filter) ?? ''))"
                        @click="$emit('update:filter', {
                            key: filter.key,
                            value: getOptionValue(option, filter)
                        })">
                        <span class="flex items-center gap-2 text-sm font-black">
                            <span v-if="option.icon" class="material-symbols-outlined text-[18px]">
                                {{ option.icon }}
                            </span>

                            {{ getOptionLabel(option, filter) }}
                        </span>

                        <span v-if="option.description" class="mt-1 block text-xs opacity-70">
                            {{ option.description }}
                        </span>
                    </button>
                </div>

                <label v-else class="block">
                    <span v-if="filter.label" class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-slate-400">
                        {{ filter.label }}
                    </span>

                    <input v-if="filter.type === 'date'" :value="filter.value ?? ''" type="date"
                    class="w-full h-11 border border-slate-300 rounded-xl px-3 text-sm bg-white outline-none" @input="$emit('update:filter', {
                        key: filter.key,
                        value: $event.target.value
                    })" />

                    <input v-else-if="filter.type === 'text'" :value="filter.value ?? ''" type="text"
                        :placeholder="filter.placeholder || filter.label"
                        class="w-full h-11 border border-slate-300 rounded-xl px-3 text-sm bg-white outline-none" @input="$emit('update:filter', {
                            key: filter.key,
                            value: $event.target.value
                        })" />

                    <select v-else :value="filter.value ?? ''"
                        class="w-full h-11 border border-slate-300 rounded-xl px-3 text-sm bg-white outline-none" @change="$emit('update:filter', {
                            key: filter.key,
                            value: $event.target.value
                        })">
                        <option :value="filter.emptyValue ?? ''">
                            {{ filter.placeholder || filter.label }}
                        </option>

                        <option v-for="option in filter.options || []" :key="getOptionValue(option, filter)"
                            :value="getOptionValue(option, filter)">
                            {{ getOptionLabel(option, filter) }}
                        </option>
                    </select>
                </label>
            </template>
        </div>

        <p v-if="showCounter" class="text-xs text-slate-400 text-center">
            {{ filteredRecords }} de {{ totalRecords }} registros
        </p>
    </section>
</template>
