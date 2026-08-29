<script setup>
import { computed, nextTick, ref, useAttrs, watch } from 'vue'
import { fieldRegistry } from '@/Validation/fieldRegistry'
import {
    formatCurrencyValue,
    sanitizeCurrencyWithCursor,
    sanitizeField,
    sanitizeFieldWithCursor,
} from '@/Validation/sanitizers'

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
    preserveCase: Boolean,
    titleCase: {
        type: Boolean,
        default: undefined,
    },

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
const displayValue = ref('')
const attrs = useAttrs()

const inputId = computed(() =>
    props.field || props.label?.toLowerCase().replace(/\s+/g, '-')
)

const fieldConfig = computed(() => fieldRegistry[props.validationField || props.field])
const normalizedFieldConfig = computed(() => {
    const config = fieldConfig.value ?? {}
    const effectiveType = config.type ?? props.type
    return {
        ...config,
        type: effectiveType,
        preserveCase: props.preserveCase || config.preserveCase,
        titleCase: props.titleCase ?? config.titleCase ?? false,
    }
})

const isDateField = computed(() =>
    props.type === 'date' || normalizedFieldConfig.value?.type === 'date'

)
const hasLeftAddon = computed(() => props.icon || props.prefix)
const hasRightAddon = computed(() => props.suffix
)
const isDisabled = computed(() => Boolean(attrs.disabled))
const isTextarea = computed(() => props.type === 'textarea')
const isCurrencyField = computed(() => normalizedFieldConfig.value.format === 'currency')

watch(
    [() => props.modelValue, normalizedFieldConfig],
    ([value, config]) => {
        displayValue.value = config.format === 'currency'
            ? formatCurrencyValue(value, config)
            : String(value ?? '')
    },
    { immediate: true },
)

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

    if (isCurrencyField.value) {
        const sanitized = sanitizeCurrencyWithCursor(
            e.target.value,
            config,
            e.target.selectionStart ?? 0,
            e.target.selectionEnd ?? 0,
        )

        displayValue.value = sanitized.value
        e.target.value = sanitized.value
        emit('update:modelValue', sanitized.rawValue)
        emit('validate', props.field)

        nextTick(() => {
            e.target.setSelectionRange?.(sanitized.selectionStart, sanitized.selectionEnd)
        })
        return
    }

    const sanitized = sanitizeFieldWithCursor(
        e.target.value,
        config,
        e.target.selectionStart ?? 0,
        e.target.selectionEnd ?? 0,
    )

    if (e.target.value !== sanitized.value) {
        e.target.value = sanitized.value
    }

    emit('update:modelValue', sanitized.value)
    emit('validate', props.field)

    nextTick(() => {
        // Los campos numericos no permiten mover el cursor con setSelectionRange.
        if (e.target.type !== 'number') {
            e.target.setSelectionRange?.(sanitized.selectionStart, sanitized.selectionEnd)
        }
    })
}

function handleBlur(e) {
    if (isCurrencyField.value) {
        const value = sanitizeField(e.target.value, normalizedFieldConfig.value)
        const formattedValue = formatCurrencyValue(value, normalizedFieldConfig.value, {
            fixedDecimals: Boolean(value),
        })

        displayValue.value = formattedValue
        e.target.value = formattedValue
        emit('update:modelValue', value)
        emit('validate', props.field)
        return
    }

    if (!isDateField.value) {
        const value = sanitizeField(e.target.value, normalizedFieldConfig.value)

        if (value !== e.target.value) {
            e.target.value = value
            emit('update:modelValue', value)
        }
    }

    emit('validate', props.field)
}

defineExpose({
    focus: () => inputEl.value?.focus(),
})

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
            <textarea v-if="isTextarea" ref="inputEl" v-bind="attrs" :id="inputId" :name="field" :placeholder="placeholder" :value="modelValue"
                :maxlength="normalizedFieldConfig?.max || undefined"
                :readonly="readonly" @keydown="emit('keydown', $event)"
                @input="handleInput" @blur="handleBlur" :class="[
                    'min-h-28 w-full resize-y rounded-xl border py-3 text-sm outline-none transition focus:ring-2 focus:ring-primary',
                    hasLeftAddon ? 'pl-11 pr-4' : 'px-4',
                    hasRightAddon ? 'pr-12' : '',
                    readonly || isDisabled ? 'cursor-not-allowed border-secondary bg-secondary text-text opacity-60' : 'bg-background text-text',
                    error ? 'border-primary bg-secondary' : 'border-secondary focus:border-primary'
                ]" />
            <input v-else ref="inputEl" v-bind="attrs" :id="inputId" :name="field" :type="type" :placeholder="placeholder"
                :value="isCurrencyField ? displayValue : modelValue"
                :maxlength="isDateField || isCurrencyField ? undefined : (normalizedFieldConfig?.max || undefined)"
                :readonly="readonly" @keydown="emit('keydown', $event)"
                @wheel="preventNumberWheel" @input="handleInput" @blur="handleBlur" :class="[
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
