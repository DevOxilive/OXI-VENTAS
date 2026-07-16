<script setup>
import { computed } from 'vue'

import SectionHeading from '@/Components/Cards/SectionHeading.vue'
import ActionIconButton from '@/Components/Forms/ActionIconButton.vue'

const props = defineProps({
    reports: {
        type: Array,
        default: () => [],
    },
    counts: {
        type: Object,
        default: () => ({}),
    },
    pagination: {
        type: Object,
        default: () => ({}),
    },
    activeStatus: {
        type: String,
        default: 'DRAFT',
    },
    activeDraftId: {
        type: [Number, String],
        default: null,
    },
    canDelete: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['open', 'view', 'delete', 'status-change', 'paginate'])

const tabs = computed(() => [
    { status: 'DRAFT', label: 'Borradores', count: Number(props.counts.DRAFT || 0) },
    { status: 'GENERATED', label: 'Seguimiento', count: Number(props.counts.GENERATED || 0) },
    { status: 'COMPLETED', label: 'Histórico', count: Number(props.counts.COMPLETED || 0) },
])

function formatDate(date) {
    if (!date) return 'Sin fecha'

    return new Intl.DateTimeFormat('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(date))
}

function itemCount(report) {
    return Number(report.items_count ?? report.items?.length ?? 0)
}

function openReport(report) {
    emit(report.status === 'DRAFT' ? 'open' : 'view', report)
}

function emptyMessage() {
    if (props.activeStatus === 'GENERATED') return 'No tienes listas en seguimiento.'
    if (props.activeStatus === 'COMPLETED') return 'Todavía no tienes compras completadas.'
    return 'Todavía no hay borradores guardados.'
}
</script>

<template>
    <aside class="min-w-0 rounded-[28px] border border-secondary bg-background p-4 shadow-sm">
        <SectionHeading
            title="Mis listas"
            description="Consulta el avance de tus solicitudes."
            spacing="sm"
        />

        <div class="mt-4 grid grid-cols-3 gap-1 rounded-2xl border border-secondary bg-secondary p-1">
            <button
                v-for="tab in tabs"
                :key="tab.status"
                type="button"
                class="min-w-0 rounded-xl px-1 py-2 text-center transition"
                :class="activeStatus === tab.status
                    ? 'bg-background text-primary shadow-sm'
                    : 'text-text opacity-65 hover:opacity-100'"
                :title="tab.label"
                @click="emit('status-change', tab.status)"
            >
                <strong class="block text-sm">{{ tab.count }}</strong>
                <span class="block truncate text-[10px] font-semibold">{{ tab.label }}</span>
            </button>
        </div>

        <div
            v-if="!reports.length"
            class="mt-4 rounded-2xl border border-dashed border-secondary bg-secondary px-4 py-5 text-center text-xs text-text opacity-70"
        >
            {{ emptyMessage() }}
        </div>

        <div
            v-else
            class="mt-4 max-h-[calc(100vh-24rem)] space-y-2 overflow-y-auto pr-1"
        >
            <article
                v-for="report in reports"
                :key="report.id"
                class="flex items-center gap-1 rounded-2xl border p-1 transition"
                :class="String(activeDraftId ?? '') === String(report.id)
                    ? 'border-primary bg-secondary ring-2 ring-primary/20'
                    : 'border-secondary bg-secondary hover:border-primary'"
            >
                <button
                    type="button"
                    class="min-w-0 flex-1 rounded-xl px-2 py-2 text-left"
                    @click="openReport(report)"
                >
                    <p class="truncate text-xs font-bold text-text">
                        {{ report.folio || `Lista #${report.id}` }}
                    </p>
                    <p class="mt-1 truncate text-[10px] text-text opacity-60">
                        {{ formatDate(report.display_date || report.created_at) }} ·
                        {{ itemCount(report) }} {{ itemCount(report) === 1 ? 'producto' : 'productos' }}
                    </p>
                </button>

                <ActionIconButton
                    v-if="report.status === 'DRAFT' && canDelete"
                    class="h-8 w-8 shrink-0"
                    icon="delete"
                    title="Eliminar borrador"
                    variant="red"
                    @click="emit('delete', report)"
                />
            </article>
        </div>

        <div
            v-if="Number(pagination.last_page || 1) > 1"
            class="mt-3 flex items-center justify-between border-t border-secondary pt-3"
        >
            <button
                type="button"
                class="rounded-lg border border-secondary bg-background px-2 py-1 text-xs font-semibold text-text disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="!pagination.prev_page_url"
                @click="emit('paginate', pagination.prev_page_url)"
            >
                Anterior
            </button>
            <span class="text-[10px] text-text opacity-60">
                {{ pagination.current_page }} / {{ pagination.last_page }}
            </span>
            <button
                type="button"
                class="rounded-lg border border-secondary bg-background px-2 py-1 text-xs font-semibold text-text disabled:cursor-not-allowed disabled:opacity-40"
                :disabled="!pagination.next_page_url"
                @click="emit('paginate', pagination.next_page_url)"
            >
                Siguiente
            </button>
        </div>
    </aside>
</template>
