<script setup>
defineProps({
    label: String,
    modelValue: [String, Number],
    error: String,
    options: Array
})

const emit = defineEmits(['update:modelValue', 'validate'])
</script>

<template>
    <div>
        <label class="block text-sm font-semibold mb-1 text-slate-700">{{ label }}</label>

        <select :value="modelValue" @change="emit('update:modelValue', $event.target.value)" @blur="emit('validate')"
            :class="[
                'w-full px-4 py-3 rounded-xl border outline-none text-sm',
                error ? 'border-red-500 bg-red-50' : 'border-slate-300 focus:border-[#1f1d2b]'
            ]">
            <option disabled value="">Seleccione una opción</option>
            <option v-for="item in options" :key="item" :value="item">
                {{ item }}
            </option>
        </select>

        <p v-if="error" class="text-red-500 text-xs mt-1">{{ error }}</p>
    </div>
</template>