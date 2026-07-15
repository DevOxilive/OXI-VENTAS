<script setup>
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
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
    quantityClass,
    formatNumber,
    resetFilters,
    closeModal,
} = useProductMovementsModal(props, emit)

const modalConfig = getProductMovementsModalConfig()
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

                    <button type="button"
                        class="inline-flex h-9 items-center justify-center rounded-lg border border-secondary bg-background px-3 text-sm font-semibold text-text transition hover:border-primary hover:bg-secondary hover:text-primary"
                        @click="resetFilters">
                        Limpiar filtros
                    </button>
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
                <div class="hidden border-b border-secondary bg-secondary px-2 py-2 md:grid md:items-center md:gap-4"
                    style="grid-template-columns: repeat(6, minmax(0, 1fr));">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-text opacity-50">Fecha</p>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-text opacity-50">Movimiento</p>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-text opacity-50">Usuario</p>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-text opacity-50">Lote / Nota</p>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-text opacity-50">Cantidad</p>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-text opacity-50">Stock</p>
                </div>

                <div class="h-full overflow-y-auto">
                    <div v-if="tableRows.length" class="divide-y divide-secondary">
                        <article v-for="movement in tableRows" :key="movement.id"
                            class="px-2 py-2.5 transition hover:bg-secondary">
                            <div class="hidden md:grid md:items-start md:gap-4"
                                style="grid-template-columns: repeat(6, minmax(0, 1fr));">
                                <p class="text-sm leading-5 text-text opacity-80">
                                    {{ movement.displayDate }}
                                </p>

                                <div class="min-w-0 text-sm leading-5">
                                    <p class="font-semibold text-text">
                                        {{ movement.displayReason }}
                                    </p>

                                    <p class="text-primary">
                                        {{ movement.displayType }}
                                    </p>
                                </div>

                                <p class="text-sm leading-5 text-text opacity-80">
                                    {{ movement.displayUser }}
                                </p>

                                <div class="min-w-0 text-sm leading-5 text-text opacity-80">
                                    <p>{{ movement.displayBatches }}</p>
                                    <p v-if="movement.notesText" class="text-text opacity-70">
                                        {{ movement.notesText }}
                                    </p>
                                </div>

                                <p class="text-sm font-semibold leading-5" :class="quantityClass(movement)">
                                    {{ movement.displayQuantity }} {{ unit }}
                                </p>

                                <p class="text-sm leading-5 text-text opacity-80">
                                    {{ movement.displayStock }} {{ unit }}
                                </p>
                            </div>

                            <div class="space-y-1.5 text-sm md:hidden">
                                <p class="font-semibold leading-5 text-text">
                                    {{ movement.displayReason }}
                                </p>

                                <div class="flex flex-wrap gap-x-3 gap-y-1 leading-5 text-text opacity-80">
                                    <p><span class="font-semibold text-violet-700">Fecha:</span> {{ movement.displayDate }}</p>
                                    <p><span class="font-semibold text-violet-700">Tipo:</span> {{ movement.displayType }}</p>
                                    <p><span class="font-semibold text-violet-700">Usuario:</span> {{ movement.displayUser }}</p>
                                    <p><span class="font-semibold text-violet-700">Lote:</span> {{ movement.displayBatches }}</p>
                                    <p><span class="font-semibold text-violet-700">Cantidad:</span> <span :class="quantityClass(movement)">{{ movement.displayQuantity }} {{ unit }}</span></p>
                                    <p><span class="font-semibold text-violet-700">Stock:</span> {{ movement.displayStock }} {{ unit }}</p>
                                </div>

                                <p v-if="movement.notesText" class="leading-5 text-text opacity-70">
                                    {{ movement.notesText }}
                                </p>
                            </div>
                        </article>
                    </div>

                    <div v-else class="flex h-full items-center justify-center px-6 text-center">
                        <div>
                            <p class="text-sm font-semibold text-text">
                                No hay movimientos que coincidan con los filtros.
                            </p>

                            <p class="mt-1 text-sm text-text opacity-70">
                                Prueba con otro grupo, usuario o rango de fechas.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </GlobalModal>
</template>
