<script setup>
import { computed, useAttrs } from 'vue'
import { fieldRegistry } from '@/Validation/fieldRegistry'

defineOptions({
    inheritAttrs: false,
})

const selectId = computed(() =>
    props.field || props.label?.toLowerCase().replace(/\s+/g, '-')
)

const props = defineProps({
    label: String,
    field: String,
    modelValue: [String, Number],
    error: String,
    hideLabel: Boolean,
    options: {
        type: Array,
        default: () => []
    },
    placeholder: {
        type: String,
        default: 'Seleccione una opción'
    },
    disabled: Boolean
})

const emit = defineEmits(['update:modelValue', 'validate', 'change'])
const attrs = useAttrs()

const fieldConfig = computed(() => fieldRegistry[props.field])

function getOptionValue(item) {
    if (item && typeof item === 'object') {
        return item.value ?? item.id ?? item.name ?? ''
    }

    return item
}

function getOptionLabel(item) {
    if (item && typeof item === 'object') {
        return item.label ?? item.name ?? item.value ?? item.id ?? ''
    }

    return item
}

function handleChange(e) {
    emit('update:modelValue', e.target.value)
    emit('validate', props.field)
    emit('change', e.target.value)
}

function handleBlur() {
    emit('validate', props.field)
}
</script>

<template>
    <div>
        <label v-if="!hideLabel" :for="selectId" class="mb-1 block text-sm font-semibold text-text">
            {{ label }}
        </label>

        <select v-bind="attrs" :id="selectId" :name="field" :value="modelValue" :disabled="disabled" @change="handleChange"
            @blur="handleBlur" :class="[
                'w-full rounded-xl border px-4 py-3 text-sm outline-none transition focus:ring-2 focus:ring-primary',
                disabled ? 'cursor-not-allowed border-secondary bg-secondary text-text opacity-60' : 'bg-background text-text',
                error ? 'border-primary bg-secondary' : 'border-secondary focus:border-primary'
            ]">
            <option disabled value="">
                {{ placeholder }}
            </option>

            <option v-for="item in options" :key="getOptionValue(item)"
                :value="getOptionValue(item)">
                {{ getOptionLabel(item) }}
            </option>
        </select>

        <div class="flex justify-between items-center mt-1">
            <p v-if="error" class="text-xs text-primary">
                {{ error }}
            </p>

            <p v-if="fieldConfig?.required" class="ml-auto text-[11px] text-text opacity-50">
                Obligatorio
            </p>
        </div>
    </div>
</template>
