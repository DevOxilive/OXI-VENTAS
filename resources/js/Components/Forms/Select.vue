<script setup>
import { ref, watch, defineProps, defineEmits } from 'vue';

const props = defineProps({
    modelValue: {
        type: [String, Number, Boolean],
        required: true
    },
    label: {
        type: String,
        default: 'Etiqueta'
    },
    data: {
        type: Array
    },
    id: {
        type: String,
        required: false
    },
    defaultTextSelected: {
        type: String,
        default: 'Selecciona una opción'
    },
    required: {
        type: Boolean,
        default: false
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    isTesting: {
        type: Boolean,
        default: false,
    }
});

const emits = defineEmits(['update:modelValue']);
const selectValue = ref(props.modelValue);

watch(selectValue, (newValue) => {
    emits('update:modelValue', newValue);
});

watch(() => props.modelValue, (newValue) => {
    selectValue.value = newValue;
});
</script>

<template>
    <div class="mb-4">
        <label :for="id" class="mb-1 block text-sm font-semibold text-text">
            {{ label }}: <span v-if="required" class="text-primary">*</span>
        </label>
        <select
            :id="id"
            v-model="selectValue"
            :required="required"
            :disabled="disabled"
            class="block w-full appearance-none rounded border border-secondary bg-secondary px-4 py-2 leading-tight text-text focus:border-primary focus:bg-background focus:outline-none focus:ring-2 focus:ring-primary"
        >
            <option value="" selected :disabled="required">{{ defaultTextSelected }}</option>
            <template v-if="data">
                <option v-for="d in data" :key="d" :value="d">{{ d }}</option>
            </template>
            <slot />
        </select>
        <p v-if="isTesting" class="mt-3 ml-3">{{ modelValue }}</p>
    </div>
</template>
