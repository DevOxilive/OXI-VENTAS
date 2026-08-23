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
})

const emit = defineEmits(['close'])

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
        <div class="flex h-full min-h-0 flex-col">
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
                                {{ formatNumber(currentStock) }} {{ unit }}
                            </p>

                            <p>
                                <span class="font-semibold text-violet-700">Movimientos:</span>
                                {{ totalFilteredMovements }} de {{ totalMovements }}
                            </p>
                        </div>
                    </div>

                </div>
            </section>

            <section class="border-b border-secondary py-3">
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-4">
                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-text opacity-50">
                            Grupo
                        </span>

                        <select v-model="filters.movementGroup"
                            class="h-10 w-full rounded-lg border border-secondary bg-background px-3 text-sm text-text outline-none transition focus:border-primary focus:ring-2 focus:ring-secondary">
                            <option v-for="option in movementGroupOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-[11px] font-semibold uppercase tracking-[0.18em] text-text opacity-50">
                            Usuario
                        </span>

                        <select v-model="filters.userName"
                            class="h-10 w-full rounded-lg border border-secondary bg-background px-3 text-sm text-text outline-none transition focus:border-primary focus:ring-2 focus:ring-secondary">
                            <option v-for="option in userOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
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

            <section class="min-h-0 flex-1 overflow-hidden">
                <GlobalTable
                    :items="tableRows"
                    :columns="movementTableColumns"
                    row-key="id"
                    mobile-card-header-field="displayReason"
                    no-data-message="No hay movimientos que coincidan con los filtros."
                    :show-pagination="false"
                >
                    <template #cell-displayQuantity="{ row }">
                        <span class="font-semibold" :class="quantityClass(row)">
                            {{ row.displayQuantity }} {{ unit }}
                        </span>
                    </template>

                    <template #cell-displayBatches="{ row }">
                        <div class="min-w-0 leading-5">
                            <p class="truncate text-text">{{ row.displayBatches }}</p>
                            <p v-if="row.notesText" class="truncate text-xs text-text opacity-60" :title="row.notesText">
                                {{ row.notesText }}
                            </p>
                        </div>
                    </template>
                </GlobalTable>
            </section>

        </div>

        <template #footer="{ close }">
            <footer class="sticky bottom-0 flex flex-col gap-3 border-t border-secondary bg-background p-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 flex-wrap items-center gap-2 text-sm text-text">
                    <span class="mr-1 font-semibold text-violet-700">Resumen:</span>

                    <template v-if="movementSummary.length">
                        <span
                            v-for="item in movementSummary"
                            :key="item.key"
                            class="inline-flex items-center gap-2 rounded-full border border-secondary bg-secondary/70 px-3 py-1.5 shadow-sm"
                        >
                            <span class="font-semibold" :class="summaryTextClass(item)">
                                {{ item.label }}
                            </span>

                            <span class="inline-flex min-w-6 justify-center rounded-full bg-background px-2 py-0.5 text-xs font-bold text-text">
                                {{ item.count }}
                            </span>
                        </span>
                    </template>

                    <span v-else class="text-text opacity-70">Sin movimientos para resumir.</span>
                </div>

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
