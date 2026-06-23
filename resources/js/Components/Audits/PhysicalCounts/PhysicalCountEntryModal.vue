<script setup>
import { computed, onMounted, onBeforeUnmount, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'

import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'

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

const isReadOnly = computed(() => props.mode === 'view')
const isDeleteMode = computed(() => props.mode === 'delete')

const title = computed(() => {
    if (props.mode === 'view') return 'Detalle del registro'
    if (props.mode === 'edit') return 'Editar registro'
    return 'Eliminar registro'
})

const subtitle = computed(() => {
    if (props.mode === 'delete') {
        return 'Confirma la eliminación del registro seleccionado'
    }

    return 'Información del registro de conteo físico'
})

const saveButtonText = computed(() => {
    if (form.processing) return 'Procesando...'
    if (props.mode === 'delete') return 'Eliminar registro'
    return 'Guardar cambios'
})

const form = useForm({
    counted_quantity: 0,
    damaged_quantity: 0,
    expired_quantity: 0,
    expiration_date: '',
    notes: '',
})

const productName = computed(() =>
    props.entry?.branch_product?.product?.name ?? 'Sin producto'
)

const scannedCode = computed(() =>
    props.entry?.scanned_code && props.entry.scanned_code !== 'Seleccionado manualmente'
        ? props.entry.scanned_code
        : (props.entry?.branch_product?.barcode ?? '-')
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

function saveEntry() {
    if (props.mode === 'view') {
        closeModal()
        return
    }

    if (props.mode === 'delete') {
        router.delete(route('audits.physical-count-entries.destroy', props.entry.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        })

        return
    }

    form.patch(route('audits.physical-count-entries.update', props.entry.id), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    })
}

function handleEsc(e) {
    if (e.key === 'Escape') closeModal()
}

watch(
    () => props.entry,
    () => loadEntryData(),
    { immediate: true }
)

onMounted(() => {
    window.addEventListener('keydown', handleEsc)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEsc)
})
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 md:items-center">
        <div class="absolute inset-0" @click="closeModal"></div>

        <section
            class="relative flex h-[100dvh] w-full flex-col overflow-hidden rounded-t-[28px] bg-white shadow-2xl md:h-[88vh] md:w-[94%] md:max-w-[1000px] md:rounded-3xl"
        >
            <GeneralModalHeader
                :title="title"
                :subtitle="subtitle"
                :total-errors="Object.keys(form.errors).length"
                :mode="props.mode"
                @close="closeModal"
            />

            <GeneralModalContent :columns="1">
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
                                />
                                <p v-if="form.errors.counted_quantity" class="mt-1 text-xs text-red-600">
                                    {{ form.errors.counted_quantity }}
                                </p>
                            </div>

                            <div>
                                <label class="text-xs font-medium text-slate-500">Cantidad dañada</label>
                                <input
                                    v-model="form.damaged_quantity"
                                    type="number"
                                    min="0"
                                    :readonly="isReadOnly || isDeleteMode"
                                    class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100"
                                />
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
                                />
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
                            />
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
            </GeneralModalContent>

            <GeneralModalFooter
                :processing="form.processing"
                :save-button-text="saveButtonText"
                :mode="props.mode"
                @save="saveEntry"
                @close="closeModal"
            />
        </section>
    </div>
</template>
