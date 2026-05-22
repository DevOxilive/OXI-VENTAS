<script setup>
import { computed, nextTick } from 'vue'
import { fieldRegistry } from '@/Validation/fieldRegistry'
import { sanitizeField } from '@/Validation/sanitizers'

const props = defineProps({
    label: String,
    field: String,
    modelValue: [String, Number],
    error: String,
    rows: {
        type: Number,
        default: 3,
    },
    readonly: Boolean,
    autoResize: {
        type: Boolean,
        default: true,
    },
})

const emit = defineEmits(['update:modelValue', 'validate'])

const textareaId = computed(() =>
    props.field || props.label?.toLowerCase().replace(/\s+/g, '-')
)

const fieldConfig = computed(() => fieldRegistry[props.field])

function resizeTextarea(textarea) {
    if (!props.autoResize || !textarea) return

    textarea.style.height = 'auto'
    textarea.style.height = `${textarea.scrollHeight}px`
}

function handleInput(e) {
    const config = fieldConfig.value

    const value = config
        ? sanitizeField(e.target.value, config)
        : e.target.value

    e.target.value = value

    emit('update:modelValue', value)
    emit('validate', props.field)

    nextTick(() => resizeTextarea(e.target))
}

function blockExtraInput(e) {
    const config = fieldConfig.value
    if (!config) return

    const allowedKeys = [
        'Backspace', 'Delete', 'ArrowLeft', 'ArrowRight',
        'ArrowUp', 'ArrowDown', 'Tab', 'Home', 'End', 'Enter'
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
        <label :for="textareaId" class="block text-sm font-semibold mb-1 text-slate-700">
            {{ label }}
        </label>

        <textarea :id="textareaId" :name="field" :value="modelValue" :rows="rows" :readonly="readonly"
            @keydown="blockExtraInput" @input="handleInput" @blur="emit('validate', field)" :class="[
                'w-full px-4 py-3 rounded-xl border outline-none transition text-sm resize-none overflow-hidden',
                readonly ? 'bg-slate-100 cursor-not-allowed' : 'bg-white',
                error ? 'border-red-500 bg-red-50' : 'border-slate-300 focus:border-[#1f1d2b]'
            ]" />

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