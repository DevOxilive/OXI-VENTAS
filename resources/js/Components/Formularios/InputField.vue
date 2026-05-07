<script setup>
import { computed } from 'vue'
import { fieldRegistry } from '@/Validation/fieldRegistry'
import { sanitizeField } from '@/Validation/sanitizers'

const props = defineProps({
    label: String,
    field: String,
    modelValue: [String, Number],
    error: String,
    type: {
        type: String,
        default: 'text'
    },
    readonly: Boolean
})

const inputId = computed(() =>
    props.field || props.label?.toLowerCase().replace(/\s+/g, '-')
)
const emit = defineEmits(['update:modelValue', 'validate'])

const fieldConfig = computed(() => fieldRegistry[props.field])

function handleInput(e) {
    const config = fieldConfig.value

    const value = config
        ? sanitizeField(e.target.value, config)
        : e.target.value

    e.target.value = value

    emit('update:modelValue', value)
    emit('validate', props.field)
}

function blockExtraInput(e) {
    const config = fieldConfig.value
    if (!config) return

    const allowedKeys = [
        'Backspace', 'Delete', 'ArrowLeft', 'ArrowRight',
        'ArrowUp', 'ArrowDown', 'Tab', 'Home', 'End'
    ]

    if (e.ctrlKey || e.metaKey) return
    if (allowedKeys.includes(e.key)) return

    const currentLength = (props.modelValue || '').toString().length

    if (config.max && currentLength >= config.max) {
        e.preventDefault()
    }
}
</script>

<template>
    <div>
        <label :for="inputId" class="block text-sm font-semibold mb-1 text-slate-700">
            {{ label }}
        </label>

        <input :id="inputId" :name="field" :type="type" :value="modelValue" :readonly="readonly"
            @keydown="blockExtraInput" @input="handleInput" @blur="emit('validate', field)" :class="[
                'w-full px-4 py-3 rounded-xl border outline-none transition text-sm',
                readonly ? 'bg-slate-100 cursor-not-allowed' : 'bg-white',
                error ? 'border-red-500 bg-red-50' : 'border-slate-300 focus:border-[#1f1d2b]'
            ]">

        <div class="flex justify-between items-center mt-1">
            <p v-if="error" class="text-red-500 text-xs">
                {{ error }}
            </p>

            <p v-if="fieldConfig?.max" class="text-[11px] text-slate-400 ml-auto">
                {{ (modelValue || '').toString().length }}/{{ fieldConfig.max }}
            </p>
        </div>
    </div>
</template>