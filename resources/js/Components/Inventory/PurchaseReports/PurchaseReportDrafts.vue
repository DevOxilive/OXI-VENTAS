<script setup>
import GlobalCard from '@/Components/Cards/GlobalCard.vue'
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

function reportTitle(report) {
    return report.folio || `Lista #${report.id}`
}

function reportDetails(report) {
    const itemsCount = Number(report.items_count ?? report.items?.length ?? 0)

    return `${formatDate(report.display_date || report.created_at)} · ${itemsCount} ${itemsCount === 1 ? 'producto' : 'productos'}`
}
</script>

<template>
    <GlobalCard
        title="Borradores"
        description="Continúa una lista guardada."
        icon="edit_note"
        :clickable="false"
        class="h-full"
    >
        <div
            v-if="reports.length"
            class="mt-5 max-h-[calc(100vh-27rem)] space-y-2 overflow-y-auto pr-1"
        >
            <article
                v-for="report in reports"
                :key="report.id"
                class="flex items-center justify-between gap-3 rounded-2xl border border-secondary bg-secondary px-3 py-3"
            >
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-bold text-text">
                        {{ reportTitle(report) }}
                    </p>
                    <p class="mt-1 truncate text-xs text-text opacity-60">
                        {{ reportDetails(report) }}
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <ActionIconButton
                        v-if="can('inventory.purchase-reports.update')"
                        class="h-9 w-9"
                        icon="edit"
                        title="Editar borrador"
                        variant="amber"
                        @click="emit('edit', report)"
                    />

                    <ActionIconButton
                        v-if="can('inventory.purchase-reports.delete')"
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
            class="mt-5 rounded-2xl border border-dashed border-secondary bg-secondary px-4 py-5 text-center text-sm text-text opacity-70"
        >
            Todavía no hay borradores guardados.
        </p>

        <div
            v-if="Number(pagination.last_page || 1) > 1"
            class="mt-4 flex items-center justify-between border-t border-secondary pt-4"
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
    </GlobalCard>
</template>
