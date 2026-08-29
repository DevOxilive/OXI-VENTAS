<script setup>
import { computed, nextTick, useAttrs } from 'vue'
import { fieldRegistry } from '@/Validation/fieldRegistry'
import { sanitizeField, sanitizeFieldWithCursor } from '@/Validation/sanitizers'

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
    preserveCase: Boolean,
    titleCase: {
        type: Boolean,
        default: undefined,
    },
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
const normalizedFieldConfig = computed(() => {
    const config = fieldConfig.value ?? {}

    return {
        ...config,
        type: config.type ?? 'text',
        preserveCase: props.preserveCase || config.preserveCase,
        titleCase: props.titleCase ?? config.titleCase ?? false,
    }
})

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
    const sanitized = sanitizeFieldWithCursor(
        e.target.value,
        normalizedFieldConfig.value,
        e.target.selectionStart ?? 0,
        e.target.selectionEnd ?? 0,
    )

    if (e.target.value !== sanitized.value) {
        e.target.value = sanitized.value
    }

    emit('update:modelValue', sanitized.value)
    emit('validate', props.field)

    nextTick(() => {
        e.target.setSelectionRange?.(sanitized.selectionStart, sanitized.selectionEnd)
        resizeTextarea(e.target)
    })
}

function handleBlur(e) {
    const value = sanitizeField(e.target.value, normalizedFieldConfig.value)

    if (value !== e.target.value) {
        e.target.value = value
        emit('update:modelValue', value)
    }

    emit('validate', props.field)
}

</script>

<template>
    <div>
        <label :for="textareaId" class="mb-1 block text-sm font-semibold text-text">
            {{ label }}
        </label>

        <textarea v-bind="attrs" :id="textareaId" :name="field" :value="modelValue" :rows="rows" :readonly="readonly"
            :maxlength="normalizedFieldConfig?.max || undefined"
            :style="{ maxHeight: textareaMaxHeight }" @input="handleInput"
            @blur="handleBlur" :class="[
                'w-full resize-none rounded-xl border px-4 py-3 text-sm outline-none transition focus:ring-2 focus:ring-primary',
                readonly ? 'cursor-not-allowed border-secondary bg-secondary text-text opacity-60' : 'bg-background text-text',
                error ? 'border-primary bg-secondary' : 'border-secondary focus:border-primary'
            ]" />

        <div class="flex justify-between items-center mt-1">
            <p v-if="error" class="text-xs text-primary">
                {{ error }}
            </p>

            <p v-if="normalizedFieldConfig?.max" class="ml-auto text-[11px] text-text opacity-50">
                {{ (modelValue || '').toString().length }}/{{ normalizedFieldConfig.max }}
            </p>
        </div>
    </div>
</template>
