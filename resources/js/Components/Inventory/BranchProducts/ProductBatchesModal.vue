<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue'

import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import BatchAdjustmentForm from '@/Components/Inventory/BranchProducts/BatchAdjustmentForm.vue'


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

function closeModal() {
    if (props.processing) return

    emit('close')
}

function handleEsc(event) {
    if (event.key === 'Escape') {
        closeModal()
    }
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

onMounted(() => {
    window.addEventListener('keydown', handleEsc)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEsc)
})
</script>

<template>
    <div class="fixed inset-0 z-50 bg-black/60 flex items-end md:items-center justify-center" role="dialog"
        aria-modal="true">
        <div class="absolute inset-0" @click="closeModal"></div>

        <div class="relative bg-white w-full h-[100dvh] md:h-auto md:max-h-[94vh] md:w-[96%] md:max-w-[1500px] rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden"
            @click.stop>
            <GeneralModalHeader title="Lotes del producto"
                subtitle="Administra, consulta y ajusta los lotes de este producto." :total-errors="totalErrors"
                mode="edit" @close="closeModal" />

            <GeneralModalContent :columns="1">
                <section class="w-full mx-auto rounded-3xl border border-slate-200 bg-white overflow-hidden">
                    <div class="grid grid-cols-1 xl:grid-cols-[390px_minmax(0,1fr)]">
                        <aside class="border-b xl:border-b-0 xl:border-r border-slate-200 bg-slate-50">
                            <div class="p-5 border-b border-slate-200 bg-white">
                                <h3 class="font-black text-slate-900">
                                    {{ productName }}
                                </h3>

                                <p class="text-sm text-slate-500 mt-1">
                                    {{ batches.length }} lote(s) registrados
                                </p>
                            </div>

                            <div class="p-4">
                                <div v-if="batches.length" class="space-y-3 max-h-[620px] overflow-y-auto pr-1">
                                    <button v-for="batch in batches" :key="batch.id" type="button"
                                        class="w-full text-left rounded-2xl border px-4 py-4 bg-white transition"
                                        :class="isSelectedBatch(batch)
                                            ? 'border-blue-500 ring-2 ring-blue-100'
                                            : 'border-slate-200 hover:bg-slate-50 hover:border-slate-300'"
                                        @click="selectBatch(batch)">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="font-black text-sm text-slate-900 truncate">
                                                    {{ batchLabel(batch) }}
                                                </p>

                                                <p class="text-xs text-slate-500 mt-1">
                                                    Disponible:
                                                    <span class="font-semibold text-slate-700">
                                                        {{ batch.quantity }} {{ unit }}
                                                    </span>
                                                </p>
                                            </div>

                                            <span
                                                class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-black"
                                                :class="statusClass(batch.status)">
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

                                            <p class="text-xs text-slate-500 truncate">
                                                Proveedor:
                                                <span class="font-semibold text-slate-700">
                                                    {{ batch.supplier || 'Sin proveedor' }}
                                                </span>
                                            </p>
                                        </div>

                                        <p class="mt-3 text-xs font-black" :class="isSelectedBatch(batch)
                                            ? 'text-blue-700'
                                            : 'text-blue-600'">
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

                        <section class="bg-white">
                            <div
                                class="border-b border-slate-200 px-5 py-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                                <div>
                                    <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                        Editor de lote
                                    </p>

                                    <h3 class="font-black text-slate-900 mt-1">
                                        {{ selectedBatchTitle }}
                                    </h3>
                                </div>

                                <div v-if="selectedBatch" class="flex items-center gap-2">
                                    <span class="rounded-full border px-3 py-1 text-xs font-black"
                                        :class="statusClass(selectedBatch.status)">
                                        {{ statusLabel(selectedBatch.status) }}
                                    </span>

                                    <span class="text-sm font-semibold text-slate-500">
                                        Disponible: {{ selectedBatch.quantity }} {{ unit }}
                                    </span>
                                </div>
                            </div>

                            <div class="p-5 max-h-[620px] overflow-y-auto">
                                <BatchAdjustmentForm v-if="selectedBatch" :form="form" :frontend-errors="frontendErrors"
                                    :processing="processing" :uses-lot="usesLot" :is-seasonal="isSeasonal"
                                    :calculated-quantity="calculatedQuantity" :adjustment-text="adjustmentText"
                                    :quantity-result-color="quantityResultColor" :toggle-lot="toggleLot"
                                    :set-adjustment-type="setAdjustmentType" :validate-field="validateField" />

                                <div v-else
                                    class="min-h-[420px] flex items-center justify-center rounded-3xl border border-dashed border-slate-300 bg-slate-50">
                                    <div class="text-center max-w-md px-6">
                                        <p class="text-lg font-black text-slate-800">
                                            Selecciona un lote para editar
                                        </p>

                                        <p class="text-sm text-slate-500 mt-2">
                                            Elige un lote de la lista izquierda para consultar sus datos, ajustar
                                            cantidad o cambiar su configuración.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </section>
            </GeneralModalContent>

            <GeneralModalFooter :processing="processing" save-button-text="Guardar cambios" close-button-text="Cerrar"
                mode="edit" @save="$emit('save')" @close="closeModal" />
        </div>
    </div>
</template>