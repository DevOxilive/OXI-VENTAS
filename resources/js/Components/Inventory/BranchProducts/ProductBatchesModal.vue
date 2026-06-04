<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue'

import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'

const emit = defineEmits(['close', 'adjust-batch'])

const props = defineProps({
    product: {
        type: Object,
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

const totalErrors = computed(() => 0)

function closeModal() {
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
        EXPIRED: 'Caducado',
        DEPLETED: 'Agotado',
        RETURNED: 'Devuelto',
    }

    return labels[status] || status || 'Sin estado'
}

function statusClass(status) {
    const classes = {
        ACTIVE: 'bg-emerald-100 text-emerald-700 border-emerald-200',
        INACTIVE: 'bg-slate-100 text-slate-600 border-slate-200',
        EXPIRED: 'bg-red-100 text-red-700 border-red-200',
        DEPLETED: 'bg-orange-100 text-orange-700 border-orange-200',
        RETURNED: 'bg-blue-100 text-blue-700 border-blue-200',
    }

    return classes[status] || 'bg-slate-100 text-slate-600 border-slate-200'
}

function seasonLabel(batch) {
    if (!batch.season_start_date && !batch.season_end_date) {
        return 'Sin temporada'
    }

    return `${formatDate(batch.season_start_date)} - ${formatDate(batch.season_end_date)}`
}

function adjustBatch(batch) {
    emit('adjust-batch', batch)
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

        <div class="relative bg-white w-full h-[100dvh] md:h-auto md:max-h-[92vh] md:w-[94%] md:max-w-6xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden"
            @click.stop>
            <GeneralModalHeader title="Lotes del producto"
                subtitle="Selecciona el lote que necesitas corregir o configurar." :total-errors="totalErrors"
                mode="edit" @close="closeModal" />

            <GeneralModalContent :columns="1">
                <section class="w-full max-w-5xl mx-auto rounded-3xl border border-slate-200 bg-white overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h3 class="font-black text-slate-900">
                            {{ productName }}
                        </h3>

                        <p class="text-sm text-slate-500 mt-1">
                            {{ batches.length }} lote(s) registrados
                        </p>
                    </div>

                    <div class="p-5">
                        <div v-if="batches.length"
                            class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 max-h-[520px] overflow-y-auto pr-1">
                            <button v-for="batch in batches" :key="batch.id" type="button"
                                class="text-left rounded-2xl border border-slate-200 px-4 py-4 bg-white hover:bg-slate-50 hover:border-slate-300 transition"
                                @click="adjustBatch(batch)">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-black text-sm text-slate-900 truncate">
                                            {{ batchLabel(batch) }}
                                        </p>

                                        <p class="text-xs text-slate-500 mt-1">
                                            Disponible: {{ batch.quantity }} {{ unit }}
                                        </p>
                                    </div>

                                    <span class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-black"
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

                                <p class="mt-3 text-xs font-black text-blue-600">
                                    Editar lote
                                </p>
                            </button>
                        </div>

                        <div v-else class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-10 text-center">
                            <p class="text-sm font-semibold text-slate-600">
                                Este producto no tiene lotes registrados.
                            </p>
                        </div>
                    </div>
                </section>
            </GeneralModalContent>

            <GeneralModalFooter :processing="false" save-button-text="Aceptar" close-button-text="Cerrar" mode="edit"
                @save="closeModal" @close="closeModal" />
        </div>
    </div>
</template>