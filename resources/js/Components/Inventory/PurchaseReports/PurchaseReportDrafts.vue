<script setup>
defineProps({
    reports: {
        type: Array,
        default: () => [],
    },
})

defineEmits(['open'])

function statusLabel(status) {
    return {
        DRAFT: 'Borrador',
        GENERATED: 'Generado',
        CANCELLED: 'Cancelado',
    }[status] ?? status
}

function formatDate(date) {
    if (!date) return 'Sin fecha'

    return new Intl.DateTimeFormat('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(date))
}
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="mb-4">
            <h2 class="text-sm font-bold text-slate-900">
                Borradores recientes
            </h2>
            <p class="text-xs text-slate-500">
                Últimas listas de abastecimiento creadas para esta sucursal.
            </p>
        </div>

        <div v-if="!reports.length" class="rounded-xl border border-dashed border-slate-300 p-5 text-center">
            <p class="text-sm font-semibold text-slate-700">
                Todavía no hay borradores.
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Crea una lista seleccionando productos del inventario.
            </p>
        </div>

        <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
            <article v-for="report in reports" :key="report.id"
                class="rounded-xl border border-slate-200 p-4 transition hover:bg-slate-50">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-bold text-slate-900">
                            Borrador #{{ report.id }}
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ formatDate(report.created_at) }}
                        </p>
                    </div>

                    <span
                        class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">
                        {{ statusLabel(report.status) }}
                    </span>
                </div>

                <p class="mt-3 text-sm text-slate-600">
                    Productos: <b>{{ report.items_count ?? report.items?.length ?? 0 }}</b>
                </p>

                <p class="mt-2 line-clamp-2 text-xs text-slate-500">
                    {{ report.notes || 'Sin notas generales' }}
                </p>

                <button type="button"
                    class="mt-4 w-full rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                    @click="$emit('open', report)">
                    Abrir borrador
                </button>
            </article>
        </div>
    </section>
</template>