<script setup>
import { useStockExitModal } from '@/Composables/Inventory/useStockExitModal'
import { computed } from 'vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import { getStockExitModalConfig } from '@/config/ModalConfigs/stockExitModalConfig'

import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'

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
        <section class="w-full overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h3 class="font-black text-slate-900">
                            {{ productName }}
                        </h3>

                        <p class="text-sm text-slate-500 mt-1">
                            Stock actual: {{ currentStock }} {{ unit }}
                        </p>
                    </div>

                    <div class="p-5">
                        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_430px] gap-6">
                            <section class="space-y-4">
                                <InputField v-model="form.quantity" :label="`Cantidad a retirar (${unit})`"
                                    type="number" field="quantity" :readonly="form.processing" :error="frontendErrors.quantity ||
                                        form.errors.quantity
                                        " @blur="validateField('quantity')" />

                                <SelectField v-model="form.reason" label="Motivo" field="reason"
                                    :options="reasonOptions" :disabled="form.processing" :error="frontendErrors.reason ||
                                        form.errors.reason
                                        " @validate="validateField('reason')" />

                                <TextareaField v-model="form.notes" label="Notas" placeholder="Opcional" field="notes"
                                    :readonly="form.processing" />
                            </section>

                            <section class="min-w-0">
                                <h4 class="text-sm font-black text-slate-800 mb-3">
                                    Selecciona de dónde saldrá el producto
                                </h4>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-[380px] overflow-y-auto pr-1">
                                    <button v-for="source in availableSources" :key="source.key" type="button"
                                        :disabled="form.processing ||
                                            source.quantity <= 0
                                            "
                                        class="text-left rounded-2xl border px-3 py-3 transition disabled:opacity-50 disabled:cursor-not-allowed"
                                        :class="expirationClass(
                                            source.expiration_date,
                                            selectedSourceKey ===
                                            source.key,
                                        )
                                            " @click="selectSource(source)">
                                        <div class="flex items-start justify-between gap-2">
                                            <p class="font-black text-sm truncate">
                                                {{ sourceLabel(source) }}
                                            </p>

                                            <span v-if="source.expiration_date"
                                                class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-black" :class="expirationBadgeClass(
                                                    source.expiration_date,
                                                    selectedSourceKey ===
                                                    source.key,
                                                )
                                                    ">
                                                {{
                                                    expirationLabel(
                                                        source.expiration_date,
                                                    )
                                                }}
                                            </span>
                                        </div>

                                        <p class="text-xs mt-1" :class="selectedSourceKey === source.key
                                            ? 'text-slate-200'
                                            : 'text-slate-500'
                                            ">
                                            Disponible: {{ source.quantity }}
                                            {{ unit }}
                                        </p>

                                        <p v-if="source.expiration_date" class="text-xs mt-0.5" :class="selectedSourceKey === source.key
                                            ? 'text-slate-200'
                                            : 'text-slate-500'
                                            ">
                                            Caduca:
                                            {{
                                                formatDate(
                                                    source.expiration_date,
                                                )
                                            }}
                                        </p>
                                    </button>
                                </div>
                            </section>
                        </div>

                        <div v-if="
                            frontendErrors.quantity ||
                            frontendErrors.reason ||
                            frontendErrors.manual_batches ||
                            frontendErrors.stock
                        " class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 space-y-1">
                            <p v-if="frontendErrors.manual_batches" class="text-sm font-semibold text-red-700">
                                {{ frontendErrors.manual_batches }}
                            </p>

                            <p v-if="frontendErrors.quantity" class="text-sm font-semibold text-red-700">
                                {{ frontendErrors.quantity }}
                            </p>

                            <p v-if="frontendErrors.reason" class="text-sm font-semibold text-red-700">
                                {{ frontendErrors.reason }}
                            </p>

                            <p v-if="frontendErrors.stock" class="text-sm font-semibold text-red-700">
                                {{ frontendErrors.stock }}
                            </p>
                        </div>
                    </div>
        </section>
    </GlobalModal>
</template>
