<script setup>
import PhysicalCountCard from './PhysicalCountCard.vue'

defineProps({
    physicalCounts: {
        type: Array,
        default: () => []
    }
})
</script>

<template>
    <div class="space-y-3">
        <div
            v-if="physicalCounts.length === 0"
            class="rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center"
        >
            <p class="text-sm text-gray-500">
              Todavía no hay conteos físicos creados.
            </p>
        </div>

        <PhysicalCountCard
            v-for="physicalCount in physicalCounts"
            :key="physicalCount.id"
            :physical-count="physicalCount"
        />
    </div>
</template>