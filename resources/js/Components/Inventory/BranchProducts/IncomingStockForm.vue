<script setup>
import { computed, watch } from 'vue'

import StockMovementForm from './StockMovementForm.vue'
import IncomingBatchesForm from './IncomingBatchesForm.vue'
import OutgoingBatchSelector from './OutgoingBatchSelector.vue'
import ProductStockSummary from '../ProductStockSummary.vue'

const props = defineProps({
    form: Object,
    product: Object,
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

function ensureEntryReady() {
    if (!isIncomingMovement.value) return

    props.form.type = 'IN'
    props.form.reason = 'PURCHASE'

    if (!props.form.batches?.length) {
        emit('add-batch')
    }
}

watch(
    () => props.form.type,
    ensureEntryReady,
    { immediate: true }
)
</script>

<template>
    <div class="h-full min-h-0 overflow-y-auto bg-white">
        <section v-if="isIncomingMovement" class="max-w-3xl mx-auto">
            <IncomingBatchesForm :form="form" :product="product" :frontend-errors="frontendErrors"
                :current-stock="currentStock" @validate="$emit('validate', $event)" />
        </section>

        <div v-else class="grid grid-cols-1 xl:grid-cols-12 gap-5 min-h-full">
            <aside class="xl:col-span-4 space-y-4">
                <ProductStockSummary :product="product" :current-stock="currentStock"
                    :projected-stock="projectedStock" />

                <StockMovementForm :form="form" :frontend-errors="frontendErrors" :type-options="typeOptions"
                    :reason-options="reasonOptions" @validate="$emit('validate', $event)" />
            </aside>

            <section class="xl:col-span-8 min-h-0">
                <OutgoingBatchSelector v-if="isOutgoingMovement" :form="form" :product="product"
                    :frontend-errors="frontendErrors" :total-manual-batch-quantity="totalManualBatchQuantity"
                    @add-manual-batch="$emit('add-manual-batch', $event)"
                    @remove-manual-batch="$emit('remove-manual-batch', $event)" />

                <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                    <p class="text-sm font-black text-slate-700">
                        Ajuste pendiente
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>