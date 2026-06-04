<script setup>
import { onMounted, onBeforeUnmount, computed, ref, watch } from 'vue'
import { useAdjustStockForm } from '@/Composables/Inventory/useAdjustStockForm'

import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
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
    validateField,
    saveAdjustment,
    addBatch,
} = useAdjustStockForm(props, emit)

const usesLot = ref(false)

const productName = computed(() => {
    return props.product?.name ?? props.product?.product?.name ?? 'Producto'
})

const unit = computed(() => props.product?.unit ?? 'pieza')

const entry = computed(() => form.batches?.[0] ?? null)

const today = computed(() => {
    return new Date().toISOString().slice(0, 10)
})

const totalErrors = computed(() => {
    return errorSummary.value.length
})
function ensureEntryReady() {
    form.type = 'IN'
    form.reason = 'PURCHASE'

    if (!form.batches?.length) {
        addBatch()
    }

    if (entry.value) {
        entry.value.received_at = entry.value.received_at || today.value
        entry.value.lot_number = usesLot.value ? entry.value.lot_number || '' : null
    }
}

function syncQuantity() {
    if (!entry.value) return

    form.quantity = entry.value.quantity || ''
}

function toggleLot() {
    usesLot.value = !usesLot.value

    if (!entry.value) return

    entry.value.lot_number = usesLot.value ? '' : null
}

function validateEntry() {
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

    if (!entry.value?.expiration_date) {
        frontendErrors.expiration_date = 'La caducidad es obligatoria.'
    }

    if (usesLot.value && !entry.value?.lot_number) {
        frontendErrors.lot_number = 'El número de lote es obligatorio.'
    }

    return !frontendErrors.quantity
        && !frontendErrors.received_at
        && !frontendErrors.expiration_date
        && !frontendErrors.lot_number
        && !frontendErrors.batches
}

function saveEntry() {
    if (!validateEntry()) return

    saveAdjustment()
}

function closeModal() {
    if (form.processing) return

    emit('close')
}

function handleEsc(e) {
    if (e.key === 'Escape') closeModal()
}

watch(
    entry,
    () => ensureEntryReady(),
    { immediate: true }
)

onMounted(() => {
    ensureEntryReady()
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

        <div class="relative bg-white w-full h-[100dvh] md:h-auto md:max-h-[92vh] md:w-[96%] md:max-w-6xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden"
            @click.stop>
            <GeneralModalHeader title="Entrada" subtitle="Registra producto que entra al inventario."
                :total-errors="totalErrors" mode="create" @close="closeModal" />

            <GeneralModalContent :columns="1">
                <section v-if="entry"
                    class="w-full max-w-5xl mx-auto rounded-3xl border border-slate-200 bg-white overflow-hidden">
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
                                    field="quantity" :readonly="form.processing" @blur="validateField('quantity')" />

                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-sm font-bold text-slate-800">
                                                ¿Tiene lote?
                                            </p>

                                            <p class="text-xs text-slate-500 mt-0.5">
                                                Actívalo si el producto trae número de lote.
                                            </p>
                                        </div>

                                        <button type="button" :disabled="form.processing"
                                            class="relative inline-flex h-8 w-16 items-center rounded-full transition disabled:opacity-50 shrink-0"
                                            :class="usesLot ? 'bg-[#1f1d2b]' : 'bg-slate-300'" @click="toggleLot">
                                            <span
                                                class="inline-block h-6 w-6 transform rounded-full bg-white shadow transition"
                                                :class="usesLot ? 'translate-x-9' : 'translate-x-1'" />

                                            <span class="absolute text-[10px] font-black uppercase"
                                                :class="usesLot ? 'left-3 text-white' : 'right-3 text-slate-600'">
                                                {{ usesLot ? 'Sí' : 'No' }}
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <InputField v-if="usesLot" v-model="entry.lot_number" label="Número de lote"
                                    placeholder="Ej. LALA-001" field="lot_number" :readonly="form.processing"
                                    @blur="validateField('lot_number')" />

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <InputField v-model="entry.received_at" label="Fecha de entrada" type="date"
                                        field="received_at" :readonly="form.processing"
                                        @blur="validateField('received_at')" />

                                    <InputField v-model="entry.expiration_date" label="Caducidad" type="date"
                                        field="expiration_date" :readonly="form.processing"
                                        @blur="validateField('expiration_date')" />
                                </div>

                                <InputField v-model="entry.supplier" label="Proveedor" placeholder="Opcional"
                                    field="supplier" :readonly="form.processing" />

                                <TextareaField v-model="form.notes" label="Notas" placeholder="Opcional" field="notes"
                                    :readonly="form.processing" />
                            </div>
                        </div>

                        <div v-if="usesLot" class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                            <p class="text-sm text-amber-800">
                                El número de lote será obligatorio para esta entrada.
                            </p>
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
            </GeneralModalContent>

            <GeneralModalFooter :processing="form.processing" save-button-text="Registrar entrada"
                close-button-text="Cancelar" mode="create" @save="saveEntry" @close="closeModal" />
        </div>
    </div>
</template>