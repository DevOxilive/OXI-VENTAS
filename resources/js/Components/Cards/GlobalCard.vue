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
    neutral: 'bg-slate-100 text-slate-500',
    info: 'bg-blue-50 text-blue-600',
    success: 'bg-emerald-50 text-emerald-600',
    warning: 'bg-amber-50 text-amber-700',
    danger: 'bg-red-50 text-red-600',
}
</script>

<template>
    <component :is="clickable ? 'button' : 'article'" type="button" :disabled="clickable ? disabled : undefined"
        class="group w-full rounded-2xl border bg-white p-5 text-left shadow-sm transition" :class="[
            disabled
                ? 'cursor-not-allowed border-slate-200 opacity-60'
                : clickable
                    ? 'border-slate-200 hover:-translate-y-1 hover:border-blue-300 hover:shadow-md'
                    : 'border-slate-200',
        ]">
        <img v-if="image" :src="image" :alt="title" class="mb-4 h-32 w-full rounded-xl object-cover" />

        <div class="mb-4 flex items-center justify-between gap-3">
            <span v-if="icon" class="material-symbols-outlined rounded-2xl p-3 text-3xl" :class="disabled
                ? 'bg-slate-100 text-slate-400'
                : 'bg-blue-50 text-blue-600'">
                {{ icon }}
            </span>

            <span v-if="badge" class="rounded-full px-3 py-1 text-xs font-black"
                :class="badgeClasses[badgeVariant] ?? badgeClasses.neutral">
                {{ badge }}
            </span>
        </div>

        <p v-if="subtitle" class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">
            {{ subtitle }}
        </p>

        <h2 class="text-lg font-black text-slate-900">
            {{ title }}
        </h2>

        <p v-if="description" class="mt-2 text-sm leading-relaxed text-slate-500">
            {{ description }}
        </p>

        <slot />
    </component>
</template>