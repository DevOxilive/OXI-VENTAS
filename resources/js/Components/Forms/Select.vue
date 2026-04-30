<script setup>
import { ref, watch, defineProps, defineEmits } from 'vue';
import InputLabel from './InputLabel.vue';

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
        <InputLabel :for="id">{{ label }}: <span v-if="required" class="text-red-600">*</span></InputLabel>
        <select :id="id" v-model="selectValue" :required="required" :disabled="disabled"
            class="block appearance-none w-full bg-gray-200 border border-gray-200 text-gray-700 py-2 px-4 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500">
            <option value="" selected :disabled="required">{{ defaultTextSelected }}</option>
            <template v-if="data">
                <option v-for="d in data" :value="d" :key="d">{{ d }}</option>
            </template>
            <slot/>
        </select>
        <p class="mt-3 ml-3" v-if="isTesting">{{ modelValue }}</p>
    </div>
</template>
