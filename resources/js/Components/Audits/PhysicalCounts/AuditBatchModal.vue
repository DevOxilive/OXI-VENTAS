<script setup>
import InputField from '@/Components/Forms/InputField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import FormPanel from '@/Components/Cards/FormPanel.vue'
import { useAuditBatchModal } from '@/Composables/Audits/useAuditBatchModal'

const emit = defineEmits(['close', 'created'])

const props = defineProps({
    physicalCountId: {
        type: Number,
        required: true,
    },
    product: {
        type: Object,
        required: true,
    },
})

const {
    form,
    modalConfig,
    productName,
    minExpirationDate,
    totalErrors,
    closeModal,
    saveBatch,
} = useAuditBatchModal(props, emit)
</script>

<template>
    <GlobalModal
        v-bind="modalConfig"
        @save="saveBatch"
        @close="closeModal"
    >
        <form @submit.prevent="saveBatch">
            <FormPanel
                :title="productName"
                :description="`Código principal: ${product.primary_code || product.barcode || 'Sin código'}`"
                body-class="space-y-4"
            >
                <div class="rounded-xl border border-secondary bg-background px-4 py-3">
                    <p class="text-sm font-semibold text-text">{{ productName }}</p>
                    <p class="mt-1 text-xs text-text opacity-70">
                        Registra solo los datos del lote. La cantidad se aplicará al cerrar y aplicar la auditoría.
                    </p>
                </div>

                <div class="flex items-start gap-3 rounded-xl border border-accent bg-background px-4 py-3">
                    <span class="material-symbols-outlined text-xl text-accent">info</span>
                    <div>
                        <p class="text-sm font-semibold text-text">
                            El número de lote es obligatorio.
                        </p>
                        <p class="mt-1 text-xs text-text opacity-70">
                            Si el producto no tiene lote, captura una clave simple. Ej. OXI-1.
                        </p>
                    </div>
                </div>

                <InputField
                    v-model="form.lot_number"
                    label="Número de lote"
                    placeholder="Ej. OXI-1"
                    field="lot_number"
                    :readonly="form.processing"
                    :error="form.errors.lot_number"
                />

                <InputField
                    v-model="form.expiration_date"
                    label="Caducidad"
                    type="date"
                    field="expiration_date"
                    :readonly="form.processing"
                    :min="minExpirationDate"
                    :error="form.errors.expiration_date"
                />

                <InputField
                    v-model="form.supplier"
                    label="Proveedor"
                    placeholder="Opcional"
                    field="supplier"
                    :readonly="form.processing"
                    :error="form.errors.supplier"
                />

                <TextareaField
                    v-model="form.notes"
                    label="Notas"
                    placeholder="Opcional"
                    field="notes"
                    :readonly="form.processing"
                />

                <div v-if="totalErrors" class="space-y-1 rounded-xl border border-danger bg-background px-4 py-3">
                    <p
                        v-for="(error, field) in form.errors"
                        :key="field"
                        class="text-sm font-semibold text-danger"
                    >
                        {{ error }}
                    </p>
                </div>
            </FormPanel>
        </form>
    </GlobalModal>
</template>
