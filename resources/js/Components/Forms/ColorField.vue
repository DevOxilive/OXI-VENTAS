<script setup>
import { computed } from 'vue'

const props = defineProps({
    label: {
        type: String,
        default: 'Color',
    },
    field: {
        type: String,
        default: 'color',
    },
    modelValue: {
        type: String,
        default: '',
    },
    error: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    hideLabel: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['update:modelValue', 'validate'])

const inputId = computed(() => props.field || props.label.toLowerCase().replace(/\s+/g, '-'))

function handleInput(event) {
    emit('update:modelValue', event.target.value)
}

function handleBlur() {
    emit('validate', props.field)
}
</script>

<template>
    <div>
        <label
            v-if="!hideLabel"
            :for="inputId"
            class="mb-2 block text-sm font-semibold text-text"
        >
            {{ label }}
        </label>

        <div
            class="flex items-center gap-3 rounded-xl border px-3 py-3 transition"
            :class="error ? 'border-primary bg-secondary' : 'border-secondary bg-background'"
        >
            <input
                :id="inputId"
                :value="modelValue"
                :disabled="disabled"
                type="color"
                class="h-12 w-16 cursor-pointer rounded-lg border border-secondary bg-background disabled:cursor-not-allowed"
                @input="handleInput"
                @blur="handleBlur"
            >

            <div class="min-w-0">
                <p class="text-sm font-medium text-text">
                    {{ modelValue || 'Sin color seleccionado' }}
                </p>

                <p class="text-xs text-text opacity-70">
                    Se usara como color visual de la sucursal.
                </p>
            </div>
        </div>

        <p v-if="error" class="mt-1 text-xs text-primary">
            {{ error }}
        </p>
    </div>
</template>
