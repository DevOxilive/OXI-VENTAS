<script setup>
defineProps({
    stats: {
        type: Object,
        required: true
    }
})

const cards = [
    {
        key: 'total',
        label: 'Productos registrados',
        icon: 'inventory_2',
        color: 'bg-blue-50 text-blue-700 border-blue-100'
    },
    {
        key: 'lowStock',
        label: 'Stock bajo',
        icon: 'warning',
        color: 'bg-amber-50 text-amber-700 border-amber-100'
    },
    {
        key: 'outOfStock',
        label: 'Agotados',
        icon: 'error',
        color: 'bg-red-50 text-red-700 border-red-100'
    },
    {
        key: 'expiringSoon',
        label: 'Con caducidad',
        icon: 'event_busy',
        color: 'bg-emerald-50 text-emerald-700 border-emerald-100'
    }
]
</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div v-for="card in cards" :key="card.key" class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">
                        {{ card.label }}
                    </p>

                    <h2 class="text-2xl font-bold text-slate-800 mt-1">
                        {{ stats[card.key] }}
                    </h2>
                </div>

                <div class="w-12 h-12 rounded-2xl border flex items-center justify-center" :class="card.color">
                    <span class="material-symbols-outlined text-[24px]">
                        {{ card.icon }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>