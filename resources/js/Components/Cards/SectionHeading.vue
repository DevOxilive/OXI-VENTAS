<script setup>
defineProps({
    title: {
        type: String,
        default: '',
    },
    description: {
        type: String,
        default: '',
    },
    centered: {
        type: Boolean,
        default: false,
    },
    bordered: {
        type: Boolean,
        default: false,
    },
    spacing: {
        type: String,
        default: 'md',
    },
})

const spacingClasses = {
    sm: 'mb-3',
    md: 'mb-4',
    lg: 'mb-6',
}
</script>

<template>
    <div
        :class="[
            centered ? 'text-center' : '',
            bordered ? 'border-b border-secondary pb-3' : '',
            spacingClasses[spacing] ?? spacingClasses.md,
        ]"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
                <h3 v-if="title || $slots.title" class="text-base font-bold text-text">
                    <slot name="title">
                        {{ title }}
                    </slot>
                </h3>

                <p
                    v-if="description || $slots.description"
                    class="mt-1 text-sm text-text opacity-70"
                >
                    <slot name="description">
                        {{ description }}
                    </slot>
                </p>
            </div>

            <div v-if="$slots.aside" class="shrink-0">
                <slot name="aside" />
            </div>
        </div>
    </div>
</template>
