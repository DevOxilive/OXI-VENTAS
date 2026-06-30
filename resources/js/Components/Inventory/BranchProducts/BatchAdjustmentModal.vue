<script setup>
import { computed } from 'vue'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import { getBatchAdjustmentModalConfig } from '@/config/ModalConfigs/batchAdjustmentModalConfig'

const emit = defineEmits(['close', 'save'])

const props = defineProps({
    form: {
        type: Object,
        required: true,
    },
    frontendErrors: {
        type: Object,
        required: true,
    },
    totalErrors: {
        type: Number,
        required: true,
    },
    processing: {
        type: Boolean,
        default: false,
    },
    usesLot: {
        type: Boolean,
        default: false,
    },
    isSeasonal: {
        type: Boolean,
        default: false,
    },
    calculatedQuantity: {
        type: Number,
        required: true,
    },
    adjustmentText: {
        type: String,
        required: true,
    },
    quantityResultColor: {
        type: String,
        required: true,
    },
    toggleLot: {
        type: Function,
        required: true,
    },
    setAdjustmentType: {
        type: Function,
        required: true,
    },
    validateField: {
        type: Function,
        required: true,
    },
})

const modalConfig = computed(() => getBatchAdjustmentModalConfig({
    totalErrors: props.totalErrors,
    processing: props.processing,
}))

const batchStatusOptions = [
    { label: 'Activo', value: 'ACTIVE' },
    { label: 'Inactivo', value: 'INACTIVE' },
    { label: 'Temporada', value: 'SEASONAL' },
]

function closeModal() {
    if (props.processing) return

    emit('close')
}
</script>

<template>
    <GlobalModal
        v-bind="modalConfig"
        @save="$emit('save')"
        @close="closeModal"
    >
        <section class="min-h-0 w-full">
            <div class="border-b border-slate-200 px-5 py-4">
                <h3 class="font-black text-slate-900">
                    {{ usesLot ? form.lot_number || 'Lote sin numero' : 'Producto sin lote' }}
                </h3>

                <p class="mt-1 text-sm text-slate-500">
                    Actualiza los datos del lote, su configuracion y el ajuste de cantidad.
                </p>
            </div>

            <div class="p-5">
                <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                    <section class="space-y-4">
                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm font-black text-slate-800">
                                Informacion del lote
                            </p>

                            <p class="mt-1 text-xs text-slate-500">
                                Datos generales del lote o producto sin lote.
                            </p>

                            <div class="mt-4 space-y-4">
                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-sm font-bold text-slate-800">
                                                Tiene lote?
                                            </p>

                                            <p class="mt-0.5 text-xs text-slate-500">
                                                Activalo si este registro debe manejar numero de lote.
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            :disabled="processing"
                                            class="relative inline-flex h-8 w-16 shrink-0 items-center rounded-full transition disabled:opacity-50"
                                            :class="usesLot ? 'bg-[#1f1d2b]' : 'bg-slate-300'"
                                            @click="toggleLot"
                                        >
                                            <span
                                                class="inline-block h-6 w-6 transform rounded-full bg-white shadow transition"
                                                :class="usesLot ? 'translate-x-9' : 'translate-x-1'"
                                            />

                                            <span
                                                class="absolute text-[10px] font-black uppercase"
                                                :class="usesLot ? 'left-3 text-white' : 'right-3 text-slate-600'"
                                            >
                                                {{ usesLot ? 'Si' : 'No' }}
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <InputField
                                    v-if="usesLot"
                                    v-model="form.lot_number"
                                    label="Numero de lote"
                                    placeholder="Ej. LALA-001"
                                    field="lot_number"
                                    :readonly="processing"
                                    :error="frontendErrors.lot_number"
                                    @validate="validateField"
                                />

                                <InputField
                                    v-model="form.supplier"
                                    label="Proveedor"
                                    placeholder="Opcional"
                                    field="supplier"
                                    :readonly="processing"
                                    :error="frontendErrors.supplier"
                                    @validate="validateField"
                                />

                                <InputField
                                    v-model="form.received_at"
                                    label="Fecha de ingreso"
                                    field="received_at"
                                    type="date"
                                    :readonly="processing"
                                    :error="frontendErrors.received_at"
                                    @validate="validateField"
                                />

                                <InputField
                                    v-model="form.expiration_date"
                                    label="Fecha de caducidad"
                                    field="expiration_date"
                                    type="date"
                                    :readonly="processing"
                                    :error="frontendErrors.expiration_date"
                                    @validate="validateField"
                                />

                                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                    <p class="mb-3 text-xs text-slate-500">
                                        Esta nota quedara registrada en el historial de movimientos.
                                    </p>

                                    <TextareaField
                                        v-model="form.notes"
                                        label="Nota del ajuste"
                                        field="notes"
                                        placeholder="Ej. Correccion por error de captura, revision fisica del lote, ajuste solicitado por contabilidad..."
                                        :rows="4"
                                        :readonly="processing"
                                        :error="frontendErrors.notes"
                                        @validate="validateField"
                                    />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm font-black text-slate-800">
                                Configuracion del lote
                            </p>

                            <p class="mt-1 text-xs text-slate-500">
                                Define si este lote esta activo, inactivo o si pertenece a una temporada.
                            </p>

                            <div class="mt-4 space-y-4">
                                <div>
                                    <SelectField
                                        v-model="form.status"
                                        label="Estado del lote"
                                        field="status"
                                        :disabled="processing"
                                        :options="batchStatusOptions"
                                        :error="frontendErrors.status"
                                        @validate="validateField"
                                    />
                                </div>

                                <div
                                    v-if="isSeasonal"
                                    class="space-y-4 rounded-2xl border border-amber-200 bg-amber-50 p-4"
                                >
                                    <div>
                                        <p class="text-sm font-black text-amber-900">
                                            Temporada del lote
                                        </p>

                                        <p class="mt-1 text-xs text-amber-700">
                                            Estas fechas solo aplican cuando el estado del lote es temporada.
                                        </p>
                                    </div>

                                    <InputField
                                        v-model="form.season_start_date"
                                        label="Inicio de temporada"
                                        field="season_start_date"
                                        type="date"
                                        :readonly="processing"
                                        :error="frontendErrors.season_start_date"
                                        @validate="validateField"
                                    />

                                    <InputField
                                        v-model="form.season_end_date"
                                        label="Fin de temporada"
                                        field="season_end_date"
                                        type="date"
                                        :readonly="processing"
                                        :error="frontendErrors.season_end_date"
                                        @validate="validateField"
                                    />
                                </div>

                                <div
                                    v-else
                                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3"
                                >
                                    <p class="text-xs font-bold text-slate-500">
                                        Temporada
                                    </p>

                                    <p class="mt-1 text-sm font-semibold text-slate-700">
                                        Selecciona Temporada para habilitar las fechas.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-sm font-black text-slate-800">
                                Ajuste de cantidad
                            </p>

                            <p class="mt-1 text-xs text-slate-500">
                                Selecciona si vas a agregar o eliminar unidades. La cantidad siempre se captura en positivo.
                            </p>

                            <div class="mt-4 space-y-4">
                                <div class="grid grid-cols-2 gap-2 rounded-2xl bg-slate-200 p-1">
                                    <button
                                        type="button"
                                        :disabled="processing"
                                        class="rounded-xl px-4 py-2 text-sm font-black transition disabled:opacity-50"
                                        :class="form.adjustment_type === 'ADD'
                                            ? 'bg-white text-emerald-700 shadow'
                                            : 'text-slate-500'"
                                        @click="setAdjustmentType('ADD')"
                                    >
                                        Agregar
                                    </button>

                                    <button
                                        type="button"
                                        :disabled="processing"
                                        class="rounded-xl px-4 py-2 text-sm font-black transition disabled:opacity-50"
                                        :class="form.adjustment_type === 'REMOVE'
                                            ? 'bg-white text-red-700 shadow'
                                            : 'text-slate-500'"
                                        @click="setAdjustmentType('REMOVE')"
                                    >
                                        Eliminar
                                    </button>
                                </div>

                                <InputField
                                    v-model="form.adjustment_amount"
                                    label="Cantidad"
                                    placeholder="Ej. 2"
                                    field="adjustment_amount"
                                    type="number"
                                    :readonly="processing"
                                    :error="frontendErrors.adjustment_amount"
                                    @validate="validateField('adjustment_amount')"
                                />

                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-1">
                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                        <p class="text-xs text-slate-500">
                                            Cantidad actual
                                        </p>

                                        <p class="text-3xl font-black text-slate-900">
                                            {{ form.original_quantity }}
                                        </p>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                        <p class="text-xs text-slate-500">
                                            Total despues del ajuste
                                        </p>

                                        <p
                                            class="text-3xl font-black"
                                            :class="quantityResultColor"
                                        >
                                            {{ calculatedQuantity }}
                                        </p>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <p
                                        class="text-sm font-semibold"
                                        :class="form.adjustment_type === 'REMOVE' && Number(form.adjustment_amount || 0) > 0
                                            ? 'text-red-600'
                                            : form.adjustment_type === 'ADD' && Number(form.adjustment_amount || 0) > 0
                                                ? 'text-emerald-600'
                                                : 'text-slate-500'"
                                    >
                                        {{ adjustmentText }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </section>
    </GlobalModal>
</template>
