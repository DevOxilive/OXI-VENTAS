<script setup>
import InputField from '@/Components/Forms/InputField.vue'
import CurrentBatchesList from './CurrentBatchesList.vue'

defineProps({
    form: Object,
    product: Object,
    frontendErrors: {
        type: Object,
        default: () => ({}),
    },
    totalBatchQuantity: {
        type: Number,
        default: 0,
    },
})

defineEmits(['remove-batch', 'edit-batch'])
</script>

<template>
    <div class="flex-1 min-h-0 overflow-y-auto rounded-3xl border border-slate-200 bg-slate-50 p-5 pr-3">
        <div v-if="product.batches?.length" class="mb-5">
            <p class="text-sm font-bold text-slate-700 mb-2">
                Lotes actuales
            </p>

            <CurrentBatchesList :batches="product.batches" :unit="product.unit ?? 'piezas'" :disabled="form.processing"
                show-edit @edit-batch="$emit('edit-batch', $event)" />
        </div>

        <div v-else-if="!form.batches?.length"
            class="mb-5 rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center">
            <p class="text-sm font-bold text-slate-600">
                Sin lotes registrados
            </p>

            <p class="text-xs text-slate-400 mt-1">
                Puedes agregar un lote nuevo desde el botón superior.
            </p>
        </div>

        <div v-if="form.batches?.length" class="space-y-4">
            <div v-for="(batch, index) in form.batches" :key="index"
                class="bg-white border border-slate-200 rounded-2xl p-4">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <p class="font-bold text-slate-800">
                        Nuevo lote #{{ index + 1 }}
                    </p>

                    <button type="button" :disabled="form.processing"
                        class="text-sm font-bold text-red-500 hover:text-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                        @click="$emit('remove-batch', index)">
                        Quitar
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2 gap-3">
                    <InputField v-model="batch.lot_number" label="Número de lote" placeholder="Ej. LALA-001"
                        field="lot_number" :readonly="form.processing" />

                    <InputField v-model="batch.expiration_date" label="Fecha de caducidad" type="date"
                        field="expiration_date" :readonly="form.processing" />

                    <InputField v-model="batch.quantity" :label="`Cantidad (${product.unit ?? 'piezas'})`" type="number"
                        field="batch_quantity" :readonly="form.processing" />

                    <InputField v-model="batch.supplier" label="Proveedor" placeholder="Ej. Lala" field="supplier"
                        :readonly="form.processing" />
                </div>

                <div v-if="frontendErrors[`batch_${index}`]"
                    class="mt-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3">
                    <p class="text-sm font-semibold text-red-700">
                        {{ frontendErrors[`batch_${index}`] }}
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-between bg-white border rounded-2xl px-4 py-3" :class="Number(totalBatchQuantity) && Number(totalBatchQuantity) === Number(form.quantity)
                ? 'border-emerald-200'
                : 'border-red-200'">
                <span class="text-sm font-bold text-slate-600">
                    Total registrado en lotes
                </span>

                <span class="text-sm font-black" :class="Number(totalBatchQuantity) && Number(totalBatchQuantity) === Number(form.quantity)
                    ? 'text-emerald-600'
                    : 'text-red-600'">
                    {{ totalBatchQuantity }} / {{ form.quantity || 0 }} {{ product.unit ?? 'piezas' }} </span>
            </div>

            <div v-if="frontendErrors.batches" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3">
                <p class="text-sm font-semibold text-red-700">
                    {{ frontendErrors.batches }}
                </p>
            </div>
        </div>
    </div>
</template>