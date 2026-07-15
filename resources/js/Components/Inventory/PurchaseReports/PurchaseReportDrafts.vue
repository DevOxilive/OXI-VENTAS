<script setup>
import SectionHeading from '@/Components/Cards/SectionHeading.vue'

defineProps({
    reports: {
        type: Array,
        default: () => [],
    },
})

defineEmits(["open"])

function statusLabel(status) {
    return {
        DRAFT: "Borrador",
        GENERATED: "Generada",
        COMPLETED: "Completada",
        CANCELLED: "Cancelada",
    }[status] ?? status
}

function formatDate(date) {
    if (!date) return "Sin fecha"

    return new Intl.DateTimeFormat("es-MX", {
        day: "2-digit",
        month: "short",
        year: "numeric",
    }).format(new Date(date))
}

function statusClasses(status) {
    if (status === "COMPLETED") {
        return "border-accent text-accent";
    }

    if (status === "GENERATED") {
        return "border-primary text-primary";
    }

    if (status === "CANCELLED") {
        return "border-red-500/40 text-red-600";
    }

    return "border-secondary text-text";
}
</script>

<template>
    <section class="rounded-[28px] border border-secondary bg-background p-4 shadow-sm">
        <SectionHeading
            title="Borradores y compras recientes"
            description="Consulta o continua una orden guardada."
            spacing="sm"
        />

        <div
            v-if="!reports.length"
            class="mt-4 rounded-2xl border border-dashed border-secondary bg-secondary px-4 py-5 text-sm text-text opacity-70"
        >
            Todavia no hay ordenes guardadas.
        </div>

        <div
            v-else
            class="mt-4 flex gap-3 overflow-x-auto pb-1"
        >
            <button
                v-for="report in reports"
                :key="report.id"
                type="button"
                class="min-w-[220px] rounded-2xl border border-secondary bg-secondary px-4 py-3 text-left transition hover:bg-background"
                @click="$emit('open', report)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-text">
                            {{ report.folio || `Orden #${report.id}` }}
                        </p>
                        <p class="mt-1 text-xs text-text opacity-70">
                            {{ formatDate(report.created_at) }}
                        </p>
                    </div>

                    <span
                        class="rounded-full border px-2.5 py-1 text-[11px] font-semibold"
                        :class="statusClasses(report.status)"
                    >
                        {{ statusLabel(report.status) }}
                    </span>
                </div>

                <div class="mt-3 flex items-center justify-between text-xs text-text opacity-70">
                    <span>{{ report.items_count ?? report.items?.length ?? 0 }} producto(s)</span>
                    <span class="font-semibold text-text">Abrir</span>
                </div>
            </button>
        </div>
    </section>
</template>
