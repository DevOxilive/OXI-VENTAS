<script setup>
import { onMounted, onBeforeUnmount, computed } from 'vue'

import { useAdjustStockForm } from '@/Composables/Inventory/useAdjustStockForm'
import { useEditBatchModal } from '@/Composables/Inventory/useEditBatchModal'

import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'

import AdjustStockData from '@/Components/Inventory/BranchProducts/AdjustStockData.vue'
import EditBatchModal from '@/Components/Inventory/BranchProducts/EditBatchModal.vue'

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

const {
    showEditBatchModal,
    liveSelectedBatch,
    editBatch,
    closeEditBatchModal,
} = useEditBatchModal(props)

const modalTitle = computed(() => 'Ajustar stock')

const modalSubtitle = computed(() => {
    const productName = props.product?.name ?? props.product?.product?.name

    return productName
        ? `Movimiento de inventario para ${productName}`
        : 'Movimiento de inventario por sucursal'
})

const saveButtonText = computed(() => 'Guardar ajuste')

const totalErrors = computed(() => errorSummary.value.length)

function closeModal() {
    if (form.processing) return
    if (showEditBatchModal.value) return

    emit('close')
}

function handleEsc(e) {
    if (e.key !== 'Escape') return

    if (showEditBatchModal.value) {
        closeEditBatchModal()
        return
    }

    closeModal()
}

onMounted(() => {
    window.addEventListener('keydown', handleEsc)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEsc)
})
</script>

<template>
    <div class="fixed inset-0 z-50 bg-black/60 flex items-end md:items-center justify-center" role="dialog"
        aria-modal="true">
        <div class="absolute inset-0" @click="closeModal"></div>

        <div class="relative bg-white w-full h-[100dvh] sm:h-[100dvh] md:h-[94vh] md:w-[96%] md:max-w-7xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden"
            @click.stop>
            <GeneralModalHeader :title="modalTitle" :subtitle="modalSubtitle" :total-errors="totalErrors" mode="create"
                @close="closeModal" />

            <GeneralModalContent :columns="1" scroll-mode="controlled">
                <AdjustStockData :form="form" :product="product" :frontend-errors="frontendErrors"
                    :type-options="typeOptions" :reason-options="reasonOptions" :current-stock="currentStock"
                    :projected-stock="projectedStock" :recent-movements="product.recentMovements || []"
                    :total-batch-quantity="totalBatchQuantity" :total-manual-batch-quantity="totalManualBatchQuantity"
                    @validate="validateField" @add-batch="addBatch" @remove-batch="removeBatch"
                    @add-manual-batch="addManualBatch" @remove-manual-batch="removeManualBatch"
                    @edit-batch="editBatch" />
            </GeneralModalContent>

            <GeneralModalFooter :processing="form.processing" :save-button-text="saveButtonText"
                close-button-text="Cancelar" mode="create" @save="saveAdjustment" @close="closeModal" />
        </div>

        <EditBatchModal v-if="showEditBatchModal && liveSelectedBatch" :batch="liveSelectedBatch"
            @close="closeEditBatchModal" />
    </div>
</template>