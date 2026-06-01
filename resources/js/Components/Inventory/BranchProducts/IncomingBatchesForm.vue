<script setup>
import InputField from '@/Components/Forms/InputField.vue'
import CurrentBatchesList from './CurrentBatchesList.vue'

defineProps({
    form: {
        type: Object,
        required: true,
    },
    product: {
        type: Object,
        required: true,
    },
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

            <CurrentBatchesList
                :batches="product.batches"
                show-edit
                @edit-batch="$emit('edit-batch', $event)"
            />
        </div>

        <div v-else-if="!form.batches?.length" class="mb-5 rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center">
            <p class="text-sm font-bold text-slate-600">
                Sin lotes registrados
            </p>

            <p class="text-xs text-slate-400 mt-1">
                Puedes agregar un lote nuevo desde el botón superior.
            </p>
        </div>

        <div v-if="form.batches?.length" class="space-y-4">
            <div v-for="(batch, index) in form.batches" :key="index" class="bg-white border border-slate-200 rounded-2xl p-4">
                <div class="flex items-center justify-between mb-4">
                    <p class="font-bold text-slate-800">
                        Nuevo lote #{{ index + 1 }}
                    </p>

                    <button type="button" class="text-sm font-bold text-red-500 hover:text-red-700"
                        @click="$emit('remove-batch', index)">
                        Quitar
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2 gap-3">
                    <InputField v-model="batch.lot_number" label="Número de lote" placeholder="Ej. LALA-001" />
                    <InputField v-model="batch.expiration_date" label="Fecha de caducidad" type="date" />
                    <InputField v-model="batch.quantity" label="Cantidad" type="number" />
                    <InputField v-model="batch.supplier" label="Proveedor" placeholder="Ej. Lala" />
                </div>

                <p v-if="frontendErrors[`batch_${index}`]" class="text-sm text-red-600 font-semibold mt-3">
                    {{ frontendErrors[`batch_${index}`] }}
                </p>
            </div>

            <div class="flex items-center justify-between bg-white border border-slate-200 rounded-2xl px-4 py-3">
                <span class="text-sm font-bold text-slate-600">
                    Total registrado en lotes
                </span>

                <span class="text-sm font-black" :class="Number(totalBatchQuantity) === Number(form.quantity)
                    ? 'text-emerald-600'
                    : 'text-red-600'">
                    {{ totalBatchQuantity }} / {{ form.quantity || 0 }}
                </span>
            </div>

            <p v-if="frontendErrors.batches" class="text-sm text-red-600 font-semibold">
                {{ frontendErrors.batches }}
            </p>
        </div>
    </div>
</template>
