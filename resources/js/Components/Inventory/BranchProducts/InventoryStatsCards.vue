<script setup>
import { computed } from 'vue'

const props = defineProps({
    stats: {
        type: Object,
        required: true,
    },
    alerts: {
        type: Object,
        default: () => ({
            expiredBatches: 0,
            nearExpirationBatches: 0,
            lowStockProducts: 0,
            inactiveCandidateProducts: 0,
        }),
    },
})

const emit = defineEmits([
    'open-alert',
])

const cards = computed(() => [
    {
        key: 'total',
        label: 'Productos',
        value: props.stats.total,
        icon: 'inventory_2',
        tone: 'slate',
    },
    {
        key: 'totalStock',
        label: 'Unidades',
        value: props.stats.totalStock,
        icon: 'warehouse',
        tone: 'slate',
    },
    {
        key: 'lowStockProducts',
        label: 'Stock bajo',
        value: props.alerts.lowStockProducts,
        icon: 'warning',
        tone: 'amber',
        alertType: 'lowStock',
    },
    {
        key: 'expiredBatches',
        label: 'Vencidos',
        value: props.alerts.expiredBatches,
        icon: 'dangerous',
        tone: 'red',
        alertType: 'expired',
    },
    {
        key: 'nearExpirationBatches',
        label: 'Por vencer',
        value: props.alerts.nearExpirationBatches,
        icon: 'event_busy',
        tone: 'orange',
        alertType: 'nearExpiration',
    },
    {
        key: 'inactiveCandidateProducts',
        label: 'Sin surtir',
        value: props.alerts.inactiveCandidateProducts,
        icon: 'inventory',
        tone: 'purple',
        alertType: 'inactiveCandidates',
    },
])

function toneClass(tone) {
    return {
        slate: 'bg-white border-slate-200 text-slate-700',
        red: 'bg-red-50 border-red-200 text-red-700',
        orange: 'bg-orange-50 border-orange-200 text-orange-700',
        amber: 'bg-amber-50 border-amber-200 text-amber-700',
        purple: 'bg-purple-50 border-purple-200 text-purple-700',
    }[tone]
}

function openCard(card) {
    if (!card.alertType || Number(card.value) <= 0) return

    emit('open-alert', card.alertType)
}
</script>

<template>
    <div>
        <p class="text-sm font-black text-slate-700 mb-3">
            Estado rápido del inventario
        </p>

        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3">
            <button v-for="card in cards" :key="card.key" type="button"
                class="rounded-2xl border px-4 py-3 shadow-sm text-left transition" :class="[
                    toneClass(card.tone),
                    card.alertType && Number(card.value) > 0
                        ? 'hover:scale-[1.01] cursor-pointer'
                        : 'cursor-default'
                ]" @click="openCard(card)">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <p class="text-xs font-bold opacity-70">
                            {{ card.label }}
                        </p>

                        <p class="text-xl font-black leading-tight mt-1">
                            {{ card.value }}
                        </p>
                    </div>

                    <span class="material-symbols-outlined text-[22px] opacity-70">
                        {{ card.icon }}
                    </span>
                </div>
            </button>
        </div>
    </div>
</template>
