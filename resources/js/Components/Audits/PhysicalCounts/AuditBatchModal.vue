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
    today,
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
                :description="`Código escaneado: ${product.scanned_code || product.barcode || 'Sin código'}`"
                body-class="space-y-4"
            >
                <div class="flex items-start gap-3 rounded-xl border border-accent bg-background px-4 py-3">
                    <span class="material-symbols-outlined text-xl text-accent">info</span>
                    <div>
                        <p class="text-sm font-semibold text-text">
                            El número de lote es obligatorio.
                        </p>
                        <p class="mt-1 text-xs text-text opacity-70">
                            Si el producto no tiene lote, genera uno. Por ejemplo, “Dulce de leche”
                            se guardará como “Dulce-De-Leche-{{ today }}”.
                        </p>
                    </div>
                </div>

                <InputField
                    v-model="form.lot_number"
                    label="Número de lote"
                    placeholder="Ej. Dulce de leche"
                    field="lot_number"
                    :readonly="form.processing"
                    :error="form.errors.lot_number"
                />

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <InputField
                        :model-value="today"
                        label="Fecha de entrada"
                        type="date"
                        field="received_at"
                        :readonly="true"
                        :min="today"
                        :max="today"
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
                </div>

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
