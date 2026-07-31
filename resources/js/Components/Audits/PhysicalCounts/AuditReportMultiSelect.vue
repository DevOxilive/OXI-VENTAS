<script setup>
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
    emptyLabel: { type: String, required: true },
})

const emit = defineEmits(['update:modelValue'])

function toggle(value) {
    const normalized = String(value)
    emit(
        'update:modelValue',
        props.modelValue.map(String).includes(normalized)
            ? props.modelValue.filter((item) => String(item) !== normalized)
            : [...props.modelValue, value],
    )
}
</script>

<template>
    <details class="relative min-w-52">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-xl border border-secondary px-3 py-2.5 text-text">
            <span>{{ modelValue.length ? `${modelValue.length} seleccionado(s)` : emptyLabel }}</span>
            <span class="material-symbols-outlined text-lg opacity-60">expand_more</span>
        </summary>

        <div class="absolute z-30 mt-2 max-h-64 w-full min-w-64 overflow-y-auto rounded-xl border border-secondary bg-background p-2 shadow-xl">
            <button
                type="button"
                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left hover:bg-secondary"
                @click="emit('update:modelValue', [])"
            >
                <span class="flex h-4 w-4 items-center justify-center rounded border border-secondary">
                    <span v-if="modelValue.length === 0" class="material-symbols-outlined text-sm text-primary">check</span>
                </span>
                <span class="font-medium text-text">{{ emptyLabel }}</span>
            </button>

            <div class="my-1 border-t border-secondary"></div>

            <button
                v-for="option in options"
                :key="option.id"
                type="button"
                class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left hover:bg-secondary"
                @click="toggle(option.id)"
            >
                <span class="flex h-4 w-4 items-center justify-center rounded border border-secondary">
                    <span
                        v-if="modelValue.map(String).includes(String(option.id))"
                        class="material-symbols-outlined text-sm text-primary"
                    >check</span>
                </span>
                <span class="text-text">{{ option.name }}</span>
            </button>

            <p v-if="options.length === 0" class="px-3 py-3 text-xs text-text opacity-55">
                Sin opciones disponibles
            </p>
        </div>
    </details>
</template>
