<script setup>
import { useForm } from '@inertiajs/vue3'
import { watch } from 'vue'

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

const submit = () => {
    form.post(route('audits.physical-counts.entries.store', props.physicalCountId), {
        preserveScroll: true,
    onSuccess: () => {
    form.reset(
        'branch_product_id',
        'product_batch_id',
        'product_id',
        'scanned_code',
        'counted_quantity',
        'damaged_quantity',
        'expired_quantity',
        'expiration_date',
        'notes'
    )
}
    })
}
</script>

<template>
    
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div v-if="product?.batches?.length" class="mb-4">
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
        <h2 class="text-lg font-semibold text-gray-900">
            Captura de conteo
        </h2>

        <form class="mt-4" @submit.prevent="submit">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <input
                    v-model="form.counted_quantity"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="Cantidad contada"
                    class="rounded-lg border-gray-300 text-sm"
                >

                <input
                    v-model="form.damaged_quantity"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="Cantidad dañada"
                    class="rounded-lg border-gray-300 text-sm"
                >

                <input
                    v-model="form.expired_quantity"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="Cantidad caducada"
                    class="rounded-lg border-gray-300 text-sm"
                >
            </div>

            <input
                v-model="form.expiration_date"
                type="date"
                class="mt-4 w-full rounded-lg border-gray-300 text-sm"
            >

            <textarea
                v-model="form.notes"
                placeholder="Observaciones"
                class="mt-4 w-full rounded-lg border-gray-300 text-sm"
            />

            <p v-if="form.errors.counted_quantity" class="mt-2 text-sm text-red-600">
                {{ form.errors.counted_quantity }}
            </p>

            <button
                type="submit"
                class="mt-4 rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                :disabled="form.processing || !product"
            >
                Guardar conteo
            </button>
        </form>
    </div>
</template>