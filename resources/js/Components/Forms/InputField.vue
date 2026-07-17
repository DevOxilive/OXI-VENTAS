<script setup>
import { computed, ref, useAttrs } from 'vue'
import { fieldRegistry } from '@/Validation/fieldRegistry'
import { sanitizeField } from '@/Validation/sanitizers'

defineOptions({
    inheritAttrs: false,
})

const props = defineProps({
    label: String,
    field: String,
    validationField: String,
    modelValue: [String, Number],
    error: String,
    hideLabel: Boolean,
    type: {
        type: String,
        default: 'text'
    },
    readonly: Boolean,

    icon: String,
    prefix: String,
    suffix: String,
    placeholder: String,
    showCounter: {
        type: Boolean,
        default: true,
    },
})

const emit = defineEmits(['update:modelValue', 'validate', 'keydown'])
const inputEl = ref(null)
const attrs = useAttrs()

const inputId = computed(() =>
    props.field || props.label?.toLowerCase().replace(/\s+/g, '-')
)

const fieldConfig = computed(() => fieldRegistry[props.validationField || props.field])
const normalizedFieldConfig = computed(() => {
    const config = fieldConfig.value ?? {}
    const effectiveType = config.type ?? props.type
    const shouldAutoTitleCase =
        effectiveType === 'text' &&
        !config.uppercase &&
        !config.preserveCase &&
        config.titleCase !== false

    return {
        ...config,
        type: effectiveType,
        titleCase: shouldAutoTitleCase || config.titleCase === true,
    }
})

const isDateField = computed(() =>
    props.type === 'date' || normalizedFieldConfig.value?.type === 'date'

)
const hasLeftAddon = computed(() => props.icon || props.prefix)
const hasRightAddon = computed(() => props.suffix
)
const isDisabled = computed(() => Boolean(attrs.disabled))

function preventNumberWheel(e) {
    if (props.type !== 'number') return

    e.preventDefault()
    e.target.blur()
}

function handleInput(e) {
    const config = normalizedFieldConfig.value

    if (isDateField.value) {
        emit('update:modelValue', e.target.value)
        emit('validate', props.field)
        return
    }

    const value = sanitizeField(e.target.value, config)

    e.target.value = value

    emit('update:modelValue', value)
    emit('validate', props.field)
}

defineExpose({
    focus: () => inputEl.value?.focus(),
})

function blockExtraInput(e) {
    if (isDateField.value) {
        return
    }

    const config = normalizedFieldConfig.value
    if (!config) return

    const allowedKeys = [
        'Backspace', 'Delete', 'ArrowLeft', 'ArrowRight',
        'ArrowUp', 'ArrowDown', 'Tab', 'Home', 'End'
    ]

    if (e.ctrlKey || e.metaKey) return
    if (allowedKeys.includes(e.key)) return

    const currentLength = (props.modelValue || '').toString().length

    if (config.type === 'decimal' && e.key === '.') {
        if ((props.modelValue || '').toString().includes('.')) {
            e.preventDefault()
        }

        return
    }

    if (config.max && currentLength >= config.max) {
        e.preventDefault()
    }
}
</script>

<template>
    <div class="relative">
        <label v-if="!hideLabel" :for="inputId" class="mb-1 block text-sm font-semibold text-text">
            {{ label }}
        </label>
        <div class="relative">
            <span v-if="hasLeftAddon"
                class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm text-text opacity-60">
                <span v-if="prefix">
                    {{ prefix }}
                </span>

                <span v-else class="material-symbols-outlined text-[20px]">
                    {{ icon }}
                </span>
            </span>
            <input ref="inputEl" v-bind="attrs" :id="inputId" :name="field" :type="type" :placeholder="placeholder" :value="modelValue"
                :readonly="readonly" @keydown="(e) => { blockExtraInput(e); emit('keydown', e) }"
                @wheel="preventNumberWheel" @input="handleInput" @blur="emit('validate', field)" :class="[
                    'w-full rounded-xl border py-3 text-sm outline-none transition focus:ring-2 focus:ring-primary',
                    hasLeftAddon ? 'pl-11 pr-4' : 'px-4',
                    hasRightAddon ? 'pr-12' : '',
                    readonly || isDisabled ? 'cursor-not-allowed border-secondary bg-secondary text-text opacity-60' : 'bg-background text-text',
                    error ? 'border-primary bg-secondary' : 'border-secondary focus:border-primary'
                ]" />
            <span v-if="suffix"
                class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-xs font-semibold text-text opacity-60">
                {{ suffix }}
            </span>
        </div>
        <div class="flex justify-between items-center mt-1">
            <p v-if="error" class="text-xs text-primary">
                {{ error }}
            </p>

            <p v-if="showCounter && normalizedFieldConfig?.max" class="ml-auto text-[11px] text-text opacity-50">
                {{ (modelValue || '').toString().length }}/{{ normalizedFieldConfig.max }}
            </p>
        </div>
    </div>
</template>
