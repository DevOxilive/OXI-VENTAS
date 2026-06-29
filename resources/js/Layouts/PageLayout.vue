<script setup>
defineProps({
    padded: {
        type: Boolean,
        default: true,
    },
})
</script>

<template>
    <div class="px-4 py-4 md:px-8 md:py-6">
        <div class="min-h-[calc(100dvh-120px)]" :class="padded ? 'space-y-5' : ''">
            <div v-if="$slots.toolbar">
                <slot name="toolbar" />
            </div>

            <slot />

            <div v-if="$slots.footer">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>