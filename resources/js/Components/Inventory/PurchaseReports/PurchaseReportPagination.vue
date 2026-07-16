<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
    links: {
        type: Array,
        default: () => [],
    },
})
</script>

<template>
    <div v-if="links.length > 3"
        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-secondary bg-background px-4 py-3">
        <p class="text-sm text-text opacity-70">
            Navegación de productos
        </p>

        <div class="flex flex-wrap gap-2">
            <template v-for="(link, index) in links" :key="index">
                <Link v-if="link.url" :href="link.url" preserve-scroll preserve-state
                    class="rounded-lg border px-3 py-2 text-sm font-semibold transition" :class="link.active
                        ? 'border-primary bg-primary text-white'
                        : 'border-secondary bg-background text-text hover:bg-secondary'" v-html="link.label" />

                <span v-else
                    class="cursor-not-allowed rounded-lg border border-secondary bg-secondary px-3 py-2 text-sm font-semibold text-text opacity-50"
                    v-html="link.label" />
            </template>
        </div>
    </div>
</template>
