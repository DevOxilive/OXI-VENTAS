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
    totalManualBatchQuantity: {
        type: Number,
        default: 0,
    },
})

defineEmits(['add-manual-batch', 'remove-manual-batch'])
</script>

<template>
    <div class="flex-1 min-h-0 overflow-y-auto rounded-3xl border border-slate-200 bg-slate-50 p-5 pr-3">
        <div class="mb-4">
            <h3 class="font-black text-slate-900">
                Selección de lote
            </h3>

            <p class="text-sm text-slate-500">
                Selecciona explícitamente los lotes que serán afectados por este movimiento para mantener la
                trazabilidad del inventario.
            </p>
        </div>

        <div>
            <p class="text-sm font-bold text-slate-700 mb-2">
                Lotes disponibles
            </p>

            <CurrentBatchesList v-if="product.batches?.length" :batches="product.batches"
                :unit="product.unit ?? 'piezas'" :disabled="form.processing" clickable allocation-method="MANUAL"
                @select-batch="$emit('add-manual-batch', $event)" />

            <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center">
                <p class="text-sm font-bold text-slate-600">
                    Sin lotes disponibles
                </p>
            </div>
        </div>

        <div class="space-y-4 mt-5">
            <div v-if="form.manual_batches?.length" class="space-y-3">
                <p class="text-sm font-bold text-slate-700">
                    Lotes seleccionados
                </p>

                <div v-for="(batch, index) in form.manual_batches" :key="batch.product_batch_id || index"
                    class="bg-white border border-slate-200 rounded-2xl p-4">
                    <div class="flex items-center justify-between gap-3 mb-3">
                        <div>
                            <p class="font-black text-slate-800">
                                {{ batch.lot_number || 'Sin lote' }}
                            </p>

                            <p class="text-xs text-slate-500">
                                Disponible: {{ batch.available_quantity }} {{ product.unit ?? 'piezas' }} </p>
                        </div>

                        <button type="button" :disabled="form.processing"
                            class="text-sm font-bold text-red-500 hover:text-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            @click="$emit('remove-manual-batch', index)">
                            Quitar
                        </button>
                    </div>

                    <InputField v-model="batch.quantity" :label="`Cantidad a descontar (${product.unit ?? 'piezas'})`"
                        type="number" field="batch_quantity" :readonly="form.processing"
                        :error="frontendErrors[`manual_batch_${index}`]" />
                </div>

                <div class="flex items-center justify-between bg-white border rounded-2xl px-4 py-3" :class="Number(totalManualBatchQuantity) === Number(form.quantity)
                    ? 'border-emerald-200'
                    : 'border-red-200'">
                    <span class="text-sm font-bold text-slate-600">
                        Total seleccionado
                    </span>

                    <span class="text-sm font-black" :class="Number(totalManualBatchQuantity) === Number(form.quantity)
                        ? 'text-emerald-600'
                        : 'text-red-600'">
                        {{ totalManualBatchQuantity }} / {{ form.quantity || 0 }} {{ product.unit ?? 'piezas' }} </span>
                </div>
            </div>

            <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white p-5 text-center">
                <p class="text-sm font-bold text-slate-600">
                    Selecciona uno o más lotes
                </p>

                <p class="text-xs text-slate-400 mt-1">
                    Haz click sobre un lote disponible para agregarlo.
                </p>
            </div>

            <div v-if="frontendErrors.manual_batches" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3">
                <p class="text-sm font-semibold text-red-700">
                    {{ frontendErrors.manual_batches }}
                </p>
            </div>
        </div>
    </div>
</template>