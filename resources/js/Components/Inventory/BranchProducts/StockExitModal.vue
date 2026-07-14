<script setup>
import { computed } from 'vue'
import { useStockExitModal } from '@/Composables/Inventory/useStockExitModal'

import FormPanel from '@/Components/Cards/FormPanel.vue'
import SectionHeading from '@/Components/Cards/SectionHeading.vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import { getStockExitModalConfig } from '@/config/ModalConfigs/stockExitModalConfig'

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
    currentStock,
    productName,
    unit,
    reasonOptions,
    availableSources,
    selectedSourceKey,
    totalErrors,

    validateField,
    closeModal,
    selectSource,
    submitExit,
    sourceLabel,
    formatDate,
    expirationLabel,
    expirationClass,
    expirationBadgeClass,
} = useStockExitModal(props, emit)

const modalConfig = computed(() => getStockExitModalConfig({
    totalErrors: totalErrors.value,
    processing: form.processing,
}))
</script>

<template>
    <GlobalModal
        v-bind="modalConfig"
        @save="submitExit"
        @close="closeModal"
    >
        <section class="min-h-0 w-full">
            <SectionHeading
                :title="productName"
                :description="`Stock actual: ${currentStock} ${unit}`"
                :bordered="true"
                spacing="md"
            />

            <div class="grid grid-cols-1 gap-6 p-5 lg:grid-cols-[minmax(0,1fr)_430px]">
                <FormPanel
                    title="Salida de stock"
                    description="Captura la cantidad, el motivo y las notas relacionadas con esta salida."
                    panel-class="bg-background shadow-sm"
                    body-class="space-y-4"
                >
                    <InputField
                        v-model="form.quantity"
                        :label="`Cantidad a retirar (${unit})`"
                        type="number"
                        field="quantity"
                        :readonly="form.processing"
                        :error="frontendErrors.quantity || form.errors.quantity"
                        @blur="validateField('quantity')"
                    />

                    <SelectField
                        v-model="form.reason"
                        label="Motivo"
                        field="reason"
                        :options="reasonOptions"
                        :disabled="form.processing"
                        :error="frontendErrors.reason || form.errors.reason"
                        @validate="validateField('reason')"
                    />

                    <TextareaField
                        v-model="form.notes"
                        label="Notas"
                        placeholder="Opcional"
                        field="notes"
                        :readonly="form.processing"
                    />
                </FormPanel>

                <FormPanel
                    title="Origen del producto"
                    description="Selecciona de donde saldra el producto para registrar correctamente la salida."
                    panel-class="min-w-0 bg-background shadow-sm"
                >
                    <div class="grid max-h-[380px] grid-cols-1 gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                        <button
                            v-for="source in availableSources"
                            :key="source.key"
                            type="button"
                            :disabled="form.processing || source.quantity <= 0"
                            class="rounded-2xl border px-3 py-3 text-left transition disabled:cursor-not-allowed disabled:opacity-50"
                            :class="expirationClass(
                                source.expiration_date,
                                selectedSourceKey === source.key,
                            )"
                            @click="selectSource(source)"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="truncate text-sm font-black">
                                    {{ sourceLabel(source) }}
                                </p>

                                <span
                                    v-if="source.expiration_date"
                                    class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-black"
                                    :class="expirationBadgeClass(
                                        source.expiration_date,
                                        selectedSourceKey === source.key,
                                    )"
                                >
                                    {{ expirationLabel(source.expiration_date) }}
                                </span>
                            </div>

                            <p
                                class="mt-1 text-xs"
                                :class="selectedSourceKey === source.key ? 'text-white/80' : 'text-text opacity-70'"
                            >
                                Disponible: {{ source.quantity }} {{ unit }}
                            </p>

                            <p
                                v-if="source.expiration_date"
                                class="mt-0.5 text-xs"
                                :class="selectedSourceKey === source.key ? 'text-white/80' : 'text-text opacity-70'"
                            >
                                Caduca: {{ formatDate(source.expiration_date) }}
                            </p>
                        </button>
                    </div>
                </FormPanel>
            </div>

            <div
                v-if="
                    frontendErrors.quantity ||
                    frontendErrors.reason ||
                    frontendErrors.manual_batches ||
                    frontendErrors.stock
                "
                class="mt-5 space-y-1 rounded-2xl border border-primary bg-secondary px-4 py-3"
            >
                <p v-if="frontendErrors.manual_batches" class="text-sm font-semibold text-primary">
                    {{ frontendErrors.manual_batches }}
                </p>

                <p v-if="frontendErrors.quantity" class="text-sm font-semibold text-primary">
                    {{ frontendErrors.quantity }}
                </p>

                <p v-if="frontendErrors.reason" class="text-sm font-semibold text-primary">
                    {{ frontendErrors.reason }}
                </p>

                <p v-if="frontendErrors.stock" class="text-sm font-semibold text-primary">
                    {{ frontendErrors.stock }}
                </p>
            </div>
        </section>
    </GlobalModal>
</template>
