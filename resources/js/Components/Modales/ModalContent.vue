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
        class="min-h-0 flex-1 bg-white p-4 sm:p-5 md:p-6"
        :class="[
            scrollMode === 'controlled' ? 'overflow-hidden' : 'overflow-y-auto',
            contentClass,
        ]"
    >
        <div
            class="grid gap-6"
            :class="[
                gridClass[columns] || gridClass[2],
                scrollMode === 'controlled' ? 'h-full min-h-0' : 'min-h-full',
            ]"
        >
            <slot />
        </div>
    </main>
</template>
