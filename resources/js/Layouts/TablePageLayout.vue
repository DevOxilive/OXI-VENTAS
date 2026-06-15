<script setup>
defineProps({
    scrollable: {
        type: Boolean,
        default: true,
    },
    maxHeight: {
        type: String,
        default: '520px',
    },
    padded: {
        type: Boolean,
        default: true,
    },
})
</script>

<template>
    <div class="relative px-8 py-6">
        <div class="bg-[#f8f5f9] rounded-3xl min-h-[calc(100vh-180px)]" :class="padded ? 'p-6' : ''">
            <div v-if="$slots.toolbar">
                <slot name="toolbar" />
            </div>

            <div class="mt-5 bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <div :class="scrollable ? 'overflow-y-auto' : ''" :style="scrollable ? { maxHeight } : {}">
                    <slot />
                </div>
            </div>

            <div v-if="$slots.footer" class="mt-6">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>