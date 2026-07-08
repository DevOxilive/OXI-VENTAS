<script setup>
import { computed } from 'vue'

const props = defineProps({
    type: {
        type: String,
        default: 'button',
    },
    variant: {
        type: String,
        default: 'primary',
    },
    block: {
        type: Boolean,
        default: false,
    },
})

const variantClasses = {
    primary: 'bg-gradient-to-r from-[#8a4b16] via-[#c27a1f] to-[#f0a62b] text-white shadow-sm hover:brightness-105 focus:ring-amber-200',
    secondary: 'border border-slate-200 bg-white text-slate-700 shadow-sm hover:bg-slate-50 focus:ring-amber-200',
    danger: 'bg-red-600 text-white shadow-sm hover:bg-red-500 focus:ring-red-200',
}

const buttonClass = computed(() => [
    'inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
    props.block ? 'w-full' : '',
    variantClasses[props.variant] || variantClasses.primary,
])
</script>

<template>
    <button :type="type" :class="buttonClass">
        <slot />
    </button>
</template>
