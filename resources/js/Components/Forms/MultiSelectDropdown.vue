<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    options: {
        type: Array,
        default: () => [],
    },
    label: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Todos',
    },
    optionLabel: {
        type: String,
        default: 'label',
    },
    optionValue: {
        type: String,
        default: 'value',
    },
    floating: {
        type: Boolean,
        default: true,
    },
})

const emit = defineEmits(['update:modelValue'])

const root = ref(null)
const searchInput = ref(null)
const open = ref(false)
const search = ref('')
const highlightedSuggestionIndex = ref(0)

const selectedValues = computed(() => props.modelValue.map((value) => String(value)))

const filteredOptions = computed(() => {
    const query = search.value.trim().toLowerCase()

    if (!query) return props.options

    return props.options
        .filter((option) => matchesProductSearch(option, query))
        .slice()
        .sort((a, b) => {
            const aName = String(getOptionLabel(a)).toLowerCase()
            const bName = String(getOptionLabel(b)).toLowerCase()
            const aStarts = aName.startsWith(query) || getOptionBarcodes(a).some((barcode) => barcode.startsWith(query))
            const bStarts = bName.startsWith(query) || getOptionBarcodes(b).some((barcode) => barcode.startsWith(query))

            if (aStarts !== bStarts) {
                return aStarts ? -1 : 1
            }

            return aName.localeCompare(bName, 'es')
        })
        .slice(0, 6)
})

const displayLabel = computed(() => {
    if (selectedValues.value.length === 0) {
        return props.placeholder || props.label
    }

    if (selectedValues.value.length === 1) {
        const option = props.options.find((item) => String(getOptionValue(item)) === selectedValues.value[0])

        return option ? getOptionLabel(option) : '1 seleccionado'
    }

    return `${selectedValues.value.length} seleccionados`
})

function getOptionLabel(option) {
    return option?.[props.optionLabel] ?? option?.label ?? option?.name ?? option
}

function getOptionValue(option) {
    return option?.[props.optionValue] ?? option?.value ?? option?.id ?? option
}

function getOptionBarcodes(option) {
    const codes = [option?.barcode, option?.code]

    if (Array.isArray(option?.barcodes)) {
        codes.push(...option.barcodes.map((barcode) => barcode?.code ?? barcode))
    }

    return [...new Set(codes
        .map((barcode) => String(barcode ?? '').trim().toLowerCase())
        .filter(Boolean))]
}

function matchesProductSearch(option, query) {
    const name = String(getOptionLabel(option)).toLowerCase()
    const barcodes = getOptionBarcodes(option)

    // La búsqueda global sólo consulta los datos propios del producto. No se
    // consideran lotes, caducidades ni existencias para decidir una coincidencia.
    return name.includes(query) || barcodes.some((barcode) => barcode.includes(query))
}

function isSelected(option) {
    return selectedValues.value.includes(String(getOptionValue(option)))
}

function toggleOption(option) {
    const value = String(getOptionValue(option))
    const next = selectedValues.value.includes(value)
        ? selectedValues.value.filter((item) => item !== value)
        : [...selectedValues.value, value]

    emit('update:modelValue', next)
    search.value = ''
    highlightedSuggestionIndex.value = 0
    focusSearch()
}

function clearSelection() {
    emit('update:modelValue', [])
    search.value = ''
    highlightedSuggestionIndex.value = 0
    focusSearch()
}

function toggleDropdown() {
    open.value = !open.value

    if (open.value) focusSearch()
}

function moveSuggestion(delta) {
    if (!filteredOptions.value.length) return

    const maxIndex = filteredOptions.value.length - 1
    const nextIndex = highlightedSuggestionIndex.value + delta

    if (nextIndex < 0) {
        highlightedSuggestionIndex.value = maxIndex
        return
    }

    if (nextIndex > maxIndex) {
        highlightedSuggestionIndex.value = 0
        return
    }

    highlightedSuggestionIndex.value = nextIndex
}

function handleSearchKeydown(event) {
    if (event.key === 'ArrowDown') {
        event.preventDefault()
        moveSuggestion(1)
        return
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault()
        moveSuggestion(-1)
        return
    }

    if (event.key !== 'Enter' || !filteredOptions.value.length) return

    event.preventDefault()
    toggleOption(
        filteredOptions.value[
            Math.min(highlightedSuggestionIndex.value, filteredOptions.value.length - 1)
        ]
    )
}

watch(search, () => {
    highlightedSuggestionIndex.value = 0
})

function focusSearch() {
    nextTick(() => {
        searchInput.value?.focus()
        searchInput.value?.select()
    })
}

function handleOutsideClick(event) {
    if (!open.value || root.value?.contains(event.target)) return

    open.value = false
}

onMounted(() => {
    document.addEventListener('pointerdown', handleOutsideClick)
})

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleOutsideClick)
})
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex h-11 w-full items-center justify-between rounded-xl border border-secondary bg-background px-3 text-sm text-text outline-none transition focus:border-primary focus:ring-2 focus:ring-primary"
            @click="toggleDropdown"
        >
            <span class="truncate">
                {{ displayLabel }}
            </span>

            <span class="material-symbols-outlined text-[20px] text-text opacity-50 transition" :class="open ? 'rotate-180' : ''">
                expand_more
            </span>
        </button>

        <div
            v-if="open"
            class="z-40 mt-2 max-h-72 w-full min-w-full overflow-y-auto rounded-xl border border-secondary bg-background p-2 shadow-xl sm:w-[28rem]"
            :class="floating ? 'absolute' : 'relative'"
        >
            <div class="mb-2 flex h-11 items-center gap-3 rounded-lg bg-secondary px-3">
                <span class="material-symbols-outlined shrink-0 text-[18px] text-text opacity-60">barcode_scanner</span>
                <input
                    ref="searchInput"
                    v-model="search"
                    type="text"
                    class="h-full min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-text placeholder:text-text placeholder:opacity-50 outline-none focus:border-0 focus:ring-0"
                    placeholder="Código de barras o nombre del producto"
                    @keydown="handleSearchKeydown"
                />
            </div>

            <button
                type="button"
                class="mb-2 w-full rounded-lg px-3 py-2 text-left text-xs font-semibold text-text opacity-70 hover:bg-secondary"
                @click="clearSelection"
            >
                Todos
            </button>

            <button
                v-for="(option, index) in filteredOptions"
                :key="getOptionValue(option)"
                type="button"
                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-secondary"
                :class="search && index === highlightedSuggestionIndex ? 'bg-primary text-white hover:bg-primary' : ''"
                @click="toggleOption(option)"
            >
                <span
                    class="flex h-4 w-4 shrink-0 items-center justify-center rounded border"
                    :class="isSelected(option) ? 'border-primary bg-primary text-white' : 'border-secondary bg-background'"
                >
                    <span v-if="isSelected(option)" class="material-symbols-outlined text-[13px]">
                        check
                    </span>
                </span>

                <span class="min-w-0 truncate" :class="search && index === highlightedSuggestionIndex ? 'text-white' : 'text-text'" :title="getOptionLabel(option)">
                    {{ getOptionLabel(option) }}
                </span>
            </button>
        </div>
    </div>
</template>
