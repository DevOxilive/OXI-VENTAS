<script setup>
import { onMounted, onBeforeUnmount, computed, ref, watch, reactive } from 'vue'
import { useForm } from '@inertiajs/vue3'

import { useAdjustStockForm } from '@/Composables/Inventory/useAdjustStockForm'

import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'

const emit = defineEmits(['close'])

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
})

const {
    form,
    frontendErrors,
    currentStock,
    errorSummary,
    validateField,
    saveAdjustment,
    addBatch,
} = useAdjustStockForm(props, emit)

const usesLot = ref(false)
const configErrors = reactive({})

const configForm = useForm({
    min_stock: props.product.minStock ?? 0,
    status: props.product.administrativeStatus ?? 'active',
    season_start_date: props.product.seasonStartDate ?? '',
    season_end_date: props.product.seasonEndDate ?? '',
})

const statusOptions = [
    { label: 'Activo', value: 'active' },
    { label: 'Inactivo', value: 'inactive' },
    { label: 'Temporada', value: 'seasonal' },
]

const productName = computed(() => {
    return props.product?.name ?? props.product?.product?.name ?? 'Producto'
})

const unit = computed(() => props.product?.unit ?? 'pieza')

const entry = computed(() => form.batches?.[0] ?? null)

const today = computed(() => {
    return new Date().toISOString().slice(0, 10)
})

const hasConfigChanges = computed(() => {
    return Number(configForm.min_stock) !== Number(props.product.minStock ?? 0)
        || configForm.status !== (props.product.administrativeStatus ?? 'active')
        || configForm.season_start_date !== (props.product.seasonStartDate ?? '')
        || configForm.season_end_date !== (props.product.seasonEndDate ?? '')
})

const totalErrors = computed(() => {
    return errorSummary.value.length
        + Object.values(configErrors).filter(Boolean).length
        + Object.values(configForm.errors || {}).filter(Boolean).length
})

function ensureEntryReady() {
    form.type = 'IN'
    form.reason = 'PURCHASE'

    if (!form.batches?.length) {
        addBatch()
    }

    if (entry.value) {
        entry.value.received_at = entry.value.received_at || today.value
        entry.value.lot_number = usesLot.value ? entry.value.lot_number || '' : null
    }
}

function syncQuantity() {
    if (!entry.value) return

    form.quantity = entry.value.quantity || ''
}

function toggleLot() {
    usesLot.value = !usesLot.value

    if (!entry.value) return

    entry.value.lot_number = usesLot.value ? '' : null
}

function validateEntry() {
    frontendErrors.quantity = ''
    frontendErrors.received_at = ''
    frontendErrors.expiration_date = ''
    frontendErrors.lot_number = ''
    frontendErrors.batches = ''

    if (!entry.value?.quantity || Number(entry.value.quantity) <= 0) {
        frontendErrors.quantity = 'La cantidad debe ser mayor a cero.'
    }

    if (!entry.value?.received_at) {
        frontendErrors.received_at = 'La fecha de entrada es obligatoria.'
    }

    if (!entry.value?.expiration_date) {
        frontendErrors.expiration_date = 'La caducidad es obligatoria.'
    }

    if (usesLot.value && !entry.value?.lot_number) {
        frontendErrors.lot_number = 'El número de lote es obligatorio.'
    }

    return !frontendErrors.quantity
        && !frontendErrors.received_at
        && !frontendErrors.expiration_date
        && !frontendErrors.lot_number
        && !frontendErrors.batches
}

function validateConfigField(field) {
    configErrors[field] = ''

    if (field === 'min_stock' && Number(configForm.min_stock) < 0) {
        configErrors.min_stock = 'El stock mínimo no puede ser menor a cero.'
    }

    if (field === 'status' && !configForm.status) {
        configErrors.status = 'Selecciona un estado operativo.'
    }

    if (configForm.status !== 'seasonal') return

    if (field === 'season_start_date' && !configForm.season_start_date) {
        configErrors.season_start_date = 'Indica cuándo inicia la temporada.'
    }

    if (field === 'season_end_date' && !configForm.season_end_date) {
        configErrors.season_end_date = 'Indica cuándo termina la temporada.'
    }

    if (
        field === 'season_end_date'
        && configForm.season_start_date
        && configForm.season_end_date
        && configForm.season_end_date < configForm.season_start_date
    ) {
        configErrors.season_end_date = 'La fecha de fin no puede ser anterior al inicio.'
    }
}

function validateConfig() {
    configErrors.min_stock = ''
    configErrors.status = ''
    configErrors.season_start_date = ''
    configErrors.season_end_date = ''

    validateConfigField('min_stock')
    validateConfigField('status')

    if (configForm.status === 'seasonal') {
        validateConfigField('season_start_date')
        validateConfigField('season_end_date')
    }

    return !Object.values(configErrors).some(Boolean)
}

function saveEntry() {
    if (!validateEntry()) return

    if (showConfig.value && !validateConfig()) return

    if (!showConfig.value || !hasConfigChanges.value) {
        saveAdjustment()
        return
    }

    configForm.patch(route('inventario.branch-inventory.update-config', props.product.id), {
        preserveScroll: true,
        onSuccess: () => saveAdjustment(),
    })
}

function closeModal() {
    if (form.processing || configForm.processing) return

    emit('close')
}

function handleEsc(e) {
    if (e.key === 'Escape') closeModal()
}
const showConfig = ref(false)

function toggleConfig() {
    showConfig.value = !showConfig.value
}
watch(
    () => entry.value?.quantity,
    syncQuantity,
    { immediate: true }
)

watch(
    entry,
    () => ensureEntryReady(),
    { immediate: true }
)

watch(
    () => props.product,
    (product) => {
        configForm.min_stock = product.minStock ?? 0
        configForm.status = product.administrativeStatus ?? 'active'
        configForm.season_start_date = product.seasonStartDate ?? ''
        configForm.season_end_date = product.seasonEndDate ?? ''
    },
    { immediate: true }
)

onMounted(() => {
    ensureEntryReady()
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

        <div class="relative bg-white w-full h-[100dvh] md:h-auto md:max-h-[92vh] md:w-[96%] md:max-w-6xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden"
            @click.stop>
            <GeneralModalHeader title="Entrada" subtitle="Registra producto que entra al inventario."
                :total-errors="totalErrors" mode="create" @close="closeModal" />

            <GeneralModalContent :columns="1">
                <section v-if="entry"
                    class="w-full max-w-5xl mx-auto rounded-3xl border border-slate-200 bg-white overflow-hidden">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h3 class="font-black text-slate-900">
                            {{ productName }}
                        </h3>

                        <p class="text-sm text-slate-500 mt-1">
                            Stock actual: {{ currentStock }} {{ unit }}
                        </p>
                    </div>

                    <div class="p-5">
                        <div class="grid grid-cols-1 lg:grid-cols-[1.2fr_0.8fr] gap-5">
                            <div class="space-y-4">
                                <InputField v-model="entry.quantity" :label="`Cantidad (${unit})`" type="number"
                                    field="quantity" :readonly="form.processing || configForm.processing"
                                    @blur="validateField('quantity')" />

                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-sm font-bold text-slate-800">
                                                ¿Tiene lote?
                                            </p>

                                            <p class="text-xs text-slate-500 mt-0.5">
                                                Actívalo si el producto trae número de lote.
                                            </p>
                                        </div>

                                        <button type="button" :disabled="form.processing || configForm.processing"
                                            class="relative inline-flex h-8 w-16 items-center rounded-full transition disabled:opacity-50 shrink-0"
                                            :class="usesLot ? 'bg-[#1f1d2b]' : 'bg-slate-300'" @click="toggleLot">
                                            <span
                                                class="inline-block h-6 w-6 transform rounded-full bg-white shadow transition"
                                                :class="usesLot ? 'translate-x-9' : 'translate-x-1'" />

                                            <span class="absolute text-[10px] font-black uppercase"
                                                :class="usesLot ? 'left-3 text-white' : 'right-3 text-slate-600'">
                                                {{ usesLot ? 'Sí' : 'No' }}
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <InputField v-if="usesLot" v-model="entry.lot_number" label="Número de lote"
                                    placeholder="Ej. LALA-001" field="lot_number"
                                    :readonly="form.processing || configForm.processing"
                                    @blur="validateField('lot_number')" />

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <InputField v-model="entry.received_at" label="Fecha de entrada" type="date"
                                        field="received_at" :readonly="form.processing || configForm.processing"
                                        @blur="validateField('received_at')" />

                                    <InputField v-model="entry.expiration_date" label="Caducidad" type="date"
                                        field="expiration_date" :readonly="form.processing || configForm.processing"
                                        @blur="validateField('expiration_date')" />
                                </div>

                                <InputField v-model="entry.supplier" label="Proveedor" placeholder="Opcional"
                                    field="supplier" :readonly="form.processing || configForm.processing" />

                                <TextareaField v-model="form.notes" label="Notas" placeholder="Opcional" field="notes"
                                    :readonly="form.processing || configForm.processing" />
                            </div>

                            <div class="space-y-4">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <div class="flex items-center justify-between gap-4">
                                        <div>
                                            <p class="text-sm font-bold text-slate-800">
                                                Configuración
                                            </p>

                                            <p class="text-xs text-slate-500 mt-0.5">
                                                Modifica stock mínimo o estado si es necesario.
                                            </p>
                                        </div>

                                        <button type="button" :disabled="form.processing || configForm.processing"
                                            class="relative inline-flex h-8 w-16 items-center rounded-full transition disabled:opacity-50 shrink-0"
                                            :class="showConfig ? 'bg-[#1f1d2b]' : 'bg-slate-300'" @click="toggleConfig">
                                            <span
                                                class="inline-block h-6 w-6 transform rounded-full bg-white shadow transition"
                                                :class="showConfig ? 'translate-x-9' : 'translate-x-1'" />

                                            <span class="absolute text-[10px] font-black uppercase"
                                                :class="showConfig ? 'left-3 text-white' : 'right-3 text-slate-600'">
                                                {{ showConfig ? 'Sí' : 'No' }}
                                            </span>
                                        </button>
                                    </div>
                                </div>

                                <section v-if="showConfig" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="space-y-4">
                                        <InputField v-model="configForm.min_stock" :label="`Stock mínimo (${unit})`"
                                            field="min_stock" type="number"
                                            :readonly="form.processing || configForm.processing"
                                            :error="configErrors.min_stock || configForm.errors.min_stock"
                                            @validate="validateConfigField" />

                                        <SelectField v-model="configForm.status" label="Estado" field="status"
                                            :options="statusOptions"
                                            :disabled="form.processing || configForm.processing"
                                            :error="configErrors.status || configForm.errors.status"
                                            @validate="validateConfigField" />

                                    </div>
                                    <div v-if="configForm.status === 'seasonal'" class="grid grid-cols-1 gap-4">
                                        <InputField v-model="configForm.season_start_date" label="Inicio de temporada"
                                            field="season_start_date" type="date"
                                            :readonly="form.processing || configForm.processing"
                                            :error="configErrors.season_start_date || configForm.errors.season_start_date"
                                            @validate="validateConfigField" />

                                        <InputField v-model="configForm.season_end_date" label="Fin estimado"
                                            field="season_end_date" type="date"
                                            :readonly="form.processing || configForm.processing"
                                            :error="configErrors.season_end_date || configForm.errors.season_end_date"
                                            @validate="validateConfigField" />
                                    </div>
                                </section>
                            </div>
                        </div>

                        <div v-if="usesLot" class="mt-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                            <p class="text-sm text-amber-800">
                                El número de lote será obligatorio para esta entrada.
                            </p>
                        </div>

                        <div v-if="frontendErrors.quantity || frontendErrors.received_at || frontendErrors.expiration_date || frontendErrors.lot_number || frontendErrors.batches"
                            class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 space-y-1">
                            <p v-if="frontendErrors.quantity" class="text-sm font-semibold text-red-700">
                                {{ frontendErrors.quantity }}
                            </p>

                            <p v-if="frontendErrors.received_at" class="text-sm font-semibold text-red-700">
                                {{ frontendErrors.received_at }}
                            </p>

                            <p v-if="frontendErrors.expiration_date" class="text-sm font-semibold text-red-700">
                                {{ frontendErrors.expiration_date }}
                            </p>

                            <p v-if="frontendErrors.lot_number" class="text-sm font-semibold text-red-700">
                                {{ frontendErrors.lot_number }}
                            </p>

                            <p v-if="frontendErrors.batches" class="text-sm font-semibold text-red-700">
                                {{ frontendErrors.batches }}
                            </p>
                        </div>
                    </div>
                </section>
            </GeneralModalContent>

            <GeneralModalFooter :processing="form.processing || configForm.processing"
                save-button-text="Registrar entrada" close-button-text="Cancelar" mode="create" @save="saveEntry"
                @close="closeModal" />
        </div>
    </div>
</template>