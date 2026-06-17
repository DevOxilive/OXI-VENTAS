<script setup>
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    physicalCountId: {
        type: Number,
        required: true
    },
    product: {
        type: Object,
        default: null
    }
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
    notes: ''
})

watch(
    () => props.product,
    (product) => {
        if (!product) return

        form.branch_product_id = product.branch_product_id
        form.product_id = product.product_id
        form.scanned_code = product.scanned_code
        form.product_batch_id = ''
    },
    { immediate: true }
)

const countedQuantity = computed(() => Number(form.counted_quantity || 0))
const damagedQuantity = computed(() => Number(form.damaged_quantity || 0))
const expiredQuantity = computed(() => Number(form.expired_quantity || 0))

const invalidQuantities = computed(() => {
    return damagedQuantity.value + expiredQuantity.value > countedQuantity.value
})

const submit = () => {
    if (!props.product || invalidQuantities.value) return

    form.post(route('audits.physical-counts.entries.store', props.physicalCountId), {
        preserveScroll: true,
        onSuccess: () => {
            form.counted_quantity = ''
            form.damaged_quantity = ''
            form.expired_quantity = ''
            form.expiration_date = ''
            form.notes = ''
        }
    })
}
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900">
            Captura del producto escaneado
        </h2>

        <p class="mt-1 text-sm text-gray-500">
            Registra las cantidades físicas encontradas para el producto seleccionado.
        </p>

        <div v-if="product?.batches?.length" class="mt-4">
            <label class="mb-1 block text-sm font-medium text-gray-700">
                Lote / caducidad
            </label>

            <select
                v-model="form.product_batch_id"
                class="w-full rounded-lg border-gray-300 text-sm"
            >
                <option value="">
                    Selecciona un lote
                </option>

                <option
                    v-for="batch in product.batches"
                    :key="batch.id"
                    :value="batch.id"
                >
                    Lote: {{ batch.lot_number ?? 'Sin lote' }}
                    | Existencia: {{ batch.quantity }}
                    | Caduca: {{ batch.expiration_date ?? 'Sin fecha' }}
                </option>
            </select>
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
                        Cantidad dañada
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
                La suma de dañados y caducados no puede ser mayor a la cantidad contada.
            </p>

            <input
                v-model="form.expiration_date"
                type="date"
                class="mt-4 w-full rounded-lg border-gray-300 text-sm"
            >

            <textarea
                v-model="form.notes"
                placeholder="Observaciones del conteo"
                class="mt-4 w-full rounded-lg border-gray-300 text-sm"
            />

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
                :disabled="form.processing || !product || invalidQuantities"
            >
                Guardar conteo
            </button>
        </form>
    </div>
</template>