<script setup>
import { computed } from 'vue'

const props = defineProps({
    sections: {
        type: Array,
        default: () => [],
    },
    activeSection: {
        type: [Number, String],
        default: null,
    },
})

const gridClass = computed(() => ({
    1: 'grid-cols-1',
    2: 'grid-cols-2',
    3: 'grid-cols-3',
    4: 'grid-cols-4',
}[Math.min(props.sections.length, 4)] ?? 'grid-cols-1'))
</script>

<template>
    <nav
        v-if="sections.length"
        class="mt-5 grid divide-x divide-secondary border-y border-secondary"
        :class="gridClass"
        aria-label="Secciones del formulario"
    >
        <div
            v-for="(section, index) in sections"
            :key="section.id ?? index"
            class="flex min-w-0 items-center gap-2 px-3 py-3 text-xs font-semibold md:px-5 md:text-sm"
            :class="String(section.id ?? index) === String(activeSection)
                ? 'text-primary'
                : 'text-text opacity-55'"
        >
            <span
                class="grid h-6 w-6 shrink-0 place-items-center rounded-full border text-[11px]"
                :class="String(section.id ?? index) === String(activeSection)
                    ? 'border-primary bg-primary text-white'
                    : 'border-secondary bg-background text-text'"
            >
                {{ index + 1 }}
            </span>
            <span class="truncate">{{ section.label }}</span>
        </div>
    </nav>
</template>
