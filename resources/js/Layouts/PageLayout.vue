<script setup>
defineProps({
    padded: {
        type: Boolean,
        default: true,
    },
    fillHeight: {
        type: Boolean,
        default: false,
    },
})
</script>

<template>
    <div class="h-full min-h-0 px-4 py-4 md:px-8 md:py-6">
        <div
            class="min-h-full"
            :class="[
                padded ? 'space-y-5' : '',
                fillHeight ? 'flex h-full min-h-0 flex-col' : '',
            ]"
        >
            <div v-if="$slots.toolbar" :class="fillHeight ? 'shrink-0' : ''">
                <slot name="toolbar" />
            </div>

            <div v-if="fillHeight" class="min-h-0 flex flex-1 flex-col">
                <slot />
            </div>

            <slot v-else />

            <div v-if="$slots.footer" :class="fillHeight ? 'shrink-0' : ''">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>
