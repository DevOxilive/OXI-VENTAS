<script setup>
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'

defineProps({
    form: {
        type: Object,
        required: true,
    },
    frontendErrors: {
        type: Object,
        default: () => ({}),
    },
    typeOptions: {
        type: Array,
        default: () => [],
    },
    reasonOptions: {
        type: Array,
        default: () => [],
    },
})

defineEmits(['validate'])
</script>

<template>
    <section class="xl:col-span-3 flex flex-col min-h-0">
        <div class="border-b pb-3 mb-4">
            <h3 class="font-bold text-base text-slate-900">
                Movimiento de inventario
            </h3>

            <p class="text-xs text-slate-500 mt-1">
                Define el tipo de movimiento y la cantidad a ajustar.
            </p>
        </div>

        <form id="adjustStockForm" class="space-y-4" @submit.prevent>
            <div class="grid grid-cols-1 gap-4 rounded-3xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
                <SelectField v-model="form.type" label="Tipo de movimiento" field="type" :options="typeOptions"
                    :disabled="form.processing" :error="frontendErrors.type || form.errors.type"
                    @validate="$emit('validate', 'type')" />

                <SelectField v-model="form.reason" label="Motivo" field="reason" :options="reasonOptions"
                    :disabled="form.processing" :error="frontendErrors.reason || form.errors.reason"
                    @validate="$emit('validate', 'reason')" />

                <InputField v-model="form.quantity" label="Cantidad" field="quantity" type="number"
                    :disabled="form.processing" :error="frontendErrors.quantity || form.errors.quantity"
                    @validate="$emit('validate', 'quantity')" />

                <TextareaField v-model="form.notes" label="Notas" field="notes" :rows="3" :max-height="120"
                    :disabled="form.processing" :error="frontendErrors.notes || form.errors.notes"
                    @validate="$emit('validate', 'notes')" />
            </div>

            <div v-if="frontendErrors.stock || form.errors.stock"
                class="bg-red-50 text-red-700 border border-red-200 rounded-2xl px-4 py-3 text-sm">
                {{ frontendErrors.stock || form.errors.stock }}
            </div>
        </form>
    </section>
</template>