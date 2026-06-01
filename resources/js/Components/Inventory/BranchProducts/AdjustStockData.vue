<script setup>
import { computed } from 'vue'

import StockMovementForm from './StockMovementForm.vue'
import IncomingBatchesForm from './IncomingBatchesForm.vue'
import OutgoingBatchSelector from './OutgoingBatchSelector.vue'

import RecentStockMovements from '../RecentStockMovements.vue'
import ProductStockSummary from '../ProductStockSummary.vue'

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    product: {
        type: Object,
        required: true,
    },
    frontendErrors: {
        type: Object,
        default: () => ({}),
    },
    typeOptions: {
        type: Array,
        default: () => [],
    },
    reasonOptions: {
        type: Array,
        default: () => [],
    },
    currentStock: {
        type: Number,
        default: 0,
    },
    projectedStock: {
        type: Number,
        default: 0,
    },
    recentMovements: {
        type: Array,
        default: () => [],
    },
    totalBatchQuantity: {
        type: Number,
        default: 0,
    },
    totalManualBatchQuantity: {
        type: Number,
        default: 0,
    },
})

const emit = defineEmits([
    'validate',
    'add-batch',
    'remove-batch',
    'add-manual-batch',
    'remove-manual-batch',
    'edit-batch',
])

const isIncomingMovement = computed(() => props.form.type === 'IN')
const isOutgoingMovement = computed(() => props.form.type === 'OUT')

function addBatch() {
    if (props.form.processing) return

    emit('add-batch')
}
</script>
<template>
    <div
        class="h-full min-h-0 overflow-y-auto xl:overflow-hidden bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 xl:gap-5 min-h-full xl:h-full xl:min-h-0">
            <div class="xl:col-span-4 min-h-0 xl:overflow-y-auto xl:pr-2 space-y-4">
                <ProductStockSummary :product="product" :current-stock="currentStock"
                    :projected-stock="projectedStock" />

                <StockMovementForm :form="form" :frontend-errors="frontendErrors" :type-options="typeOptions"
                    :reason-options="reasonOptions" @validate="$emit('validate', $event)" />
            </div>

            <section class="xl:col-span-4 flex flex-col min-h-0">
                <div
                    class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b pb-3 mb-4 shrink-0">
                    <div>
                        <h3 class="font-bold text-base text-slate-900">
                            Lotes y caducidad
                        </h3>

                        <p class="text-xs text-slate-500 mt-1">
                            Administra lotes disponibles y nuevas entradas.
                        </p>
                    </div>

                    <button v-if="isIncomingMovement" type="button" :disabled="form.processing"
                        class="px-4 py-2 rounded-xl bg-[#1f1d2b] text-white text-sm font-bold hover:bg-[#2d2a3d] transition disabled:opacity-50 disabled:cursor-not-allowed w-full sm:w-auto shrink-0"
                        @click="addBatch">
                        + Agregar lote
                    </button>
                </div>

                <IncomingBatchesForm v-if="isIncomingMovement" :form="form" :product="product"
                    :frontend-errors="frontendErrors" :total-batch-quantity="totalBatchQuantity"
                    @remove-batch="$emit('remove-batch', $event)" @edit-batch="$emit('edit-batch', $event)" />

                <OutgoingBatchSelector v-if="isOutgoingMovement" :form="form" :product="product"
                    :frontend-errors="frontendErrors" :total-manual-batch-quantity="totalManualBatchQuantity"
                    @add-manual-batch="$emit('add-manual-batch', $event)"
                    @remove-manual-batch="$emit('remove-manual-batch', $event)" />

                <div v-if="!isIncomingMovement && !isOutgoingMovement"
                    class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">
                    Selecciona si el movimiento será una entrada o una salida para administrar lotes.
                </div>
            </section>

            <div class="xl:col-span-4 min-h-0 xl:overflow-y-auto xl:pr-2">
                <RecentStockMovements :recent-movements="recentMovements" />
            </div>
        </div>
    </div>
</template>