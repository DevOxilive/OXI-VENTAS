<script setup>
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import { useProductMovementsModal } from '@/Composables/Inventory/useProductMovementsModal'
import { getProductMovementsModalConfig } from '@/config/ModalConfigs/productMovementsModalConfig'

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    loading: Boolean,
    error: String,
})

const emit = defineEmits(['close', 'load-page', 'retry'])

const {
    productName,
    productCode,
    unit,
    currentStock,
    totalMovements,
    totalFilteredMovements,
    filters,
    movementGroupOptions,
    userOptions,
    tableRows,
    movementTableColumns,
    movementSummary,
    quantityClass,
    formatNumber,
    pagination,
    loadPage,
    closeModal,
} = useProductMovementsModal(props, emit)

const modalConfig = getProductMovementsModalConfig()

function summaryTextClass(item) {
    return {
        purchases: 'text-emerald-700',
        returns: 'text-emerald-700',
        sales: 'text-rose-700',
        damaged: 'text-rose-700',
        expired: 'text-rose-700',
        audits: 'text-primary',
        adjustments: 'text-primary',
        others: 'text-text',
    }[item.key] ?? 'text-text'
}
</script>

<template>
    <GlobalModal v-bind="modalConfig" @close="closeModal">
        <template #content>
        <div class="history-scroll min-h-0 flex-1 overflow-auto overscroll-contain p-4 sm:p-6" tabindex="0" aria-label="Historial de movimientos desplazable">
            <section class="border-b border-secondary pb-3">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div class="min-w-0">
                        <h3 class="text-base font-bold leading-6 text-text">
                            {{ productName }}
                        </h3>

                        <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-text opacity-80">
                            <p class="min-w-0">
                                <span class="font-semibold text-violet-700">Código:</span>
                                {{ productCode }}
                            </p>

                            <p>
                                <span class="font-semibold text-violet-700">Stock:</span>
                                {{ formatNumber(currentStock) }}
                            </p>

                            <p>
                                <span class="font-semibold text-violet-700">Movimientos:</span>
                                {{ totalFilteredMovements }} de {{ totalMovements }}
                            </p>
                        </div>
                    </div>

                </div>
            </section>

            <div v-if="movementSummary.length" class="my-4 flex flex-wrap gap-2" aria-label="Resumen por operación">
                <div v-for="item in movementSummary" :key="item.key" class="flex items-center gap-3 rounded-xl border border-secondary px-3 py-2">
                    <span class="text-xs font-medium" :class="summaryTextClass(item)">{{ item.label }}</span>
                    <span class="text-lg font-bold tabular-nums text-text">{{ item.count }}</span>
                </div>
            </div>

            <section class="border-b border-secondary py-3">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-text opacity-50">
                            Grupo
                        </span>

                        <GlobalSelect v-model="filters.movementGroup"
                            class="h-10 w-full rounded-lg border border-secondary bg-background px-3 text-sm text-text outline-none transition focus:border-primary focus:ring-2 focus:ring-secondary">
                            <option v-for="option in movementGroupOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </GlobalSelect>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-text opacity-50">
                            Usuario
                        </span>

                        <GlobalSelect v-model="filters.userId"
                            class="h-10 w-full rounded-lg border border-secondary bg-background px-3 text-sm text-text outline-none transition focus:border-primary focus:ring-2 focus:ring-secondary">
                            <option v-for="option in userOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </GlobalSelect>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-text opacity-50">
                            Desde
                        </span>

                        <input v-model="filters.dateFrom" type="date"
                            class="h-10 w-full rounded-lg border border-secondary bg-background px-3 text-sm text-text outline-none transition focus:border-primary focus:ring-2 focus:ring-secondary">
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-text opacity-50">
                            Hasta
                        </span>

                        <input v-model="filters.dateTo" type="date"
                            class="h-10 w-full rounded-lg border border-secondary bg-background px-3 text-sm text-text outline-none transition focus:border-primary focus:ring-2 focus:ring-secondary">
                    </label>
                </div>
            </section>

            <section class="history-table mt-4 overflow-auto rounded-xl border border-secondary" tabindex="0" aria-label="Tabla de movimientos desplazable">
                <div v-if="error" role="alert" class="p-4">
                    {{ error }} <button type="button" class="underline" @click="emit('retry')">Reintentar</button>
                </div>
                <GlobalTable v-else
                    :items="tableRows"
                    :columns="movementTableColumns"
                    row-key="id"
                    mobile-card-header-field="displayReason"
                    no-data-message="No hay movimientos que coincidan con los filtros."
                    :show-pagination="false"
                    :loading="loading"
                >
                    <template #cell-displayQuantity="{ row }">
                        <span class="font-semibold" :class="quantityClass(row)">
                            {{ row.displayQuantity }}
                        </span>
                    </template>

                    <template #cell-displayBatches="{ row }">
                        <div class="min-w-0 leading-5">
                            <p class="whitespace-normal break-words text-text">{{ row.displayBatches }}</p>
                            <details v-if="row.notesText" class="mt-1 text-xs text-text">
                                <summary class="cursor-pointer opacity-70">Ver nota</summary>
                                <p class="mt-2 whitespace-pre-wrap leading-relaxed">{{ row.notesText }}</p>
                            </details>
                        </div>
                    </template>
                </GlobalTable>
            </section>

            <div v-if="pagination && pagination.last_page > 1" class="flex shrink-0 items-center justify-end gap-3 border-t border-secondary py-3 text-sm">
                <button type="button" class="rounded-lg border border-secondary px-3 py-2 disabled:opacity-40"
                    :disabled="loading || pagination.current_page <= 1" @click="loadPage(pagination.current_page - 1)">Anterior</button>
                <span>{{ pagination.current_page }} de {{ pagination.last_page }}</span>
                <button type="button" class="rounded-lg border border-secondary px-3 py-2 disabled:opacity-40"
                    :disabled="loading || !pagination.has_more" @click="loadPage(pagination.current_page + 1)">Siguiente</button>
            </div>

        </div>
        </template>

        <template #footer="{ close }">
            <footer class="sticky bottom-0 flex flex-col gap-3 border-t border-secondary bg-background p-4 sm:flex-row sm:items-center sm:justify-between">
                <span class="text-xs text-text opacity-60">{{ totalFilteredMovements }} movimientos encontrados</span>

                <button
                    type="button"
                    class="w-full shrink-0 rounded-full border border-secondary bg-secondary px-8 py-3 text-text transition hover:brightness-95 sm:w-auto"
                    @click="close"
                >
                    Cerrar
                </button>
            </footer>
        </template>
    </GlobalModal>
</template>

<style scoped>
.history-scroll { scrollbar-gutter: stable; }
.history-table { max-height: 52dvh; scrollbar-gutter: stable; }
.history-table :deep(thead) { position: sticky; top: 0; z-index: 10; }
@media (max-height: 550px) {
    .history-table { max-height: none; }
}
</style>
