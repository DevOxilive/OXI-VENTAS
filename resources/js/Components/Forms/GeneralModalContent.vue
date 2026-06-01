<script setup>
defineProps({
    columns: {
        type: [Number, String],
        default: 2,
    },
    scrollMode: {
        type: String,
        default: 'auto', // auto | controlled
    },
})

const gridClass = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 xl:grid-cols-2',
    3: 'grid-cols-1 lg:grid-cols-2 xl:grid-cols-3',
}
</script>
<template>
    <div class="flex-1 min-h-0 p-4 sm:p-5 md:p-8 bg-slate-50" :class="scrollMode === 'controlled'
        ? 'overflow-hidden'
        : 'overflow-y-auto'">
        <div class="grid gap-6" :class="[
            gridClass[columns] || gridClass[2],
            scrollMode === 'controlled'
                ? 'h-full min-h-0'
                : 'min-h-full',
        ]">
            <slot />
        </div>
    </div>
</template>
