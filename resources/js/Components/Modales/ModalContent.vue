<script setup>
defineProps({
    columns: {
        type: [Number, String],
        default: 2,
    },
    scrollMode: {
        type: String,
        default: 'auto',
    },
    contentClass: {
        type: [String, Array, Object],
        default: '',
    },
})

const gridClass = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 xl:grid-cols-2',
    3: 'grid-cols-1 lg:grid-cols-2 xl:grid-cols-3',
}
</script>

<template>
    <main
        class="min-h-0 flex-1 overflow-y-auto overscroll-contain bg-white p-4 touch-pan-y sm:p-5 md:p-6"
        :class="[
            scrollMode === 'controlled' ? 'overflow-hidden' : '',
            contentClass,
        ]"
        @click.stop
        @wheel.stop
        @touchmove.stop
    >
        <div
            class="grid gap-6"
            :class="[
                gridClass[columns] || gridClass[2],
                scrollMode === 'controlled' ? 'h-full min-h-0' : 'min-h-0',
            ]"
        >
            <slot />
        </div>
    </main>
</template>
