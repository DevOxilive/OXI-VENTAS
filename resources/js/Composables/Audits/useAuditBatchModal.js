import { computed, onBeforeUnmount, onMounted } from 'vue'
import { useForm } from '@inertiajs/vue3'

import { getModalRequestOptions } from '@/Components/Modales/useModalConfig'
import { getAuditBatchModalConfig } from '@/config/ModalConfigs/auditBatchModalConfig'

export function useAuditBatchModal(props, emit) {
    const form = useForm({
        branch_product_id: props.product.branch_product_id,
        scanned_code: props.product.scanned_code,
        lot_number: '',
        expiration_date: '',
        supplier: '',
        notes: '',
    })

    const productName = computed(() => props.product?.name ?? 'Producto')
    const today = computed(() => new Date().toISOString().slice(0, 10))
    const minExpirationDate = computed(() => {
        const date = new Date()
        date.setDate(date.getDate() + 1)

        return date.toISOString().slice(0, 10)
    })

    const totalErrors = computed(() => Object.keys(form.errors || {}).length)

    const modalConfig = computed(() => getAuditBatchModalConfig({
        totalErrors: totalErrors.value,
        processing: form.processing,
        productName: productName.value,
    }))

    function formatLotNumber(value) {
        return String(value || '')
            .trim()
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
    }

    function closeModal() {
        if (form.processing) return
        emit('close')
    }

    function saveBatch() {
        const cleanLotNumber = formatLotNumber(form.lot_number)
        const lotNumber = cleanLotNumber ? `${cleanLotNumber}-${today.value}` : ''

        form.lot_number = lotNumber

        form.post(
            route('audits.physical-counts.batches.store', props.physicalCountId),
            getModalRequestOptions({
                mode: 'create',
                entityName: modalConfig.value.alerts.entityName,
                close: () => emit('close'),
                successTitle: modalConfig.value.alerts.create.successTitle,
                errorTitle: modalConfig.value.alerts.create.errorTitle,
                errorMessage: modalConfig.value.alerts.create.errorMessage,
                onSuccess: () => {
                    emit('created', lotNumber)
                },
            })
        )
    }

    function handleEsc(event) {
        if (event.key === 'Escape') {
            closeModal()
        }
    }

    onMounted(() => {
        window.addEventListener('keydown', handleEsc)
    })

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', handleEsc)
    })

    return {
        form,
        modalConfig,
        productName,
        today,
        minExpirationDate,
        totalErrors,
        closeModal,
        saveBatch,
    }
}
