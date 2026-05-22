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
    readonly: Boolean,

    icon: String,
    prefix: String,
    suffix: String,
    placeholder: String,
})

const emit = defineEmits(['update:modelValue', 'validate'])

const inputId = computed(() =>
    props.field || props.label?.toLowerCase().replace(/\s+/g, '-')
)

const fieldConfig = computed(() => fieldRegistry[props.field])

const isDateField = computed(() =>
    props.type === 'date' || fieldConfig.value?.type === 'date'
    
)
const hasLeftAddon = computed(() => props.icon || props.prefix)
const hasRightAddon = computed(() => props.suffix
)

function handleInput(e) {
    const config = fieldConfig.value

    if (isDateField.value) {
        emit('update:modelValue', e.target.value)
        emit('validate', props.field)
        return
    }

    const value = config
        ? sanitizeField(e.target.value, config)
        : e.target.value

    e.target.value = value

    emit('update:modelValue', value)
    emit('validate', props.field)
}

function blockExtraInput(e) {
    if (isDateField.value) {
        e.preventDefault()
        return
    }

    const config = fieldConfig.value
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
        <label :for="inputId" class="block text-sm font-semibold mb-1 text-slate-700">
            {{ label }}
        </label>
<span
    v-if="hasLeftAddon"
    class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none"
>
    <span v-if="prefix">
        {{ prefix }}
    </span>

    <span
        v-else
        class="material-symbols-outlined text-[20px]"
    >
        {{ icon }}
    </span>
</span>
        <input :id="inputId" :name="field" :type="type" :placeholder="placeholder" :value="modelValue" :readonly="readonly"
            :inputmode="isDateField ? 'none' : undefined" @keydown="blockExtraInput" @input="handleInput"
            @blur="emit('validate', field)" :class="[
'w-full py-3 rounded-xl border outline-none transition text-sm',
hasLeftAddon ? 'pl-11 pr-4' : 'px-4',
hasRightAddon ? 'pr-12' : '',                readonly ? 'bg-slate-100 cursor-not-allowed' : 'bg-white',
                error ? 'border-red-500 bg-red-50' : 'border-slate-300 focus:border-[#1f1d2b]'
            ]">
<span
    v-if="suffix"
    class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 text-xs font-semibold pointer-events-none"
>
    {{ suffix }}
</span>
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