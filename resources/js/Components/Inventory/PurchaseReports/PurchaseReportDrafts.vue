<script setup>
import ActionIconButton from '@/Components/Forms/ActionIconButton.vue'
import { usePermissions } from '@/Composables/usePermissions'
import { formatOperationalDate } from '@/utils/operationalDateTime'

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

    return formatOperationalDate(date)
}

function reportTitle(report, index) {
    return report.draft_label || 'Borrador compartido'
}

function reportDetails(report) {
    const itemsCount = Number(report.items_count ?? report.items?.length ?? 0)

    return `${formatDate(report.display_date || report.created_at)} · ${itemsCount} ${itemsCount === 1 ? 'producto' : 'productos'}`
}
</script>

<template>
    <article class="flex flex-col gap-3 rounded-2xl border border-secondary bg-background p-3 shadow-sm xl:flex-row xl:items-center">
        <div class="flex shrink-0 flex-col gap-3 sm:flex-row sm:items-center xl:gap-2">
            <div class="flex min-w-0 items-center gap-3">
                <span class="material-symbols-outlined rounded-xl bg-secondary p-2 text-2xl text-primary">
                    edit_note
                </span>
                <div class="min-w-0">
                    <h2 class="text-base font-black text-text">
                        Borrador compartido
                    </h2>
                </div>
            </div>

            <span class="w-fit rounded-full border border-secondary bg-secondary px-3 py-1 text-xs font-black text-text">
                {{ reports.length ? 'Activo' : 'Sin borrador' }}
            </span>
        </div>

        <div
            v-if="reports.length"
            class="grid flex-1 gap-2 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2"
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
