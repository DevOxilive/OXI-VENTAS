<script setup>
defineProps({
    columns: {
        type: [Number, String],
        default: 2
    }
})

const gridClass = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 xl:grid-cols-2',
    3: 'grid-cols-1 lg:grid-cols-2 xl:grid-cols-3'
}
</script>

<template>
    <div class="flex-1 min-h-0 overflow-y-auto p-4 sm:p-5 md:p-8 bg-slate-50">
        <div class="grid gap-6 min-h-full" :class="gridClass[columns] || gridClass[2]">
            <slot />
        </div>
    </div>
</template>
