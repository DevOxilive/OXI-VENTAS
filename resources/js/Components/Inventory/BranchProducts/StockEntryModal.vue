<script setup>
import { onMounted, computed, watch } from 'vue'
import { useAdjustStockForm } from '@/Composables/Inventory/useAdjustStockForm'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import { getStockEntryModalConfig } from '@/config/ModalConfigs/stockEntryModalConfig'

import InputField from '@/Components/Forms/InputField.vue'
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
    errorSummary,
    saveAdjustment,
    addBatch,
} = useAdjustStockForm(props, emit)

const productName = computed(() => {
    return props.product?.name ?? props.product?.product?.name ?? 'Producto'
})

const unit = computed(() => props.product?.unit ?? 'pieza')

const entry = computed(() => form.batches?.[0] ?? null)

const today = computed(() => new Date().toISOString().slice(0, 10))

const minExpirationDate = computed(() => {
    const date = new Date(today.value)
    date.setDate(date.getDate() + 1)

    return date.toISOString().slice(0, 10)
})

const totalErrors = computed(() => errorSummary.value.length)

const modalConfig = computed(() => getStockEntryModalConfig({
    totalErrors: totalErrors.value,
    processing: form.processing,
}))

function formatLotNumber(value) {
    if (!value) return ''

    return value
        .toString()
        .trim()
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
}

function ensureLotFormat() {
    if (!entry.value?.lot_number) return

    const cleanLot = formatLotNumber(entry.value.lot_number)
    entry.value.lot_number = `${cleanLot}-${today.value}`
}

function ensureEntryReady() {
    form.type = 'IN'
    form.reason = 'PURCHASE'
    form.batch_allocation_method = 'MANUAL'
    form.manual_batches = []

    if (!form.batches?.length) {
        addBatch()
    }

    if (entry.value) {
        entry.value.received_at = today.value
        entry.value.lot_number = entry.value.lot_number || ''

        if (
            entry.value.expiration_date &&
            entry.value.expiration_date <= today.value
        ) {
            entry.value.expiration_date = ''
        }
    }

    syncQuantity()
}

function syncQuantity() {
    if (!entry.value) return

    form.quantity = entry.value.quantity || ''
}

function validateEntry() {
    syncQuantity()

    frontendErrors.quantity = ''
    frontendErrors.received_at = ''
    frontendErrors.expiration_date = ''
    frontendErrors.lot_number = ''
    frontendErrors.batches = ''

    if (!entry.value?.quantity || Number(entry.value.quantity) <= 0) {
        frontendErrors.quantity = 'La cantidad debe ser mayor a cero.'
    }

    if (!entry.value?.received_at) {
        frontendErrors.received_at = 'La fecha de entrada es obligatoria.'
    }

    if (entry.value?.received_at !== today.value) {
        frontendErrors.received_at = 'La fecha de entrada debe ser el día de hoy.'
    }

    if (!entry.value?.expiration_date) {
        frontendErrors.expiration_date = 'La caducidad es obligatoria.'
    }

    if (
        entry.value?.expiration_date &&
        entry.value.expiration_date <= entry.value.received_at
    ) {
        frontendErrors.expiration_date = 'La caducidad debe ser mayor a la fecha de entrada.'
    }

    if (!entry.value?.lot_number) {
        frontendErrors.lot_number = 'El número de lote es obligatorio.'
    }

    return !frontendErrors.quantity
        && !frontendErrors.received_at
        && !frontendErrors.expiration_date
        && !frontendErrors.lot_number
        && !frontendErrors.batches
}

function saveEntry() {
    syncQuantity()
    ensureLotFormat()

    if (!validateEntry()) return

    saveAdjustment()
}

function closeModal() {
    if (form.processing) return

    emit('close')
}

watch(
    () => entry.value?.quantity,
    () => syncQuantity()
)

watch(
    entry,
    () => ensureEntryReady(),
    { immediate: true }
)

onMounted(() => {
    ensureEntryReady()
})
</script>

<template>
    <GlobalModal
        v-bind="modalConfig"
        @save="saveEntry"
        @close="closeModal"
    >
        <section
            v-if="entry"
            class="min-h-0 w-full"
        >
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h3 class="font-black text-slate-900">
                            {{ productName }}
                        </h3>

                        <p class="text-sm text-slate-500 mt-1">
                            Stock actual: {{ currentStock }} {{ unit }}
                        </p>
                    </div>

                    <div class="p-5">
                        <div class="grid grid-cols-1 gap-5">
                            <div class="space-y-4">
                                <InputField v-model="entry.quantity" :label="`Cantidad (${unit})`" type="number"
                                    field="quantity" :readonly="form.processing" @input="syncQuantity"
                                    @blur="validateEntry" />

                                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                                    <p class="text-sm font-bold text-amber-900">
                                        El lote es obligatorio.
                                    </p>

                                    <p class="text-xs text-amber-800 mt-1">
                                        Si el producto no tiene lote, genera uno. Ejemplo: Dulce de leche.
                                        El sistema lo guardará como: Dulce-De-Leche-{{ today }}.
                                    </p>
                                </div>

                                <InputField v-model="entry.lot_number" label="Número de lote"
                                    placeholder="Ej. Dulce de leche" field="lot_number" :readonly="form.processing"
                                    @blur="validateEntry" />

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <InputField v-model="entry.received_at" label="Fecha de entrada" type="date"
                                        field="received_at" :readonly="true" :min="today" :max="today"
                                        @blur="validateEntry" />

                                    <InputField v-model="entry.expiration_date" label="Caducidad" type="date"
                                        field="expiration_date" :readonly="form.processing" :min="minExpirationDate"
                                        @blur="validateEntry" />
                                </div>

                                <InputField v-model="entry.supplier" label="Proveedor" placeholder="Opcional"
                                    field="supplier" :readonly="form.processing" />

                                <TextareaField v-model="form.notes" label="Notas" placeholder="Opcional" field="notes"
                                    :readonly="form.processing" />
                            </div>
                        </div>

                        <div v-if="frontendErrors.quantity || frontendErrors.received_at || frontendErrors.expiration_date || frontendErrors.lot_number || frontendErrors.batches"
                            class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 space-y-1">
                            <p v-if="frontendErrors.quantity" class="text-sm font-semibold text-red-700">
                                {{ frontendErrors.quantity }}
                            </p>

                            <p v-if="frontendErrors.received_at" class="text-sm font-semibold text-red-700">
                                {{ frontendErrors.received_at }}
                            </p>

                            <p v-if="frontendErrors.expiration_date" class="text-sm font-semibold text-red-700">
                                {{ frontendErrors.expiration_date }}
                            </p>

                            <p v-if="frontendErrors.lot_number" class="text-sm font-semibold text-red-700">
                                {{ frontendErrors.lot_number }}
                            </p>

                            <p v-if="frontendErrors.batches" class="text-sm font-semibold text-red-700">
                                {{ frontendErrors.batches }}
                            </p>
                        </div>
                    </div>
        </section>
    </GlobalModal>
</template>
