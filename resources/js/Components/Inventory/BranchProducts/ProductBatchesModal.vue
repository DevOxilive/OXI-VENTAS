<script setup>
import { computed } from 'vue'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import { getProductBatchesModalConfig } from '@/config/ModalConfigs/productBatchesModalConfig'

const emit = defineEmits(['close', 'select-batch', 'save'])

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
    toggleLot: {
        type: Function,
        default: () => {},
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
        ACTIVE: 'bg-emerald-100 text-emerald-700 border-emerald-200',
        INACTIVE: 'bg-slate-100 text-slate-600 border-slate-200',
        SEASONAL: 'bg-amber-100 text-amber-700 border-amber-200',
    }

    return classes[status] || 'bg-slate-100 text-slate-600 border-slate-200'
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
        <section class="flex h-full min-h-0 w-full flex-col overflow-y-auto xl:block xl:overflow-hidden">
            <div class="grid min-h-full grid-cols-1 xl:h-full xl:min-h-0 xl:grid-cols-[390px_minmax(0,1fr)]">
                <aside class="flex min-h-[260px] max-h-[42dvh] flex-col border-b border-slate-200 bg-slate-50 xl:min-h-0 xl:max-h-none xl:border-b-0 xl:border-r">
                    <div class="border-b border-slate-200 bg-white p-5">
                        <h3 class="font-black text-slate-900">
                            {{ productName }}
                        </h3>

                        <p class="mt-1 text-sm text-slate-500">
                            {{ batches.length }} lote(s) registrados
                        </p>
                    </div>

                    <div class="min-h-0 flex-1 p-4">
                        <div v-if="batches.length" class="h-full space-y-3 overflow-y-auto pr-1">
                            <button
                                v-for="batch in batches"
                                :key="batch.id"
                                type="button"
                                class="w-full rounded-2xl border bg-white px-4 py-4 text-left transition"
                                :class="isSelectedBatch(batch)
                                    ? 'border-blue-500 ring-2 ring-blue-100'
                                    : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                                @click="selectBatch(batch)"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-black text-slate-900">
                                            {{ batchLabel(batch) }}
                                        </p>

                                        <p class="mt-1 text-xs text-slate-500">
                                            Disponible:
                                            <span class="font-semibold text-slate-700">
                                                {{ batch.quantity }} {{ unit }}
                                            </span>
                                        </p>
                                    </div>

                                    <span
                                        class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-black"
                                        :class="statusClass(batch.status)"
                                    >
                                        {{ statusLabel(batch.status) }}
                                    </span>
                                </div>

                                <div class="mt-3 space-y-1">
                                    <p class="text-xs text-slate-500">
                                        Caducidad:
                                        <span class="font-semibold text-slate-700">
                                            {{ formatDate(batch.expiration_date) }}
                                        </span>
                                    </p>

                                    <p class="text-xs text-slate-500">
                                        Entrada:
                                        <span class="font-semibold text-slate-700">
                                            {{ formatDate(batch.received_at) }}
                                        </span>
                                    </p>

                                    <p class="text-xs text-slate-500">
                                        Temporada:
                                        <span class="font-semibold text-slate-700">
                                            {{ seasonLabel(batch) }}
                                        </span>
                                    </p>

                                    <p class="truncate text-xs text-slate-500">
                                        Proveedor:
                                        <span class="font-semibold text-slate-700">
                                            {{ batch.supplier || 'Sin proveedor' }}
                                        </span>
                                    </p>
                                </div>

                                <p
                                    class="mt-3 text-xs font-black"
                                    :class="isSelectedBatch(batch) ? 'text-blue-700' : 'text-blue-600'"
                                >
                                    {{ isSelectedBatch(batch) ? 'Editando este lote' : 'Editar lote' }}
                                </p>
                            </button>
                        </div>

                        <div v-else class="rounded-2xl border border-slate-200 bg-white px-4 py-10 text-center">
                            <p class="text-sm font-semibold text-slate-600">
                                Este producto no tiene lotes registrados.
                            </p>
                        </div>
                    </div>
                </aside>

                <section class="flex min-h-[520px] flex-col bg-white xl:min-h-0">
                    <div class="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Editor de lote
                            </p>

                            <h3 class="mt-1 font-black text-slate-900">
                                {{ selectedBatchTitle }}
                            </h3>
                        </div>

                        <div v-if="selectedBatch" class="flex items-center gap-2">
                            <span class="rounded-full border px-3 py-1 text-xs font-black" :class="statusClass(selectedBatch.status)">
                                {{ statusLabel(selectedBatch.status) }}
                            </span>

                            <span class="text-sm font-semibold text-slate-500">
                                Disponible: {{ selectedBatch.quantity }} {{ unit }}
                            </span>
                        </div>
                    </div>

                    <div class="min-h-0 flex-1 overflow-y-auto p-5">
                        <section v-if="selectedBatch" class="grid grid-cols-1 gap-5 xl:grid-cols-3">
                            <section class="space-y-4">
                                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                                    <p class="text-sm font-black text-slate-800">
                                        Informacion del lote
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
                                                :readonly="processing"
                                                :rows="4"
                                                placeholder="Ej. Correccion por error de captura..."
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

                                        <div v-else class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                            <p class="text-sm font-semibold text-slate-700">
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

                                            <p class="text-3xl font-black" :class="quantityResultColor">
                                                {{ calculatedQuantity }}
                                            </p>
                                        </div>

                                        <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                            <p class="text-sm font-semibold" :class="quantityResultColor">
                                                {{ adjustmentText }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        </section>

                        <div
                            v-else
                            class="flex min-h-[420px] items-center justify-center rounded-3xl border border-dashed border-slate-300 bg-slate-50"
                        >
                            <div class="max-w-md px-6 text-center">
                                <p class="text-lg font-black text-slate-800">
                                    Selecciona un lote para editar
                                </p>

                                <p class="mt-2 text-sm text-slate-500">
                                    Elige un lote de la lista izquierda para consultar sus datos, ajustar cantidad o cambiar su configuracion.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </GlobalModal>
</template>
