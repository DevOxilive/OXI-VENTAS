<script setup>
import { onMounted, onBeforeUnmount, computed } from 'vue'
import { useAdjustStockForm } from '@/Composables/Inventory/useAdjustStockForm'

import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import AdjustStockData from '@/Components/Inventory/AdjustStockData.vue'

const emit = defineEmits(['close'])

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
})

const {
    form,
    frontendErrors,
    typeOptions,
    reasonOptions,
    currentStock,
    projectedStock,
    errorSummary,
    validateField,
    saveAdjustment,
    addBatch,
    removeBatch,
    totalBatchQuantity,
    addManualBatch,
    removeManualBatch,
    totalManualBatchQuantity,
} = useAdjustStockForm(props, emit)

function closeModal() {
    emit('close')
}

const saveButtonText = computed(() => {
    if (form.processing) return 'Procesando...'

    return 'Guardar ajuste'
})

const totalErrors = computed(() => errorSummary.value.length)

function handleEsc(e) {
    if (e.key === 'Escape') closeModal()
}

onMounted(() => {
    window.addEventListener('keydown', handleEsc)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEsc)
})
</script>

<template>
    <div class="fixed inset-0 z-50 bg-black/60 flex items-end md:items-center justify-center">
        <div class="absolute inset-0" @click="closeModal"></div>

        <div
            class="relative bg-white w-full h-[100dvh] sm:h-[100dvh] md:h-[94vh] md:w-[96%] md:max-w-7xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden">
            <GeneralModalHeader title="Ajustar stock" subtitle="Movimiento de inventario por sucursal"
                :total-errors="totalErrors" mode="create" @close="closeModal" />

            <GeneralModalContent :columns="1">
                <AdjustStockData :form="form" :product="product" :frontend-errors="frontendErrors"
                    :type-options="typeOptions" :reason-options="reasonOptions" :current-stock="currentStock"
                    :projected-stock="projectedStock" :recent-movements="product.recentMovements || []"
                    :total-batch-quantity="totalBatchQuantity" :total-manual-batch-quantity="totalManualBatchQuantity"
                    @validate="validateField" @add-batch="addBatch" @remove-batch="removeBatch"
                    @add-manual-batch="addManualBatch" @remove-manual-batch="removeManualBatch" />
            </GeneralModalContent>

            <GeneralModalFooter :processing="form.processing" :save-button-text="saveButtonText" mode="create"
                @save="saveAdjustment" @close="closeModal" />
        </div>
    </div>
</template>