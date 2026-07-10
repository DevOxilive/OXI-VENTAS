<script setup>
import { computed, nextTick, useAttrs } from 'vue'
import { fieldRegistry } from '@/Validation/fieldRegistry'
import { sanitizeField } from '@/Validation/sanitizers'

defineOptions({
    inheritAttrs: false,
})

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
    maxHeight: {
        type: [Number, String],
        default: 140,
    },
})

const emit = defineEmits(['update:modelValue', 'validate'])
const attrs = useAttrs()

const textareaId = computed(() =>
    props.field || props.label?.toLowerCase().replace(/\s+/g, '-')
)

const fieldConfig = computed(() => fieldRegistry[props.field])

const textareaMaxHeight = computed(() => {
    return typeof props.maxHeight === 'number'
        ? `${props.maxHeight}px`
        : props.maxHeight
})

function resizeTextarea(textarea) {
    if (!props.autoResize || !textarea) return

    textarea.style.height = 'auto'

    const scrollHeight = textarea.scrollHeight
    const maxHeight = parseInt(textareaMaxHeight.value, 10)

    if (scrollHeight > maxHeight) {
        textarea.style.height = `${maxHeight}px`
        textarea.style.overflowY = 'auto'
        return
    }

    textarea.style.height = `${scrollHeight}px`
    textarea.style.overflowY = 'hidden'
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
        <label :for="textareaId" class="mb-1 block text-sm font-semibold text-text">
            {{ label }}
        </label>

        <textarea v-bind="attrs" :id="textareaId" :name="field" :value="modelValue" :rows="rows" :readonly="readonly"
            :style="{ maxHeight: textareaMaxHeight }" @keydown="blockExtraInput" @input="handleInput"
            @blur="emit('validate', field)" :class="[
                'w-full resize-none rounded-xl border px-4 py-3 text-sm outline-none transition focus:ring-2 focus:ring-primary',
                readonly ? 'cursor-not-allowed border-secondary bg-secondary text-text opacity-60' : 'bg-background text-text',
                error ? 'border-primary bg-secondary' : 'border-secondary focus:border-primary'
            ]" />

        <div class="flex justify-between items-center mt-1">
            <p v-if="error" class="text-xs text-primary">
                {{ error }}
            </p>

            <p v-if="fieldConfig?.max" class="ml-auto text-[11px] text-text opacity-50">
                {{ (modelValue || '').toString().length }}/{{ fieldConfig.max }}
            </p>
        </div>
    </div>
</template>
