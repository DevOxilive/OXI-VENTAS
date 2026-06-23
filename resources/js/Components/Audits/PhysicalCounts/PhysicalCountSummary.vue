<script setup>
const props = defineProps({
    summary: {
        type: Object,
        default: () => ({})
    },
    canViewInventoryComparison: {
        type: Boolean,
        default: false
    }
})

const cards = [
    { key: 'total_entries', label: 'Registros', icon: 'list_alt' },
    { key: 'total_counted', label: 'Total contado', icon: 'inventory_2' },
    { key: 'total_damaged', label: 'Dañados', icon: 'warning' },
    { key: 'total_expired', label: 'Caducados', icon: 'event_busy' },
]
</script>

<template>
    <div class="space-y-4">
        <div v-if="canViewInventoryComparison" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">
                Progreso de auditoría
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Avance calculado contra los productos activos de la sucursal.
            </p>

            <div class="mt-4">
                <div class="mb-2 flex justify-between text-sm">
                    <span class="text-gray-500">
                        {{ summary.audited_products ?? 0 }} de {{ summary.total_products_in_branch ?? 0 }} productos
                    </span>

                    <strong class="text-gray-900">
                        {{ summary.progress_percentage ?? 0 }}%
                    </strong>
                </div>

                <div class="h-3 overflow-hidden rounded-full bg-gray-100">
                    <div
                        class="h-full rounded-full bg-slate-900 transition-all"
                        :style="{ width: `${summary.progress_percentage ?? 0}%` }"
                    />
                </div>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-xs text-gray-500">Auditados</p>
                    <strong class="text-lg text-gray-900">
                        {{ summary.audited_products ?? 0 }}
                    </strong>
                </div>

                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-xs text-gray-500">Pendientes</p>
                    <strong class="text-lg text-gray-900">
                        {{ summary.pending_products ?? 0 }}
                    </strong>
                </div>

                <div class="rounded-lg bg-slate-50 p-3">
                    <p class="text-xs text-gray-500">Total</p>
                    <strong class="text-lg text-gray-900">
                        {{ summary.total_products_in_branch ?? 0 }}
                    </strong>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">
                Resumen general
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Totales acumulados de los productos capturados.
            </p>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <div
                    v-for="card in cards"
                    :key="card.key"
                    class="rounded-xl border border-slate-200 bg-slate-50 p-3"
                >
                    <div class="flex items-center gap-2 text-xs font-medium text-slate-500">
                        <span class="material-symbols-outlined text-[18px]">
                            {{ card.icon }}
                        </span>

                        {{ card.label }}
                    </div>

                    <div class="mt-2 text-2xl font-bold text-slate-900">
                        {{ summary[card.key] ?? 0 }}
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">
                Participantes
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Usuarios que han registrado conteos en esta auditoría.
            </p>

            <div
                v-if="summary.participant_names?.length"
                class="mt-4 space-y-2"
            >
                <div
                    v-for="user in summary.participant_names"
                    :key="user"
                    class="flex items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700"
                >
                    <span class="material-symbols-outlined text-[18px] text-slate-500">
                        person
                    </span>

                    {{ user }}
                </div>
            </div>

            <div
                v-else
                class="mt-4 rounded-lg border border-dashed border-gray-300 p-3 text-sm text-gray-500"
            >
                Todavía no hay participantes registrados.
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-900">
                Comparación contra inventario
            </h2>

            <p class="mt-1 text-sm text-gray-500">
                Diferencias encontradas contra el stock actual del sistema.
            </p>

            <div class="mt-4 space-y-2">
                <div class="flex justify-between rounded-lg bg-red-50 px-3 py-2 text-sm">
                    <span class="text-red-700">Faltantes</span>
                    <strong class="text-red-700">
                        {{ summary.missing_products ?? 0 }}
                    </strong>
                </div>

                <div class="flex justify-between rounded-lg bg-amber-50 px-3 py-2 text-sm">
                    <span class="text-amber-700">Sobrantes</span>
                    <strong class="text-amber-700">
                        {{ summary.surplus_products ?? 0 }}
                    </strong>
                </div>

                <div class="flex justify-between rounded-lg bg-green-50 px-3 py-2 text-sm">
                    <span class="text-green-700">Correctos</span>
                    <strong class="text-green-700">
                        {{ summary.matched_products ?? 0 }}
                    </strong>
                </div>
            </div>
        </div>
    </div>
</template>
