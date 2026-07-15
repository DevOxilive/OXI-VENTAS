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
    centeredHeading: {
        type: Boolean,
        default: false,
    },
    headingBorder: {
        type: Boolean,
        default: true,
    },
    panelClass: {
        type: [String, Array, Object],
        default: '',
    },
    bodyClass: {
        type: [String, Array, Object],
        default: '',
    },
})
</script>

<template>
    <section
        class="rounded-2xl border border-secondary bg-secondary/60 p-4"
        :class="panelClass"
    >
        <SectionHeading
            v-if="title || description || $slots.title || $slots.description || $slots.header"
            :title="title"
            :description="description"
            :centered="centeredHeading"
            :bordered="headingBorder"
            spacing="lg"
        >
            <template v-if="$slots.title" #title>
                <slot name="title" />
            </template>

            <template v-if="$slots.description" #description>
                <slot name="description" />
            </template>

            <template v-if="$slots.header" #aside>
                <slot name="header" />
            </template>
        </SectionHeading>

        <div :class="bodyClass">
            <slot />
        </div>
    </section>
</template>
