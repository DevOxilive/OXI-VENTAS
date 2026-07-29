<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

import AuditBatchModal from '@/Components/Audits/PhysicalCounts/AuditBatchModal.vue'
import FormPanel from '@/Components/Cards/FormPanel.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import { ErrorAlert, ToastAlert } from '@/Components/Modales/UniversalActionModal'

const props = defineProps({
    physicalCountId: {
        type: Number,
        required: true,
    },
    product: {
        type: Object,
        default: null,
    },
    canViewStock: {
        type: Boolean,
        default: false,
    },
})

const form = useForm({
    branch_product_id: '',
    product_batch_id: '',
    product_id: '',
    scanned_code: '',
    counted_quantity: '',
    damaged_quantity: '',
    expired_quantity: '',
    expiration_date: '',
    notes: '',
})

const showCreateBatch = ref(false)
const pendingLotNumber = ref('')

const batchOptions = computed(() =>
    (props.product?.batches || []).map((batch) => ({
        value: batch.id,
        label: batchOptionLabel(batch),
    })),
)
const countedQuantity = computed(() => Number(form.counted_quantity || 0))
const damagedQuantity = computed(() => Number(form.damaged_quantity || 0))
const expiredQuantity = computed(() => Number(form.expired_quantity || 0))
const invalidQuantities = computed(() =>
    damagedQuantity.value + expiredQuantity.value > countedQuantity.value,
)

watch(
    () => props.product,
    (product) => {
        if (!product) return

        form.branch_product_id = product.branch_product_id
        form.product_id = product.product_id
        form.scanned_code = product.scanned_code

        const pendingBatch = pendingLotNumber.value
            ? product.batches?.find((batch) => batch.lot_number === pendingLotNumber.value)
            : null

        form.product_batch_id = pendingBatch?.id ?? ''
    },
    { immediate: true },
)

function batchOptionLabel(batch) {
    const parts = [
        `Lote: ${batch.lot_number ?? 'Sin lote'}`,
        `Caducidad: ${batch.expiration_date ?? 'Sin fecha'}`,
    ]

    if (props.canViewStock) {
        parts.push(`Existencia: ${batch.quantity ?? 0}`)
    }

    return parts.join(' | ')
}

function handleBatchCreated(lotNumber) {
    pendingLotNumber.value = lotNumber
}

function submit() {
    if (!props.product || invalidQuantities.value) return

    form.post(route('audits.physical-counts.entries.store', props.physicalCountId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            pendingLotNumber.value = ''
            ToastAlert({ title: 'Conteo guardado correctamente' })
        },
        onError: () => {
            ErrorAlert({
                title: 'Error al guardar el conteo',
                message: 'No fue posible registrar las cantidades capturadas.',
            })
        },
    })
}
</script>

<template>
    <FormPanel
        title="Captura del producto seleccionado"
        description="Registra las cantidades físicas encontradas y el lote correspondiente."
        panel-class="bg-background"
    >
        <template #header>
            <AppButton variant="secondary" @click="showCreateBatch = true">
                <span class="material-symbols-outlined mr-2 text-[18px]">add_box</span>
                Crear lote
            </AppButton>
        </template>

        <form class="space-y-4" @submit.prevent="submit">
            <SelectField
                v-model="form.product_batch_id"
                label="Lote y caducidad"
                field="product_batch_id"
                :options="batchOptions"
                placeholder="Selecciona un lote"
                :error="form.errors.product_batch_id"
            />

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <InputField
                    v-model="form.counted_quantity"
                    label="Cantidad contada"
                    field="counted_quantity"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="Ej. 10"
                    :error="form.errors.counted_quantity"
                />

                <InputField
                    v-model="form.damaged_quantity"
                    label="Cantidad dañada"
                    field="damaged_quantity"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="Ej. 2"
                    :error="form.errors.damaged_quantity"
                />

                <InputField
                    v-model="form.expired_quantity"
                    label="Cantidad caducada"
                    field="expired_quantity"
                    type="number"
                    min="0"
                    step="0.01"
                    placeholder="Ej. 1"
                    :error="form.errors.expired_quantity"
                />
            </div>

            <div
                v-if="invalidQuantities"
                class="rounded-xl border border-primary bg-secondary px-4 py-3 text-sm text-primary"
            >
                La suma de productos dañados y caducados no puede superar la cantidad contada.
            </div>

            <TextareaField
                v-model="form.notes"
                label="Observaciones"
                field="notes"
                placeholder="Agrega información relevante del conteo."
                :rows="3"
                :error="form.errors.notes"
            />

            <p v-if="form.errors.status" class="text-sm text-primary">
                {{ form.errors.status }}
            </p>

            <div class="flex justify-end">
                <AppButton
                    type="submit"
                    :disabled="form.processing || !product || !form.product_batch_id || invalidQuantities"
                >
                    <span class="material-symbols-outlined mr-2 text-[18px]">save</span>
                    Guardar conteo
                </AppButton>
            </div>
        </form>

        <AuditBatchModal
            v-if="showCreateBatch && product"
            :physical-count-id="physicalCountId"
            :product="product"
            @created="handleBatchCreated"
            @close="showCreateBatch = false"
        />
    </FormPanel>
</template>
