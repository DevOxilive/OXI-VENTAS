<script setup>
import InputField from '@/Components/Forms/InputField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
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
        <form class="space-y-5" @submit.prevent="saveBatch">
            <section class="rounded-3xl border border-slate-200 bg-white overflow-hidden">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="font-black text-slate-900">
                        {{ productName }}
                    </h3>

                    <p class="mt-1 text-sm text-slate-500">
                        Código escaneado: {{ product.scanned_code || product.barcode || 'Sin código' }}
                    </p>
                </div>

                <div class="p-5 space-y-4">
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                        <p class="text-sm font-bold text-amber-900">
                            El lote es obligatorio.
                        </p>

                        <p class="mt-1 text-xs text-amber-800">
                            Si el producto no tiene lote, genera uno. Ejemplo: Dulce de leche.
                            El sistema lo guardara como: Dulce-De-Leche-{{ today }}.
                        </p>
                    </div>

                    <InputField
                        v-model="form.lot_number"
                        label="Numero de lote"
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

                    <div v-if="totalErrors" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 space-y-1">
                        <p
                            v-for="(error, field) in form.errors"
                            :key="field"
                            class="text-sm font-semibold text-red-700"
                        >
                            {{ error }}
                        </p>
                    </div>
                </div>
            </section>
        </form>
    </GlobalModal>
</template>
