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
        <h3 class="font-bold text-base border-b pb-3 mb-4">
            Movimiento de inventario
        </h3>

        <form id="adjustStockForm" class="flex-1 min-h-0 overflow-y-auto space-y-6 pr-2" @submit.prevent>
            <div class="grid grid-cols-1 gap-4 rounded-3xl border border-slate-200 bg-slate-50 p-5">
                <SelectField
                    label="Tipo de movimiento"
                    field="type"
                    v-model="form.type"
                    :options="typeOptions"
                    :error="frontendErrors.type || form.errors.type"
                    @validate="$emit('validate', 'type')"
                />

                <SelectField
                    label="Motivo"
                    field="reason"
                    v-model="form.reason"
                    :options="reasonOptions"
                    :error="frontendErrors.reason || form.errors.reason"
                    @validate="$emit('validate', 'reason')"
                />

                <InputField
                    label="Cantidad"
                    field="quantity"
                    type="number"
                    v-model="form.quantity"
                    :error="frontendErrors.quantity || form.errors.quantity"
                    @validate="$emit('validate', 'quantity')"
                />

                <TextareaField
                    label="Notas"
                    field="notes"
                    v-model="form.notes"
                    :rows="3"
                    :max-height="120"
                    :error="frontendErrors.notes || form.errors.notes"
                    @validate="$emit('validate', 'notes')"
                />
            </div>

            <div v-if="frontendErrors.stock || form.errors.stock"
                class="bg-red-50 text-red-700 border border-red-200 rounded-2xl px-4 py-3 text-sm">
                {{ frontendErrors.stock || form.errors.stock }}
            </div>
        </form>
    </section>
</template>
