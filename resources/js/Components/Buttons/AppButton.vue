<script setup>
import { computed } from 'vue'

const props = defineProps({
    as: {
        type: String,
        default: 'button',
    },
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
    success: 'border border-green-600 bg-green-600 text-white shadow-sm hover:brightness-110 focus:ring-green-600',
    danger: 'border border-primary bg-primary text-white shadow-sm hover:brightness-95 focus:ring-primary',
}

const buttonClass = computed(() => [
    'inline-flex items-center justify-center rounded-xl px-4 py-2.5 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-background disabled:cursor-not-allowed disabled:opacity-50',
    props.block ? 'w-full' : '',
    variantClasses[props.variant] || variantClasses.primary,
])
</script>

<template>
    <component :is="as" :type="as === 'button' ? type : undefined" :class="buttonClass">
        <slot />
    </component>
</template>
