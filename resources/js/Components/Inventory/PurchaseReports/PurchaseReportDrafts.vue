<script setup>
import ActionIconButton from '@/Components/Forms/ActionIconButton.vue'
import { usePermissions } from '@/Composables/usePermissions'

const props = defineProps({
    reports: {
        type: Array,
        default: () => [],
    },
    pagination: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits(['edit', 'delete', 'paginate'])
const { can } = usePermissions()

function formatDate(date) {
    if (!date) return 'Sin fecha'

    return new Intl.DateTimeFormat('es-MX', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(date))
}

function reportTitle(report, index) {
    return report.draft_label || `Borrador ${String(index + 1).padStart(3, '0')}`
}

function reportDetails(report) {
    const itemsCount = Number(report.items_count ?? report.items?.length ?? 0)

    return `${formatDate(report.display_date || report.created_at)} · ${itemsCount} ${itemsCount === 1 ? 'producto' : 'productos'}`
}
</script>

<template>
    <article class="flex h-40 min-h-0 flex-col rounded-2xl border border-secondary bg-background p-4 shadow-sm">
        <div class="shrink-0 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-center gap-3">
                <span class="material-symbols-outlined rounded-xl bg-secondary p-2 text-2xl text-primary">
                    edit_note
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-black text-text">
                        Borradores
                    </h2>
                    <p class="truncate text-sm text-text opacity-70">
                        Continúa una lista guardada o confirma que ya no quedan pendientes.
                    </p>
                </div>
            </div>

            <span class="w-fit rounded-full border border-secondary bg-secondary px-3 py-1 text-xs font-black text-text">
                {{ reports.length }} {{ reports.length === 1 ? 'borrador' : 'borradores' }}
            </span>
        </div>

        <div
            v-if="reports.length"
            class="mt-4 grid min-h-0 flex-1 content-start gap-2 overflow-y-auto pr-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5"
        >
            <article
                v-for="(report, index) in reports"
                :key="report.id"
                class="flex min-w-0 items-center justify-between gap-3 rounded-xl border border-secondary bg-secondary px-3 py-2.5"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-text">
                        {{ reportTitle(report, index) }}
                    </p>
                    <p class="mt-1 truncate text-xs text-text opacity-60">
                        {{ reportDetails(report) }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <ActionIconButton
                        v-if="can('sales.purchase-lists.update')"
                        class="h-9 w-9"
                        icon="edit"
                        title="Editar borrador"
                        variant="amber"
                        @click="emit('edit', report)"
                    />

                    <ActionIconButton
                        v-if="can('sales.purchase-lists.delete')"
                        class="h-9 w-9"
                        icon="delete"
                        title="Eliminar borrador"
                        variant="red"
                        @click="emit('delete', report)"
                    />
                </div>
            </article>
        </div>

        <p
            v-else
            class="mt-4 flex min-h-0 flex-1 items-center justify-center rounded-xl border border-dashed border-secondary bg-secondary px-4 py-3 text-center text-sm text-text opacity-70"
        >
            Todavía no hay borradores guardados.
        </p>

        <div
            v-if="Number(pagination.last_page || 1) > 1"
            class="mt-3 flex shrink-0 items-center justify-between border-t border-secondary pt-3"
        >
            <ActionIconButton
                icon="chevron_left"
                title="Página anterior"
                variant="slate"
                :disabled="!pagination.prev_page_url"
                class="disabled:cursor-not-allowed disabled:opacity-40"
                @click="emit('paginate', pagination.prev_page_url)"
            />

            <span class="text-xs font-semibold text-text opacity-60">
                Página {{ pagination.current_page }} de {{ pagination.last_page }}
            </span>

            <ActionIconButton
                icon="chevron_right"
                title="Página siguiente"
                variant="slate"
                :disabled="!pagination.next_page_url"
                class="disabled:cursor-not-allowed disabled:opacity-40"
                @click="emit('paginate', pagination.next_page_url)"
            />
        </div>
    </article>
</template>
