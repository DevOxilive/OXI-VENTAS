<script setup>
import { computed } from 'vue'
import { fieldRegistry } from '@/Validation/fieldRegistry'

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
        <label v-if="!hideLabel" :for="selectId" class="block text-sm font-semibold mb-1 text-slate-700">
            {{ label }}
        </label>

        <select :id="selectId" :name="field" :value="modelValue" :disabled="disabled" @change="handleChange"
            @blur="handleBlur" :class="[
                'w-full px-4 py-3 rounded-xl border outline-none text-sm transition',
                disabled ? 'bg-slate-100 cursor-not-allowed' : 'bg-white',
                error ? 'border-red-500 bg-red-50' : 'border-slate-300 focus:border-[#1f1d2b]'
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
            <p v-if="error" class="text-red-500 text-xs">
                {{ error }}
            </p>

            <p v-if="fieldConfig?.required" class="text-[11px] text-slate-400 ml-auto">
                Obligatorio
            </p>
        </div>
    </div>
</template>
