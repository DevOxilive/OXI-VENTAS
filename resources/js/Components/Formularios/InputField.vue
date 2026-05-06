<script setup>
defineProps({
    label: String,
    modelValue: [String, Number],
    error: String,
    type: {
        type: String,
        default: 'text'
    },
    readonly: Boolean
})

const emit = defineEmits(['update:modelValue', 'validate'])
</script>

<template>
    <div>
        <label class="block text-sm font-semibold mb-1 text-slate-700">{{ label }}</label>

        <input :type="type" :value="modelValue" :readonly="readonly"
            @input="emit('update:modelValue', $event.target.value)" @keyup="emit('validate')" @blur="emit('validate')"
            :class="[
                'w-full px-4 py-3 rounded-xl border outline-none transition text-sm',
                readonly ? 'bg-slate-100 cursor-not-allowed' : 'bg-white',
                error ? 'border-red-500 bg-red-50' : 'border-slate-300 focus:border-[#1f1d2b]'
            ]">

        <p v-if="error" class="text-red-500 text-xs mt-1">{{ error }}</p>
    </div>
</template>