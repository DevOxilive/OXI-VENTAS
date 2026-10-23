<script setup>
defineProps({
    title: String,
    description: String,
    modelValue: String,
    tabs: {
        type: Array,
        default: () => [
            { value: "content", label: "Contenido", icon: "edit_note" },
            { value: "paper", label: "Papel", icon: "straighten" },
            { value: "design", label: "Diseño", icon: "tune" },
        ],
    },
});
defineEmits(["update:modelValue"]);
</script>
<template>
    <div class="template-workspace space-y-5 text-text">
        <header
            class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-secondary bg-background p-5"
        >
            <div>
                <h2 class="text-lg font-bold">{{ title }}</h2>
                <p class="mt-1 max-w-2xl text-sm opacity-70">
                    {{ description }}
                </p>
            </div>
            <slot name="status" />
        </header>
        <div
            class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(340px,0.9fr)]"
        >
            <section
                class="min-w-0 rounded-2xl border border-secondary bg-background shadow-sm"
            >
                <nav
                    aria-label="Configuración de plantilla"
                    class="flex gap-1 overflow-x-auto border-b border-secondary p-2"
                >
                    <button
                        v-for="tab in tabs"
                        :key="tab.value"
                        type="button"
                        :aria-pressed="modelValue === tab.value"
                        class="flex flex-1 items-center justify-center gap-2 whitespace-nowrap rounded-xl px-4 py-3 text-sm font-semibold transition"
                        :class="
                            modelValue === tab.value
                                ? 'bg-primary text-white'
                                : 'text-text hover:bg-secondary'
                        "
                        @click="$emit('update:modelValue', tab.value)"
                    >
                        <span class="material-symbols-outlined text-lg">{{
                            tab.icon
                        }}</span
                        >{{ tab.label }}
                    </button>
                </nav>
                <div class="space-y-5 p-5 sm:p-6"><slot /></div>
            </section>
            <div class="min-w-0 lg:sticky lg:top-4">
                <slot name="preview" />
            </div>
        </div>
    </div>
</template>
<style scoped>
.template-workspace :deep(.print-field) {
    display: block;
    font-size: 0.875rem;
    font-weight: 600;
}
.template-workspace :deep(.print-field input),
.template-workspace :deep(.print-field select) {
    display: block;
    width: 100%;
    margin-top: 0.5rem;
    border: 1px solid var(--color-secondary, #d1d5db);
    border-radius: 0.75rem;
    padding: 0.65rem 0.8rem;
    background: transparent;
    color: inherit;
}
.template-workspace :deep(.print-check) {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.875rem;
    padding: 0.75rem 0;
}
</style>
