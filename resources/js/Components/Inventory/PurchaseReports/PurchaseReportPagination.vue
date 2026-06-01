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
        class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3">
        <p class="text-sm text-slate-500">
            Navegación de productos
        </p>

        <div class="flex flex-wrap gap-2">
            <template v-for="(link, index) in links" :key="index">
                <Link v-if="link.url" :href="link.url" preserve-scroll preserve-state
                    class="rounded-lg border px-3 py-2 text-sm font-semibold transition" :class="link.active
                        ? 'border-slate-900 bg-slate-900 text-white'
                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'" v-html="link.label" />

                <span v-else
                    class="cursor-not-allowed rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-400"
                    v-html="link.label" />
            </template>
        </div>
    </div>
</template>