<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AuditBatchModal from '@/Components/Audits/PhysicalCounts/AuditBatchModal.vue'

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
    { immediate: true }
)

const countedQuantity = computed(() => Number(form.counted_quantity || 0))
const damagedQuantity = computed(() => Number(form.damaged_quantity || 0))
const expiredQuantity = computed(() => Number(form.expired_quantity || 0))

const invalidQuantities = computed(() => {
    return damagedQuantity.value + expiredQuantity.value > countedQuantity.value
})

function batchOptionLabel(batch) {
    const parts = [
        `Lote: ${batch.lot_number ?? 'Sin lote'}`,
        `Caduca: ${batch.expiration_date ?? 'Sin fecha'}`,
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
            form.counted_quantity = ''
            form.damaged_quantity = ''
            form.expired_quantity = ''
            form.expiration_date = ''
            form.notes = ''
        },
    })
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900">
            Captura del producto escaneado
        </h2>

        <p class="mt-1 text-sm text-gray-500">
            Registra las cantidades fisicas encontradas para el producto seleccionado.
        </p>

        <div v-if="product" class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <label class="block text-sm font-medium text-gray-700">
                    Lote / caducidad
                </label>

                <button
                    type="button"
                    class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                    @click="showCreateBatch = true"
                >
                    Crear lote
                </button>
            </div>

            <select v-model="form.product_batch_id" class="mt-3 w-full rounded-lg border-gray-300 text-sm">
                <option value="">
                    Selecciona un lote
                </option>

                <option v-for="batch in product.batches || []" :key="batch.id" :value="batch.id">
                    {{ batchOptionLabel(batch) }}
                </option>
            </select>

            <p v-if="product && !form.product_batch_id" class="mt-2 text-sm text-amber-600">
                Selecciona el lote antes de guardar el conteo.
            </p>
        </div>

        <form class="mt-4" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Cantidad contada
                    </label>

                    <input
                        v-model="form.counted_quantity"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="Ej. 10"
                        class="w-full rounded-lg border-gray-300 text-sm"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Cantidad danada
                    </label>

                    <input
                        v-model="form.damaged_quantity"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="Ej. 2"
                        class="w-full rounded-lg border-gray-300 text-sm"
                    >
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Cantidad caducada
                    </label>

                    <input
                        v-model="form.expired_quantity"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="Ej. 1"
                        class="w-full rounded-lg border-gray-300 text-sm"
                    >
                </div>
            </div>

            <p
                v-if="invalidQuantities"
                class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700"
            >
                La suma de danados y caducados no puede ser mayor a la cantidad contada.
            </p>

            <textarea
                v-model="form.notes"
                placeholder="Observaciones del conteo"
                class="mt-4 w-full rounded-lg border-gray-300 text-sm"
            />

            <p v-if="form.errors.product_batch_id" class="mt-2 text-sm text-red-600">
                {{ form.errors.product_batch_id }}
            </p>

            <p v-if="form.errors.counted_quantity" class="mt-2 text-sm text-red-600">
                {{ form.errors.counted_quantity }}
            </p>

            <p v-if="form.errors.damaged_quantity" class="mt-2 text-sm text-red-600">
                {{ form.errors.damaged_quantity }}
            </p>

            <p v-if="form.errors.expired_quantity" class="mt-2 text-sm text-red-600">
                {{ form.errors.expired_quantity }}
            </p>

            <p v-if="form.errors.status" class="mt-2 text-sm text-red-600">
                {{ form.errors.status }}
            </p>

            <button
                type="submit"
                class="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                :disabled="form.processing || !product || !form.product_batch_id || invalidQuantities"
            >
                Guardar conteo
            </button>
        </form>

        <AuditBatchModal
            v-if="showCreateBatch && product"
            :physical-count-id="physicalCountId"
            :product="product"
            @created="handleBatchCreated"
            @close="showCreateBatch = false"
        />
    </div>
</template>
