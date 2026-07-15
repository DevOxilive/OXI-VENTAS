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
    primary: 'border border-primary bg-primary text-white shadow-sm hover:brightness-110 focus:ring-primary',
    secondary: 'border border-secondary bg-secondary text-text shadow-sm hover:brightness-95 focus:ring-accent',
    danger: 'border border-primary bg-primary text-white shadow-sm hover:brightness-95 focus:ring-primary',
}

const buttonClass = computed(() => [
    'inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50',
    props.block ? 'w-full' : '',
    variantClasses[props.variant] || variantClasses.primary,
])
</script>

<template>
    <button :type="type" :class="buttonClass">
        <slot />
    </button>
</template>
