<script setup>
defineProps({
    summary: {
        type: Object,
        default: () => ({}),
    },
})

const cards = [
    { key: 'total_products', label: 'Productos' },
    { key: 'total_stock', label: 'Stock total' },
    { key: 'low_stock', label: 'Stock bajo' },
    { key: 'out_of_stock', label: 'Sin stock' },
    { key: 'expired_batches', label: 'Lotes caducados' },
    { key: 'near_expiration_batches', label: 'Por caducar' },
    { key: 'attention_products', label: 'Requieren atención' },
]
</script>

<template>
    <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
        <article v-for="card in cards" :key="card.key"
            class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">
                {{ card.label }}
            </p>
            <p class="mt-2 text-2xl font-black text-slate-900">
                {{ summary?.[card.key] ?? 0 }}
            </p>
        </article>
    </section>
</template>