<script setup>
import ProductStockSummary from './ProductStockSummary.vue'
import StockMovementForm from './StockMovementForm.vue'
import IncomingBatchesForm from './IncomingBatchesForm.vue'
import OutgoingBatchSelector from './OutgoingBatchSelector.vue'
import RecentStockMovements from './RecentStockMovements.vue'

defineProps({
    form: Object,
    product: Object,
    frontendErrors: Object,
    typeOptions: Array,
    reasonOptions: Array,
    currentStock: Number,
    projectedStock: Number,
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

defineEmits([
    'validate',
    'add-batch',
    'remove-batch',
    'add-manual-batch',
    'remove-manual-batch',
    'edit-batch',
])
</script>

<template>
    <div
        class="h-full min-h-0 bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm overflow-hidden">
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 xl:gap-5 h-full min-h-0">
            <ProductStockSummary :product="product" :current-stock="currentStock" :projected-stock="projectedStock" />

            <StockMovementForm :form="form" :frontend-errors="frontendErrors" :type-options="typeOptions"
                :reason-options="reasonOptions" @validate="$emit('validate', $event)" />

            <section class="xl:col-span-4 flex flex-col min-h-0">
                <div class="flex items-center justify-between gap-4 border-b pb-3 mb-4">
                    <div>
                        <h3 class="font-bold text-base text-slate-900">
                            Lotes y caducidad
                        </h3>

                        <p class="text-xs text-slate-500 mt-1">
                            Administra lotes disponibles y nuevas entradas.
                        </p>
                    </div>

                    <button v-if="form.type === 'IN'" type="button"
                        class="px-4 py-2 rounded-xl bg-[#1f1d2b] text-white text-sm font-bold hover:bg-[#2d2a3d] transition shrink-0"
                        @click="$emit('add-batch')">
                        + Agregar lote
                    </button>
                </div>

                <IncomingBatchesForm v-if="form.type === 'IN'" :form="form" :product="product"
                    :frontend-errors="frontendErrors" :total-batch-quantity="totalBatchQuantity"
                    @remove-batch="$emit('remove-batch', $event)" @edit-batch="$emit('edit-batch', $event)" />

                <OutgoingBatchSelector v-if="form.type === 'OUT'" :form="form" :product="product"
                    :frontend-errors="frontendErrors" :total-manual-batch-quantity="totalManualBatchQuantity"
                    @add-manual-batch="$emit('add-manual-batch', $event)"
                    @remove-manual-batch="$emit('remove-manual-batch', $event)" />
            </section>

            <RecentStockMovements :recent-movements="recentMovements" />
        </div>
    </div>
</template>
