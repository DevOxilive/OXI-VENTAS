<script setup>
import { computed, onMounted, onBeforeUnmount } from 'vue'
import { useForm } from '@inertiajs/vue3'

import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import InputField from '@/Components/Forms/InputField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'

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

function formatLotNumber(value) {
    return String(value || '')
        .trim()
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
}

function saveBatch() {
    const cleanLotNumber = formatLotNumber(form.lot_number)
    const lotNumber = cleanLotNumber ? `${cleanLotNumber}-${today.value}` : ''
    form.lot_number = lotNumber

    form.post(route('audits.physical-counts.batches.store', props.physicalCountId), {
        preserveScroll: true,
        onSuccess: () => {
            emit('created', lotNumber)
            emit('close')
        },
    })
}

function closeModal() {
    if (form.processing) return

    emit('close')
}

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
    <div class="fixed inset-0 z-50 bg-black/60 flex items-end md:items-center justify-center" role="dialog" aria-modal="true">
        <div class="absolute inset-0" @click="closeModal"></div>

        <div
            class="relative bg-white w-full h-[100dvh] md:h-auto md:max-h-[92vh] md:w-[96%] md:max-w-6xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden"
            @click.stop
        >
            <GeneralModalHeader
                title="Crear lote"
                subtitle="Registra un lote para usarlo en este conteo físico."
                :total-errors="totalErrors"
                mode="create"
                @close="closeModal"
            />

            <GeneralModalContent :columns="1">
                <section class="w-full max-w-5xl mx-auto rounded-3xl border border-slate-200 bg-white overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h3 class="font-black text-slate-900">
                            {{ productName }}
                        </h3>

                        <p class="text-sm text-slate-500 mt-1">
                            Código escaneado: {{ product.scanned_code || product.barcode || 'Sin código' }}
                        </p>
                    </div>

                    <div class="p-5">
                        <div class="space-y-4">
                            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                                <p class="text-sm font-bold text-amber-900">
                                    El lote es obligatorio.
                                </p>

                                <p class="text-xs text-amber-800 mt-1">
                                    Si el producto no tiene lote, genera uno. Ejemplo: Dulce de leche.
                                    El sistema lo guardará como: Dulce-De-Leche-{{ today }}.
                                </p>
                            </div>

                            <InputField
                                v-model="form.lot_number"
                                label="Número de lote"
                                placeholder="Ej. Dulce de leche"
                                field="lot_number"
                                :readonly="form.processing"
                                :error="form.errors.lot_number"
                            />

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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
                        </div>

                        <div v-if="totalErrors" class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 space-y-1">
                            <p v-for="(error, field) in form.errors" :key="field" class="text-sm font-semibold text-red-700">
                                {{ error }}
                            </p>
                        </div>
                    </div>
                </section>
            </GeneralModalContent>

            <GeneralModalFooter
                :processing="form.processing"
                save-button-text="Guardar lote"
                close-button-text="Cancelar"
                mode="create"
                @save="saveBatch"
                @close="closeModal"
            />
        </div>
    </div>
</template>
