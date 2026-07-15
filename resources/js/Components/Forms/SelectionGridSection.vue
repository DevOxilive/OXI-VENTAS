<script setup>
import SectionHeading from '@/Components/Cards/SectionHeading.vue'

defineProps({
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
    gridClass: {
        type: [String, Array, Object],
        default: 'grid grid-cols-1 gap-4 md:grid-cols-2',
    },
    wrapperClass: {
        type: [String, Array, Object],
        default: '',
    },
})
</script>

<template>
    <div :class="wrapperClass">
        <SectionHeading
            v-if="title || description || $slots.title || $slots.description"
            :title="title"
            :description="description"
            spacing="sm"
        >
            <template v-if="$slots.title" #title>
                <slot name="title" />
            </template>

            <template v-if="$slots.description" #description>
                <slot name="description" />
            </template>

            <template v-if="$slots.aside" #aside>
                <slot name="aside" />
            </template>
        </SectionHeading>

        <div :class="gridClass">
            <slot />
        </div>
    </div>
</template>
