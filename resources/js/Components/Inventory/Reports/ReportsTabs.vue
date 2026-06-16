<script setup>
defineProps({
    modelValue: {
        type: String,
        required: true,
    },
    tabs: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
        <div class="flex min-w-max gap-2">
            <button v-for="tab in tabs" :key="tab.key" type="button"
                class="rounded-xl px-4 py-2 text-sm font-bold transition" :class="modelValue === tab.key
                    ? 'bg-slate-900 text-white'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'"
                @click="emit('update:modelValue', tab.key)">
                {{ tab.label }}
            </button>
        </div>
    </div>
</template>