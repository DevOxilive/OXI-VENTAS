<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'

import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import InputField from '@/Components/Forms/InputField.vue'

const emit = defineEmits(['close'])

const props = defineProps({
    batch: {
        type: Object,
        required: true,
    },
})

const form = reactive({
    id: null,
    lot_number: '',
    expiration_date: '',
    supplier: '',
    received_at: '',
    quantity: '',
    original_quantity: 0,
})

watch(
    () => props.batch,
    (batch) => {
        form.id = batch.id
        form.lot_number = batch.lot_number || ''
        form.expiration_date = batch.expiration_date || ''
        form.supplier = batch.supplier || ''
        form.received_at = batch.received_at || ''
        form.quantity = ''
        form.original_quantity = Number(batch.quantity || 0)
    },
    { immediate: true }
)

const batchQuantityDifference = computed(() => {
    if (form.quantity === '' || form.quantity === null) {
        return 0
    }

    return Number(form.quantity || 0) - Number(form.original_quantity || 0)
})

const batchQuantityDifferenceText = computed(() => {
    if (form.quantity === '' || form.quantity === null) {
        return 'Ingresa la cantidad real corregida para calcular el ajuste.'
    }

    if (batchQuantityDifference.value > 0) {
        return `Se agregarán ${batchQuantityDifference.value} unidad(es) al lote y al stock general.`
    }

    if (batchQuantityDifference.value < 0) {
        return `Se descontarán ${Math.abs(batchQuantityDifference.value)} unidad(es) del lote y del stock general.`
    }

    return 'La cantidad disponible no tendrá cambios.'
})

const canSave = computed(() => {
    return form.quantity !== '' && form.quantity !== null && Number(form.quantity) >= 0
})

function closeModal() {
    emit('close')
}

function handleEscape(event) {
    if (event.key === 'Escape') {
        closeModal()
    }
}

function saveEditedBatch() {
    if (!canSave.value) return

    router.put(
        route('inventario.product-batches.update', form.id),
        {
            lot_number: form.lot_number || null,
            expiration_date: form.expiration_date || null,
            supplier: form.supplier || null,
            received_at: form.received_at || null,
            quantity: Number(form.quantity),
        },
        {
            preserveScroll: true,
            onSuccess: closeModal,
        }
    )
}

onMounted(() => {
    document.addEventListener('keydown', handleEscape)
})

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleEscape)
})
</script>

<template>
    <div class="fixed inset-0 z-[60] bg-black/60 flex items-end md:items-center justify-center">
        <div class="absolute inset-0" @click="closeModal"></div>

        <div
            class="relative bg-white w-full h-[100dvh] sm:h-[100dvh] md:h-[94vh] md:w-[96%] md:max-w-2xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden">
            <GeneralModalHeader :title="form.lot_number ? 'Editar lote' : 'Editar cantidad sin lote'"
                subtitle="Corrige información registrada" :total-errors="0" mode="edit" @close="closeModal" />

            <GeneralModalContent :columns="1">
                <div class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <InputField v-model="form.lot_number" label="Número de lote" />

                        <InputField v-model="form.expiration_date" label="Fecha de caducidad" type="date" />

                        <InputField v-model="form.supplier" label="Proveedor" />

                        <InputField v-model="form.received_at" label="Fecha de ingreso" type="date" />

                        <InputField v-model="form.quantity" label="Cantidad disponible real" type="number"
                            class="sm:col-span-2" />
                    </div>

                    <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">
                            Cantidad registrada originalmente
                        </p>

                        <p class="text-lg font-black text-slate-900">
                            {{ form.original_quantity }}
                        </p>

                        <p class="text-sm font-semibold mt-2" :class="batchQuantityDifference === 0
                            ? 'text-slate-500'
                            : batchQuantityDifference > 0
                                ? 'text-emerald-600'
                                : 'text-red-600'">
                            {{ batchQuantityDifferenceText }}
                        </p>
                    </div>
                </div>
            </GeneralModalContent>

            <GeneralModalFooter :processing="false" save-button-text="Guardar cambios" mode="edit"
                @save="saveEditedBatch" @close="closeModal" />
        </div>
    </div>
</template>