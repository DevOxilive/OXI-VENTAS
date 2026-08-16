<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

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
const open = ref(false)
const search = ref('')

const selectedValues = computed(() => props.modelValue.map((value) => String(value)))

const filteredOptions = computed(() => {
    const query = search.value.trim().toLowerCase()

    if (!query) return props.options

    return props.options.filter((option) =>
        String(getOptionLabel(option)).toLowerCase().includes(query)
    )
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

function isSelected(option) {
    return selectedValues.value.includes(String(getOptionValue(option)))
}

function toggleOption(option) {
    const value = String(getOptionValue(option))
    const next = selectedValues.value.includes(value)
        ? selectedValues.value.filter((item) => item !== value)
        : [...selectedValues.value, value]

    emit('update:modelValue', next)
}

function clearSelection() {
    emit('update:modelValue', [])
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
            @click="open = !open"
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
            class="z-40 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-secondary bg-background p-2 shadow-xl"
            :class="floating ? 'absolute' : 'relative'"
        >
            <div class="mb-2 flex h-10 items-center gap-2 rounded-lg bg-secondary px-3">
                <span class="material-symbols-outlined text-[18px] text-text opacity-60">filter_list</span>
                <input
                    v-model="search"
                    type="text"
                    class="min-w-0 flex-1 bg-transparent text-sm text-text placeholder:text-text placeholder:opacity-50 outline-none"
                    placeholder="Escribe para filtrar"
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
                v-for="option in filteredOptions"
                :key="getOptionValue(option)"
                type="button"
                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm hover:bg-secondary"
                @click="toggleOption(option)"
            >
                <span
                    class="flex h-4 w-4 items-center justify-center rounded border"
                    :class="isSelected(option) ? 'border-primary bg-primary text-white' : 'border-secondary bg-background'"
                >
                    <span v-if="isSelected(option)" class="material-symbols-outlined text-[13px]">
                        check
                    </span>
                </span>

                <span class="truncate text-text">
                    {{ getOptionLabel(option) }}
                </span>
            </button>
        </div>
    </div>
</template>
