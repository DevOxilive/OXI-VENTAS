<script setup>
import { computed } from 'vue'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import { getProductBatchesModalConfig } from '@/config/ModalConfigs/productBatchesModalConfig'

const emit = defineEmits(['close', 'select-batch', 'save', 'adjust-batch'])

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    selectedBatch: {
        type: Object,
        default: null,
    },
    form: {
        type: Object,
        default: () => ({
            lot_number: '',
            supplier: '',
            received_at: '',
            expiration_date: '',
            notes: '',
            status: 'ACTIVE',
            season_start_date: '',
            season_end_date: '',
            adjustment_type: 'ADD',
            adjustment_amount: 0,
            original_quantity: 0,
        }),
    },
    frontendErrors: {
        type: Object,
        default: () => ({}),
    },
    totalErrors: {
        type: Number,
        default: 0,
    },
    processing: {
        type: Boolean,
        default: false,
    },
    isSeasonal: {
        type: Boolean,
        default: false,
    },
    calculatedQuantity: {
        type: Number,
        default: 0,
    },
    adjustmentText: {
        type: String,
        default: 'Selecciona un lote para ajustar su cantidad.',
    },
    quantityResultColor: {
        type: String,
        default: 'text-slate-900',
    },
    setAdjustmentType: {
        type: Function,
        default: () => {},
    },
    validateField: {
        type: Function,
        default: () => {},
    },
})

const productName = computed(() => {
    return props.product?.name ?? props.product?.product?.name ?? 'Producto'
})

const unit = computed(() => {
    return props.product?.unit ?? 'pieza'
})

const batches = computed(() => {
    return props.product?.batches ?? []
})

const selectedBatchTitle = computed(() => {
    if (!props.selectedBatch) {
        return 'Selecciona un lote'
    }

    return props.selectedBatch.lot_number || 'Sin lote'
})

const availableQuantity = computed(() => {
    return props.selectedBatch?.quantity ?? 0
})

const modalConfig = computed(() => getProductBatchesModalConfig({
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

function formatDate(date) {
    if (!date) return 'Sin fecha'

    return String(date).slice(0, 10)
}

function batchLabel(batch) {
    return batch.lot_number || 'Sin lote'
}

function statusLabel(status) {
    const labels = {
        ACTIVE: 'Activo',
        INACTIVE: 'Inactivo',
        SEASONAL: 'Temporada',
    }

    return labels[status] || status || 'Sin estado'
}

function statusClass(status) {
    const classes = {
        ACTIVE: 'border-emerald-200 bg-emerald-100 text-emerald-700',
        INACTIVE: 'border-slate-200 bg-slate-100 text-slate-600',
        SEASONAL: 'border-amber-200 bg-amber-100 text-amber-700',
    }

    return classes[status] || 'border-slate-200 bg-slate-100 text-slate-600'
}

function seasonLabel(batch) {
    if (!batch.season_start_date && !batch.season_end_date) {
        return 'Sin temporada'
    }

    return `${formatDate(batch.season_start_date)} - ${formatDate(batch.season_end_date)}`
}

function isSelectedBatch(batch) {
    return props.selectedBatch?.id === batch.id
}

function selectBatch(batch) {
    if (props.processing) return

    emit('select-batch', batch)
}
</script>

<template>
    <GlobalModal
        v-bind="modalConfig"
        @save="$emit('save')"
        @close="closeModal"
    >
        <section class="flex h-full min-h-0 w-full flex-col overflow-hidden">
            <div class="grid h-full min-h-0 grid-cols-1 xl:grid-cols-[320px_minmax(0,1fr)]">
                <aside class="flex min-h-[250px] max-h-[340px] flex-col border-b border-slate-200 bg-slate-50 xl:h-full xl:min-h-0 xl:max-h-full xl:border-b-0 xl:border-r">
                    <div class="border-b border-slate-200 bg-white px-5 py-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate font-black text-slate-900">
                                    {{ productName }}
                                </h3>

                                <p class="mt-1 text-sm text-slate-500">
                                    {{ batches.length }} lote(s) registrados
                                </p>
                            </div>

                            <span
                                v-if="selectedBatch"
                                class="shrink-0 rounded-full border px-3 py-1 text-xs font-black"
                                :class="statusClass(selectedBatch.status)"
                            >
                                {{ statusLabel(selectedBatch.status) }}
                            </span>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto p-3">
                        <div
                            v-if="batches.length"
                            class="space-y-2"
                        >
                            <button
                                v-for="batch in batches"
                                :key="batch.id"
                                type="button"
                                class="w-full rounded-2xl border bg-white px-4 py-3 text-left transition"
                                :class="isSelectedBatch(batch)
                                    ? 'border-blue-500 bg-blue-50 shadow-sm'
                                    : 'border-slate-200 hover:border-slate-300'"
                                @click="selectBatch(batch)"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-900">
                                            {{ batchLabel(batch) }}
                                        </p>

                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ seasonLabel(batch) }}
                                        </p>
                                    </div>

                                    <span
                                        class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-black"
                                        :class="statusClass(batch.status)"
                                    >
                                        {{ statusLabel(batch.status) }}
                                    </span>
                                </div>

                                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                    <span>Stock: <span class="font-semibold text-slate-700">{{ batch.quantity }} {{ unit }}</span></span>
                                    <span>Proveedor: <span class="font-semibold text-slate-700">{{ batch.supplier || 'Sin proveedor' }}</span></span>
                                </div>
                            </button>
                        </div>

                        <div
                            v-else
                            class="rounded-2xl border border-slate-200 bg-white px-4 py-10 text-center"
                        >
                            <p class="text-sm font-semibold text-slate-600">
                                Este producto no tiene lotes registrados.
                            </p>
                        </div>
                    </div>
                </aside>

                <section class="flex min-h-[520px] flex-col bg-white xl:h-full xl:min-h-0">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div class="min-w-0">
                                <h3 class="truncate font-black text-slate-900">
                                    {{ selectedBatchTitle }}
                                </h3>

                                <p class="mt-1 text-sm text-slate-500">
                                    Ajusta la informacion del lote y su cantidad sin navegar entre tarjetas grandes.
                                </p>
                            </div>

                            <span class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-600">
                                Disponible: {{ availableQuantity }} {{ unit }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="selectedBatch"
                        class="grid min-h-0 flex-1 grid-cols-1 xl:grid-cols-[minmax(0,1fr)_280px]"
                    >
                        <div class="min-h-0 overflow-y-auto px-5 py-5">
                            <div class="space-y-5">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                                    <InputField
                                        v-model="form.lot_number"
                                        label="Numero de lote"
                                        placeholder="Ej. AX-3-2026-07-08"
                                        field="lot_number"
                                        :readonly="processing"
                                        :error="frontendErrors.lot_number"
                                        @validate="validateField"
                                    />

                                    <InputField
                                        v-model="form.expiration_date"
                                        label="Caducidad"
                                        field="expiration_date"
                                        type="date"
                                        :readonly="processing"
                                        :error="frontendErrors.expiration_date"
                                        @validate="validateField"
                                    />

                                    <SelectField
                                        v-model="form.status"
                                        label="Estado"
                                        field="status"
                                        :disabled="processing"
                                        :options="batchStatusOptions"
                                        :error="frontendErrors.status"
                                        @validate="validateField"
                                    />
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
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
                                        v-model="form.season_start_date"
                                        label="Inicio de temporada"
                                        field="season_start_date"
                                        type="date"
                                        :readonly="processing || !isSeasonal"
                                        :error="frontendErrors.season_start_date"
                                        @validate="validateField"
                                    />

                                    <InputField
                                        v-model="form.season_end_date"
                                        label="Fin de temporada"
                                        field="season_end_date"
                                        type="date"
                                        :readonly="processing || !isSeasonal"
                                        :error="frontendErrors.season_end_date"
                                        @validate="validateField"
                                    />
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-[280px_minmax(0,1fr)]">
                                    <InputField
                                        v-model="form.supplier"
                                        label="Proveedor"
                                        placeholder="Opcional"
                                        field="supplier"
                                        :readonly="processing"
                                        :error="frontendErrors.supplier"
                                        @validate="validateField"
                                    />

                                    <TextareaField
                                        v-model="form.notes"
                                        label="Notas"
                                        field="notes"
                                        :readonly="processing"
                                        :rows="7"
                                        :max-height="260"
                                        placeholder="Ej. Correccion por captura, conteo fisico o ajuste operativo..."
                                        :error="frontendErrors.notes"
                                        @validate="validateField"
                                    />
                                </div>
                            </div>
                        </div>

                        <aside class="border-t border-slate-200 bg-slate-50 px-5 py-5 xl:border-l xl:border-t-0">
                            <div class="space-y-4">
                                <div>
                                    <p class="text-sm font-black text-slate-900">
                                        Ajuste de cantidad
                                    </p>

                                    <p class="mt-1 text-xs text-slate-500">
                                        Captura siempre la cantidad en positivo.
                                    </p>
                                </div>

                                <div class="grid grid-cols-2 rounded-full bg-slate-200 p-1">
                                    <button
                                        type="button"
                                        class="rounded-full px-4 py-2 text-sm font-black transition"
                                        :class="form.adjustment_type === 'ADD'
                                            ? 'bg-white text-emerald-700 shadow-sm'
                                            : 'text-slate-600'"
                                        :disabled="processing"
                                        @click="setAdjustmentType('ADD')"
                                    >
                                        Agregar
                                    </button>

                                    <button
                                        type="button"
                                        class="rounded-full px-4 py-2 text-sm font-black transition"
                                        :class="form.adjustment_type === 'REMOVE'
                                            ? 'bg-white text-red-600 shadow-sm'
                                            : 'text-slate-600'"
                                        :disabled="processing"
                                        @click="setAdjustmentType('REMOVE')"
                                    >
                                        Eliminar
                                    </button>
                                </div>

                                <InputField
                                    v-model="form.adjustment_amount"
                                    label="Cantidad"
                                    type="number"
                                    field="adjustment_amount"
                                    placeholder="Ej. 2"
                                    :readonly="processing"
                                    :error="frontendErrors.adjustment_amount"
                                    @validate="validateField"
                                />

                                <div class="grid grid-cols-2 gap-3">
                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                        <p class="text-xs font-semibold text-slate-500">
                                            Actual
                                        </p>

                                        <p class="mt-1 text-2xl font-black text-slate-900">
                                            {{ form.original_quantity }}
                                        </p>
                                    </div>

                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                        <p class="text-xs font-semibold text-slate-500">
                                            Resultado
                                        </p>

                                        <p class="mt-1 text-2xl font-black" :class="quantityResultColor">
                                            {{ calculatedQuantity }}
                                        </p>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <p class="text-sm font-semibold text-slate-700">
                                        {{ adjustmentText }}
                                    </p>
                                </div>
                            </div>
                        </aside>
                    </div>

                    <div
                        v-else
                        class="flex min-h-[340px] items-center justify-center px-6 py-10 text-center"
                    >
                        <div class="max-w-md">
                            <p class="text-lg font-black text-slate-900">
                                Selecciona un lote para editarlo
                            </p>

                            <p class="mt-2 text-sm text-slate-500">
                                Desde la lista de la izquierda puedes ajustar fechas, estado, proveedor y cantidad.
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </GlobalModal>
</template>
