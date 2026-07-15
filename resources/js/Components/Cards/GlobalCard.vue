<script setup>
const props = defineProps({
    title: {
        type: String,
        required: true,
    },

    subtitle: {
        type: String,
        default: '',
    },

    description: {
        type: String,
        default: '',
    },

    icon: {
        type: String,
        default: 'description',
    },

    image: {
        type: String,
        default: '',
    },

    badge: {
        type: String,
        default: '',
    },

    badgeVariant: {
        type: String,
        default: 'neutral',
    },

    disabled: {
        type: Boolean,
        default: false,
    },

    clickable: {
        type: Boolean,
        default: true,
    },
})

const badgeClasses = {
    neutral: 'bg-secondary text-text',
    info: 'bg-secondary text-primary',
    success: 'bg-secondary text-accent',
    warning: 'bg-secondary text-accent',
    danger: 'bg-secondary text-primary',
}
</script>

<template>
    <component :is="clickable ? 'button' : 'article'" type="button" :disabled="clickable ? disabled : undefined"
        class="group w-full rounded-2xl border bg-background p-5 text-left shadow-sm transition" :class="[
            disabled
                ? 'cursor-not-allowed border-secondary opacity-60'
                : clickable
                    ? 'border-secondary hover:-translate-y-1 hover:border-primary hover:shadow-md'
                    : 'border-secondary',
        ]">
        <img v-if="image" :src="image" :alt="title" class="mb-4 h-32 w-full rounded-xl object-cover" />

        <div class="mb-4 flex items-center justify-between gap-3">
            <span v-if="icon" class="material-symbols-outlined rounded-2xl p-3 text-3xl" :class="disabled
                ? 'bg-secondary text-text opacity-40'
                : 'bg-secondary text-primary'">
                {{ icon }}
            </span>

            <span v-if="badge" class="rounded-full px-3 py-1 text-xs font-black"
                :class="badgeClasses[badgeVariant] ?? badgeClasses.neutral">
                {{ badge }}
            </span>
        </div>

        <p v-if="subtitle" class="text-xs font-black uppercase tracking-[0.18em] text-text opacity-50">
            {{ subtitle }}
        </p>

        <h2 class="text-lg font-black text-text">
            {{ title }}
        </h2>

        <p v-if="description" class="mt-2 text-sm leading-relaxed text-text opacity-70">
            {{ description }}
        </p>

        <slot />
    </component>
</template>
