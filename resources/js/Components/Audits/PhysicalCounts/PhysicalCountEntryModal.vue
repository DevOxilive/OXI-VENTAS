<script setup>
import { computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import {
    confirmModalAction,
    getModalRequestOptions,
} from '@/Components/Modales/useModalConfig'
import { getPhysicalCountEntryModalConfig } from '@/config/ModalConfigs/physicalCountEntryModalConfig'

const emit = defineEmits(['close'])

const props = defineProps({
    mode: {
        type: String,
        required: true,
    },
    entry: {
        type: Object,
        required: true,
    },
})

const form = useForm({
    counted_quantity: 0,
    damaged_quantity: 0,
    expired_quantity: 0,
    expiration_date: '',
    notes: '',
})

const isReadOnly = computed(() => props.mode === 'view')
const isDeleteMode = computed(() => props.mode === 'delete')
const totalErrors = computed(() => Object.keys(form.errors || {}).length)

const modalConfig = computed(() => getPhysicalCountEntryModalConfig({
    mode: props.mode,
    totalErrors: totalErrors.value,
    processing: form.processing,
}))

const productName = computed(() =>
    props.entry?.branch_product?.product?.name ?? 'Sin producto'
)

const scannedCode = computed(() =>
    props.entry?.scanned_code ?? '-'
)

const lotNumber = computed(() =>
    props.entry?.product_batch?.lot_number ?? 'N/A'
)

const userName = computed(() =>
    props.entry?.user?.name ?? 'Sin usuario'
)

const entryDate = computed(() => {
    if (!props.entry?.created_at) return '-'

    return new Date(props.entry.created_at).toLocaleDateString('es-MX')
})

const entryTime = computed(() => {
    if (!props.entry?.created_at) return '-'

    return new Date(props.entry.created_at).toLocaleTimeString('es-MX', {
        hour: '2-digit',
        minute: '2-digit',
    })
})

function loadEntryData() {
    form.counted_quantity = props.entry?.counted_quantity ?? 0
    form.damaged_quantity = props.entry?.damaged_quantity ?? 0
    form.expired_quantity = props.entry?.expired_quantity ?? 0
    form.expiration_date = props.entry?.expiration_date ?? ''
    form.notes = props.entry?.notes ?? ''
}

function closeModal() {
    emit('close')
}

async function saveEntry() {
    if (props.mode === 'view') {
        closeModal()
        return
    }

    if (props.mode === 'delete') {
        const result = await confirmModalAction({
            mode: 'delete',
            entityName: 'registro',
            title: 'Eliminar registro',
            message: '¿Deseas eliminar este registro del conteo físico?',
            confirmText: 'Sí, eliminar',
        })

        if (!result.isConfirmed) return

        router.delete(route('audits.physical-count-entries.destroy', props.entry.id), getModalRequestOptions({
            mode: 'delete',
            entityName: modalConfig.value.alerts.entityName,
            close: closeModal,
            successTitle: modalConfig.value.alerts.delete.successTitle,
            errorTitle: modalConfig.value.alerts.delete.errorTitle,
            errorMessage: modalConfig.value.alerts.delete.errorMessage,
        }))

        return
    }

    form.patch(route('audits.physical-count-entries.update', props.entry.id), getModalRequestOptions({
        mode: 'edit',
        entityName: modalConfig.value.alerts.entityName,
        close: closeModal,
        successTitle: modalConfig.value.alerts.edit.successTitle,
        errorTitle: modalConfig.value.alerts.edit.errorTitle,
        errorMessage: modalConfig.value.alerts.edit.errorMessage,
    }))
}

watch(
    () => props.entry,
    () => loadEntryData(),
    { immediate: true },
)
</script>

<template>
    <GlobalModal
        v-bind="modalConfig"
        @save="saveEntry"
        @close="closeModal"
    >
        <div class="space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">
                    Información del producto
                </h3>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <p class="text-xs font-medium text-slate-500">Producto</p>
                        <p class="text-sm font-semibold text-slate-800">{{ productName }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-slate-500">Código escaneado</p>
                        <p class="text-sm font-semibold text-slate-800">{{ scannedCode }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-slate-500">Lote</p>
                        <p class="text-sm font-semibold text-slate-800">{{ lotNumber }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-slate-500">Usuario</p>
                        <p class="text-sm font-semibold text-slate-800">{{ userName }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-slate-500">Fecha</p>
                        <p class="text-sm font-semibold text-slate-800">{{ entryDate }}</p>
                    </div>

                    <div>
                        <p class="text-xs font-medium text-slate-500">Hora</p>
                        <p class="text-sm font-semibold text-slate-800">{{ entryTime }}</p>
                    </div>
                </div>
            </div>

            <div
                v-if="isDeleteMode"
                class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
            >
                Esta acción eliminará el registro del conteo físico. El comparativo de inventario se recalculará con los registros restantes.
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-4">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">
                    Cantidades registradas
                </h3>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="text-xs font-medium text-slate-500">Cantidad contada</label>
                        <input
                            v-model="form.counted_quantity"
                            type="number"
                            min="0"
                            :readonly="isReadOnly || isDeleteMode"
                            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100"
                        >
                        <p v-if="form.errors.counted_quantity" class="mt-1 text-xs text-red-600">
                            {{ form.errors.counted_quantity }}
                        </p>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-slate-500">Cantidad danada</label>
                        <input
                            v-model="form.damaged_quantity"
                            type="number"
                            min="0"
                            :readonly="isReadOnly || isDeleteMode"
                            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100"
                        >
                        <p v-if="form.errors.damaged_quantity" class="mt-1 text-xs text-red-600">
                            {{ form.errors.damaged_quantity }}
                        </p>
                    </div>

                    <div>
                        <label class="text-xs font-medium text-slate-500">Cantidad caducada</label>
                        <input
                            v-model="form.expired_quantity"
                            type="number"
                            min="0"
                            :readonly="isReadOnly || isDeleteMode"
                            class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100"
                        >
                        <p v-if="form.errors.expired_quantity" class="mt-1 text-xs text-red-600">
                            {{ form.errors.expired_quantity }}
                        </p>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="text-xs font-medium text-slate-500">Caducidad</label>
                    <input
                        v-model="form.expiration_date"
                        type="date"
                        :readonly="isReadOnly || isDeleteMode"
                        class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                    >
                </div>

                <div class="mt-4">
                    <label class="text-xs font-medium text-slate-500">Observaciones</label>
                    <textarea
                        v-model="form.notes"
                        rows="4"
                        :readonly="isReadOnly || isDeleteMode"
                        class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"
                    />
                </div>
            </div>
        </div>
    </GlobalModal>
</template>
