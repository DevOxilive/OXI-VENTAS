<script setup>
import { ref } from 'vue'
import { useToolbarConfig } from './useToolbarConfig'
import { getToolbarActionClasses } from './toolbarClasses'
import { sanitizeToolbarFilter, sanitizeToolbarSearch } from './toolbarInputSanitizer'

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
    compactFilters: {
        type: Boolean,
        default: false,
    },
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

const emit = defineEmits([
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
        return 'border-secondary bg-background text-text'
    }

    const tones = {
        red: 'border-primary bg-secondary text-primary ring-2 ring-primary',
        amber: 'border-accent bg-secondary text-accent ring-2 ring-accent',
        blue: 'border-primary bg-secondary text-primary ring-2 ring-primary',
        rose: 'border-primary bg-secondary text-primary ring-2 ring-primary',
        slate: 'border-secondary bg-secondary text-text ring-2 ring-secondary',
    }

    return tones[option?.tone] ?? tones.slate
}

function handleSearchInput(event) {
    const value = sanitizeToolbarSearch(event.target.value)

    event.target.value = value
    return value
}

function handleTextFilterInput(event, filter) {
    const value = sanitizeToolbarFilter(event.target.value, filter)

    event.target.value = value
    return value
}

function normalizeMultiValue(value) {
    return Array.isArray(value) ? value.map((item) => String(item)) : []
}

function isMultiSelected(filter, option) {
    return normalizeMultiValue(filter.value).includes(String(getOptionValue(option, filter)))
}

function toggleMultiFilter(filter, option) {
    const optionValue = String(getOptionValue(option, filter))
    const current = normalizeMultiValue(filter.value)
    const next = current.includes(optionValue)
        ? current.filter((value) => value !== optionValue)
        : [...current, optionValue]

    emit('update:filter', {
        key: filter.key,
        value: next,
    })
}

function clearMultiFilter(filter) {
    emit('update:filter', {
        key: filter.key,
        value: [],
    })
}

function multiFilterLabel(filter) {
    const selected = normalizeMultiValue(filter.value)

    if (selected.length === 0) {
        return filter.placeholder || filter.label
    }

    if (selected.length === 1) {
        const selectedOption = (filter.options || []).find((option) =>
            String(getOptionValue(option, filter)) === selected[0]
        )

        return selectedOption ? getOptionLabel(selectedOption, filter) : '1 seleccionado'
    }

    return `${selected.length} seleccionados`
}
</script>

<template>
    <section class="space-y-4 rounded-2xl border border-secondary bg-background p-4 shadow-sm md:hidden">
        <div class="md:hidden space-y-3">
            <div v-if="backButton">
                <button type="button"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-secondary bg-background px-4 py-2.5 text-sm font-black text-text shadow-sm transition active:scale-[0.99]"
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
                    class="flex h-10 items-center gap-2 rounded-xl px-4 text-sm font-bold transition" :class="activeTab === tab.key
                        ? 'bg-primary text-white'
                        : 'text-text opacity-70 hover:bg-secondary hover:text-text hover:opacity-100'"
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
                :class="[
                    getToolbarActionClasses(action.variant),
                    action.disabled ? 'cursor-not-allowed opacity-60' : ''
                ]"
                :disabled="action.disabled"
                @click="$emit('action', action.id)">
                <span v-if="action.icon" class="material-symbols-outlined text-[18px]">
                    {{ action.icon }}
                </span>

                {{ action.label }}

                <span
                    v-if="action.badge"
                    class="inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 py-0.5 text-[11px] font-black text-white"
                >
                    {{ action.badge }}
                </span>
            </button>
        </div>

        <div v-if="hasSearch || hasRecordsPerPage" class="grid grid-cols-1 gap-3">
            <input v-if="hasSearch" :value="search" type="text" :placeholder="searchPlaceholder"
                class="h-11 w-full rounded-xl border border-secondary bg-background px-4 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary"
                @input="$emit('update:search', handleSearchInput($event))" />

            <div v-if="hasRecordsPerPage"
                class="flex h-11 items-center justify-between gap-2 rounded-xl border border-secondary bg-secondary px-3">
                <span class="text-sm text-text opacity-70">
                    Mostrar filas
                </span>

                <select :value="recordsPerPage" class="bg-transparent text-sm font-semibold text-text outline-none"
                    @change="$emit('update:records-per-page', Number($event.target.value))">
                    <option v-for="option in recordsPerPageOptions" :key="option" :value="option">
                        {{ option }}
                    </option>
                </select>
            </div>
        </div>

        <button v-if="visibleFilters.length" type="button"
            class="flex h-11 w-full items-center justify-between rounded-xl border border-secondary bg-background px-4 text-sm font-semibold text-text"
            @click="showFilters = !showFilters">
            <span>Filtros</span>

            <span class="material-symbols-outlined text-[20px]">
                {{ showFilters ? 'expand_less' : 'expand_more' }}
            </span>
        </button>

        <div v-if="showFilters" class="space-y-3">
            <template v-for="filter in visibleFilters" :key="filter.key">
                <div v-if="filter.type === 'button-group'" class="space-y-2">
                    <p v-if="filter.label" class="text-xs font-black uppercase tracking-[0.18em] text-text opacity-50">
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
                    <span v-if="filter.label" class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-text opacity-50">
                        {{ filter.label }}
                    </span>

                    <input v-if="filter.type === 'date'" :value="filter.value ?? ''" type="date"
                    class="h-11 w-full rounded-xl border border-secondary bg-background px-3 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary"
                    :disabled="filter.disabled"
                    :min="filter.min"
                    :max="filter.max"
                    @input="$emit('update:filter', {
                        key: filter.key,
                        value: $event.target.value
                    })" />

                    <input v-else-if="filter.type === 'text'" :value="filter.value ?? ''" type="text"
                        :placeholder="filter.placeholder || filter.label"
                        class="h-11 w-full rounded-xl border border-secondary bg-background px-3 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary"
                        :disabled="filter.disabled"
                        @input="$emit('update:filter', {
                            key: filter.key,
                            value: handleTextFilterInput($event, filter)
                        })" />

                    <details v-else-if="filter.type === 'multiselect'" class="group relative">
                        <summary
                            class="flex h-11 cursor-pointer list-none items-center justify-between rounded-xl border border-secondary bg-background px-3 text-sm text-text outline-none"
                        >
                            <span class="truncate">
                                {{ multiFilterLabel(filter) }}
                            </span>

                            <span class="material-symbols-outlined text-[20px] text-text opacity-50 transition group-open:rotate-180">
                                expand_more
                            </span>
                        </summary>

                        <div class="mt-2 max-h-64 overflow-y-auto rounded-xl border border-secondary bg-background p-2 shadow-sm">
                            <button
                                type="button"
                                class="mb-2 w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-text opacity-70 hover:bg-secondary"
                                @click="clearMultiFilter(filter)"
                            >
                                Todos
                            </button>

                            <button
                                v-for="option in filter.options || []"
                                :key="getOptionValue(option, filter)"
                                type="button"
                                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-secondary"
                                @click="toggleMultiFilter(filter, option)"
                            >
                                <span
                                    class="flex h-4 w-4 items-center justify-center rounded border"
                                    :class="isMultiSelected(filter, option) ? 'border-primary bg-primary text-white' : 'border-secondary bg-background'"
                                >
                                    <span v-if="isMultiSelected(filter, option)" class="material-symbols-outlined text-[13px]">
                                        check
                                    </span>
                                </span>

                                <span class="truncate text-text">
                                    {{ getOptionLabel(option, filter) }}
                                </span>
                            </button>
                        </div>
                    </details>

                    <select v-else :value="filter.value ?? ''"
                        class="h-11 w-full rounded-xl border border-secondary bg-background px-3 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary"
                        :disabled="filter.disabled"
                        @change="$emit('update:filter', {
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

        <p v-if="showCounter" class="text-center text-xs text-text opacity-50">
            {{ filteredRecords }} de {{ totalRecords }} registros
        </p>
    </section>
</template>
