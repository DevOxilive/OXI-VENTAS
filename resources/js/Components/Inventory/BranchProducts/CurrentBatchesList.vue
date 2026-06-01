<script setup>
import { computed } from 'vue'

const props = defineProps({
    batches: {
        type: Array,
        default: () => [],
    },
    showEdit: {
        type: Boolean,
        default: false,
    },
    clickable: {
        type: Boolean,
        default: false,
    },
    allocationMethod: {
        type: String,
        default: '',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['edit-batch', 'select-batch'])

const trackedBatches = computed(() => {
    return props.batches.filter(batch => batch.lot_number)
})

const untrackedBatches = computed(() => {
    return props.batches.filter(batch => !batch.lot_number)
})

function canSelectBatch() {
    return props.clickable && props.allocationMethod === 'MANUAL' && !props.disabled
}

function selectBatch(batch) {
    if (!canSelectBatch()) return

    emit('select-batch', batch)
}

function editBatch(batch) {
    if (props.disabled) return

    emit('edit-batch', batch)
}

function batchStatusLabel(status) {
    return {
        ACTIVE: 'Activo',
        VALID: 'Vigente',
        EXPIRED: 'Caducado',
        NEAR_EXPIRATION: 'Por vencer',
        NO_EXPIRATION: 'Sin caducidad',
        EXHAUSTED: 'Agotado',
    }[status] || 'Sin estado'
}

function batchStatusClass(status) {
    return {
        ACTIVE: 'bg-emerald-100 text-emerald-700',
        VALID: 'bg-emerald-100 text-emerald-700',
        EXPIRED: 'bg-red-100 text-red-700',
        NEAR_EXPIRATION: 'bg-orange-100 text-orange-700',
        NO_EXPIRATION: 'bg-slate-100 text-slate-600',
        EXHAUSTED: 'bg-zinc-100 text-zinc-500',
    }[status] || 'bg-slate-100 text-slate-600'
}
</script>

<template>
    <div v-if="batches.length" class="space-y-5">
        <div v-if="trackedBatches.length" class="space-y-2">
            <p class="text-xs font-black uppercase tracking-wide text-slate-500">
                Lotes registrados
            </p>

            <component :is="clickable ? 'button' : 'div'" v-for="batch in trackedBatches" :key="batch.id" type="button"
                :disabled="clickable && disabled"
                class="w-full rounded-2xl border border-slate-200 bg-white p-3 text-left transition" :class="[
                    clickable && allocationMethod === 'MANUAL' && !disabled
                        ? 'hover:border-[#1f1d2b]'
                        : '',
                    clickable && (allocationMethod !== 'MANUAL' || disabled)
                        ? 'cursor-default opacity-80'
                        : '',
                ]" @click="selectBatch(batch)">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="font-black text-sm text-slate-800 truncate">
                                {{ batch.lot_number }}
                            </p>

                            <span class="px-2 py-0.5 rounded-full text-[11px] font-black"
                                :class="batchStatusClass(batch.expiration_status || batch.status)">
                                {{ batchStatusLabel(batch.expiration_status || batch.status) }}
                            </span>
                        </div>

                        <p class="text-xs text-slate-500 mt-1">
                            Caduca: {{ batch.formatted_expiration_date || 'Sin fecha' }}
                        </p>

                        <p v-if="batch.supplier" class="text-xs text-slate-400 mt-0.5">
                            Proveedor: {{ batch.supplier }}
                        </p>
                    </div>

                    <div class="text-right shrink-0">
                        <p class="text-xs text-slate-500">
                            Disponible
                        </p>

                        <p class="text-lg font-black text-slate-900">
                            {{ batch.quantity }}
                        </p>

                        <button v-if="showEdit" type="button" :disabled="disabled"
                            class="mt-2 inline-flex items-center gap-1 text-xs font-black text-[#1f1d2b] hover:underline disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:no-underline"
                            @click.stop="editBatch(batch)">
                            <span class="material-symbols-outlined text-[14px]">
                                edit
                            </span>

                            Editar
                        </button>
                    </div>
                </div>

                <div v-if="clickable && allocationMethod === 'FEFO_AUTO'"
                    class="mt-3 flex items-center justify-between">
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-black text-emerald-700">
                        FEFO sugerido
                    </span>

                    <span class="text-xs text-slate-400">
                        Ordenado por caducidad
                    </span>
                </div>

                <div v-if="clickable && allocationMethod === 'MANUAL' && !disabled"
                    class="mt-3 text-xs font-bold text-[#1f1d2b]">
                    Click para usar este lote
                </div>
            </component>
        </div>

        <div v-if="untrackedBatches.length" class="space-y-2">
            <p class="text-xs font-black uppercase tracking-wide text-slate-500">
                Cantidades sin lote
            </p>

            <component :is="clickable ? 'button' : 'div'" v-for="batch in untrackedBatches" :key="batch.id"
                type="button" :disabled="clickable && disabled"
                class="w-full rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-3 text-left transition"
                :class="[
                    clickable && allocationMethod === 'MANUAL' && !disabled
                        ? 'hover:border-[#1f1d2b] hover:bg-white'
                        : '',
                    clickable && (allocationMethod !== 'MANUAL' || disabled)
                        ? 'cursor-default opacity-80'
                        : '',
                ]" @click="selectBatch(batch)">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="font-black text-sm text-slate-800 truncate">
                                Sin lote
                            </p>

                            <span class="px-2 py-0.5 rounded-full text-[11px] font-black"
                                :class="batchStatusClass(batch.expiration_status || batch.status)">
                                {{ batchStatusLabel(batch.expiration_status || batch.status) }}
                            </span>

                            <span class="px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700 text-[11px] font-black">
                                A granel
                            </span>
                        </div>

                        <p class="text-xs text-slate-500 mt-1">
                            Ingreso: {{ batch.formatted_received_at || batch.received_at || 'Sin fecha' }}
                        </p>

                        <p class="text-xs text-slate-400 mt-0.5">
                            Este stock no tiene número de lote registrado.
                        </p>
                    </div>

                    <div class="text-right shrink-0">
                        <p class="text-xs text-slate-500">
                            Disponible
                        </p>

                        <p class="text-lg font-black text-slate-900">
                            {{ batch.quantity }}
                        </p>

                        <button v-if="showEdit" type="button" :disabled="disabled"
                            class="mt-2 inline-flex items-center gap-1 text-xs font-black text-[#1f1d2b] hover:underline disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:no-underline"
                            @click.stop="editBatch(batch)">
                            <span class="material-symbols-outlined text-[14px]">
                                edit
                            </span>

                            Editar
                        </button>
                    </div>
                </div>

                <div v-if="clickable && allocationMethod === 'MANUAL' && !disabled"
                    class="mt-3 text-xs font-bold text-[#1f1d2b]">
                    Click para usar esta cantidad
                </div>
            </component>
        </div>
    </div>
</template>