<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

import AuditBatchModal from '@/Components/Audits/PhysicalCounts/AuditBatchModal.vue'
import FormPanel from '@/Components/Cards/FormPanel.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import InputField from '@/Components/Forms/InputField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import AuditBatchSelect from '@/Components/Audits/PhysicalCounts/AuditBatchSelect.vue'
import PresentationSwitch from '@/Components/Inventory/PresentationSwitch.vue'
import { ErrorAlert, ToastAlert } from '@/Components/Modales/UniversalActionModal'
import { confirmModalAction } from '@/Components/Modales/useModalConfig'
import { formatInventoryQuantity } from '@/utils/quantityFormatter'

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
    count_presentation: 'piece',
    expiration_date: '',
    notes: '',
})

const showCreateBatch = ref(false)
const pendingLotNumber = ref('')

const countedQuantity = computed(() => Number(form.counted_quantity || 0))
const damagedQuantity = computed(() => Number(form.damaged_quantity || 0))
const expiredQuantity = computed(() => Number(form.expired_quantity || 0))
const invalidQuantities = computed(() =>
    damagedQuantity.value + expiredQuantity.value > countedQuantity.value,
)
const isKilogram = computed(() => props.product?.inventory_unit === 'kg')
const hasBoxPresentation = computed(() => Boolean(props.product?.has_box_presentation) && !isKilogram.value)
const piecesPerBox = computed(() => {
    const pieces = Number(props.product?.pieces_per_box || 0)

    return Number.isInteger(pieces) && pieces > 1 ? pieces : 1
})
const countPresentation = computed(() =>
    hasBoxPresentation.value && form.count_presentation === 'box' ? 'box' : 'piece'
)
const visualUnitLabel = computed(() => {
    if (countPresentation.value === 'box') return 'cajas'
    if (isKilogram.value) return 'kg'

    return 'piezas'
})
const baseUnitLabel = computed(() => isKilogram.value ? 'kg' : 'piezas')
const countedBaseQuantity = computed(() => visualToBaseQuantity(form.counted_quantity))
const damagedBaseQuantity = computed(() => visualToBaseQuantity(form.damaged_quantity))
const expiredBaseQuantity = computed(() => visualToBaseQuantity(form.expired_quantity))

watch(
    () => props.product,
    (product) => {
        if (!product) return

        const productChanged =
            Number(form.branch_product_id) !== Number(product.branch_product_id)

        form.branch_product_id = product.branch_product_id
        form.product_id = product.product_id
        form.scanned_code = product.scanned_code
        form.count_presentation = hasBoxPresentation.value ? form.count_presentation : 'piece'

        const pendingBatch = pendingLotNumber.value
            ? product.batches?.find((batch) => batch.lot_number === pendingLotNumber.value)
            : null

        if (pendingBatch) {
            form.product_batch_id = pendingBatch.id
            pendingLotNumber.value = ''
        } else if (productChanged) {
            form.product_batch_id = ''
        }
    },
    { immediate: true },
)

function handleBatchCreated(lotNumber) {
    pendingLotNumber.value = lotNumber
}

function visualToBaseQuantity(value) {
    const quantity = Number(String(value ?? '').replace(',', '.')) || 0

    return countPresentation.value === 'box'
        ? Math.round(quantity * piecesPerBox.value * 1000) / 1000
        : quantity
}

function formatQuantity(value) {
    return formatInventoryQuantity(value, isKilogram.value ? 'kg' : 'pza')
}

function formatBaseQuantity(value) {
    return formatInventoryQuantity(value, 'pza')
}

function setCountPresentation(value) {
    form.count_presentation = value
}

async function handleBatchSelection(batchId) {
    const batch = (props.product?.batches || [])
        .find((item) => String(item.id) === String(batchId))

    if (!batch?.is_counted) {
        form.product_batch_id = batchId
        return
    }

    const countedBy = (batch.counted_by || [])
        .map((user) => user.name)
        .filter(Boolean)
        .join(', ')

    const result = await confirmModalAction({
        mode: 'update',
        title: 'Lote ya contado',
        message: `El lote ${batch.lot_number || 'seleccionado'} ya fue contado${
            countedBy ? ` por ${countedBy}` : ''
        }. ¿Deseas agregar más producto sobre este mismo lote?`,
        confirmText: 'Sí, agregar más',
        cancelText: 'Elegir otro lote',
        confirmButtonColor: '#16a34a',
    })

    if (result.isConfirmed) {
        form.product_batch_id = batchId
    }
}

function submit() {
    if (!props.product || invalidQuantities.value) return

    form
        .transform((data) => ({
            ...data,
            counted_quantity: visualToBaseQuantity(data.counted_quantity),
            damaged_quantity: visualToBaseQuantity(data.damaged_quantity),
            expired_quantity: visualToBaseQuantity(data.expired_quantity),
            count_presentation: countPresentation.value,
            pieces_per_box: countPresentation.value === 'box' ? piecesPerBox.value : null,
        }))
        .post(route('audits.physical-counts.entries.store', props.physicalCountId), {
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
            <div class="flex flex-wrap items-center justify-end gap-3">
                <div
                    v-if="hasBoxPresentation"
                    class="flex items-center gap-2"
                >
                    <span class="text-xs font-semibold text-text opacity-60">Contar por</span>
                    <PresentationSwitch
                        :model-value="countPresentation"
                        @change="setCountPresentation"
                    />
                </div>

                <AppButton variant="secondary" @click="showCreateBatch = true">
                    <span class="material-symbols-outlined mr-2 text-[18px]">add_box</span>
                    Crear lote
                </AppButton>
            </div>
        </template>

        <form class="space-y-4" @submit.prevent="submit">
            <AuditBatchSelect
                :model-value="form.product_batch_id"
                :batches="product?.batches || []"
                :can-view-stock="canViewStock"
                :error="form.errors.product_batch_id"
                @update:model-value="handleBatchSelection"
            />

            <div
                class="grid grid-cols-1 gap-3 rounded-xl border border-secondary bg-secondary px-4 py-3 text-sm text-text md:grid-cols-3"
            >
                <div>
                    <p class="text-xs font-medium opacity-60">Conteo visual</p>
                    <p class="mt-1 font-semibold">{{ visualUnitLabel }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium opacity-60">Stock actual</p>
                    <p class="mt-1 font-semibold">{{ formatQuantity(product?.stock ?? 0) }} {{ baseUnitLabel }}</p>
                </div>
                <div v-if="countPresentation === 'box'">
                    <p class="text-xs font-medium opacity-60">Equivalencia</p>
                    <p class="mt-1 font-semibold">1 caja = {{ piecesPerBox }} piezas</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <InputField
                    v-model="form.counted_quantity"
                    :label="`Cantidad contada (${visualUnitLabel})`"
                    field="counted_quantity"
                    type="text"
                    :inputmode="isKilogram ? 'decimal' : 'numeric'"
                    :validation-field="isKilogram ? 'kilogram_quantity' : 'quantity'"
                    placeholder="Ej. 10"
                    :error="form.errors.counted_quantity"
                />

                <InputField
                    v-model="form.damaged_quantity"
                    :label="`Cantidad dañada (${visualUnitLabel})`"
                    field="damaged_quantity"
                    type="text"
                    :inputmode="isKilogram ? 'decimal' : 'numeric'"
                    :validation-field="isKilogram ? 'kilogram_quantity' : 'quantity'"
                    placeholder="Ej. 2"
                    :error="form.errors.damaged_quantity"
                />

                <InputField
                    v-model="form.expired_quantity"
                    :label="`Cantidad caducada (${visualUnitLabel})`"
                    field="expired_quantity"
                    type="text"
                    :inputmode="isKilogram ? 'decimal' : 'numeric'"
                    :validation-field="isKilogram ? 'kilogram_quantity' : 'quantity'"
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

            <p
                v-if="countPresentation === 'box'"
                class="text-xs font-medium text-text opacity-70"
            >
                Se guardará en inventario como {{ formatBaseQuantity(countedBaseQuantity) }} piezas contadas,
                {{ formatBaseQuantity(damagedBaseQuantity) }} dañadas y {{ formatBaseQuantity(expiredBaseQuantity) }} caducadas.
            </p>

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
