<script setup>
import { computed, onMounted, watch } from 'vue'
import { useAdjustStockForm } from '@/Composables/Inventory/useAdjustStockForm'
import FormPanel from '@/Components/Cards/FormPanel.vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import { getStockEntryModalConfig } from '@/config/ModalConfigs/stockEntryModalConfig'

import InputField from '@/Components/Forms/InputField.vue'
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'

const emit = defineEmits(['close'])

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
    branches: {
        type: Array,
        default: () => [],
    },
    currentBranch: {
        type: Object,
        default: null,
    },
})

const {
    form,
    frontendErrors,
    errorSummary,
    saveAdjustment,
    addBatch,
} = useAdjustStockForm(props, emit)

const productName = computed(() => {
    return props.product?.name ?? props.product?.product?.name ?? 'Producto'
})

const unit = computed(() => props.product?.unit ?? 'pieza')

const entry = computed(() => form.batches?.[0] ?? null)

const today = computed(() => new Date().toISOString().slice(0, 10))

const minExpirationDate = computed(() => {
    const date = new Date(today.value)
    date.setDate(date.getDate() + 1)

    return date.toISOString().slice(0, 10)
})

const totalErrors = computed(() => errorSummary.value.length)

const modalConfig = computed(() => getStockEntryModalConfig({
    totalErrors: totalErrors.value,
    processing: form.processing,
    productName: productName.value,
}))

const branchOptions = computed(() => {
    return (props.branches ?? []).filter((branch) => branch?.id)
})

const currentBranchId = computed(() => props.currentBranch?.id ?? null)

const selectedBranches = computed(() => {
    return branchOptions.value.filter((branch) => {
        return Boolean(findAllocation(branch.id))
    })
})

const totalAllocated = computed(() => {
    return form.branch_allocations.reduce((sum, allocation) => {
        return sum + Number(allocation.quantity || 0)
    }, 0)
})

const totalQuantity = computed(() => Number(entry.value?.quantity || 0))

const remainingQuantity = computed(() => {
    return totalQuantity.value - totalAllocated.value
})

const branchDistributionEnabled = computed(() => {
    return selectedBranches.value.length > 1
})

function normalizeLotNumber(value = entry.value?.lot_number) {
    if (value === null || value === undefined) return

    entry.value.lot_number = value.toString().toUpperCase()
}

function buildAllocation(branchId, quantity = '') {
    return {
        branch_id: branchId,
        quantity,
    }
}

function findAllocation(branchId) {
    return form.branch_allocations.find((allocation) => {
        return Number(allocation.branch_id) === Number(branchId)
    }) ?? null
}

function ensureCurrentBranchAllocation(forceQuantity = false) {
    if (!currentBranchId.value) return

    const existingAllocation = findAllocation(currentBranchId.value)

    if (!existingAllocation) {
        form.branch_allocations.unshift(
            buildAllocation(currentBranchId.value, entry.value?.quantity || ''),
        )
        return
    }

    if (forceQuantity) {
        existingAllocation.quantity = entry.value?.quantity || ''
    }
}

function ensureEntryReady() {
    form.type = 'IN'
    form.reason = 'PURCHASE'
    form.batch_allocation_method = 'MANUAL'
    form.manual_batches = []

    if (!form.batches?.length) {
        addBatch()
    }

    if (entry.value) {
        entry.value.received_at = today.value
        entry.value.lot_number = entry.value.lot_number || ''

        if (
            entry.value.expiration_date &&
            entry.value.expiration_date <= today.value
        ) {
            entry.value.expiration_date = ''
        }
    }

    syncQuantity()
    ensureCurrentBranchAllocation(!branchDistributionEnabled.value)
}

function syncQuantity() {
    if (!entry.value) return

    form.quantity = entry.value.quantity || ''

    if (!branchDistributionEnabled.value) {
        ensureCurrentBranchAllocation(true)
    }
}

function branchSelected(branchId) {
    return Boolean(findAllocation(branchId))
}

function isCurrentBranch(branchId) {
    return Number(branchId) === Number(currentBranchId.value)
}

function toggleBranch(branch) {
    if (!branch?.id || isCurrentBranch(branch.id)) return

    const allocationIndex = form.branch_allocations.findIndex((allocation) => {
        return Number(allocation.branch_id) === Number(branch.id)
    })

    if (allocationIndex >= 0) {
        form.branch_allocations.splice(allocationIndex, 1)
    } else {
        form.branch_allocations.push(buildAllocation(branch.id, ''))
    }

    ensureCurrentBranchAllocation(!branchDistributionEnabled.value)
    frontendErrors.branch_allocations = ''
}

function updateBranchAllocation(branchId, value) {
    const allocation = findAllocation(branchId)
    if (!allocation) return

    allocation.quantity = value
    frontendErrors.branch_allocations = ''
}

function allocationPayload() {
    if (!branchDistributionEnabled.value) {
        return []
    }

    return form.branch_allocations
        .map((allocation) => ({
            branch_id: Number(allocation.branch_id),
            quantity: Number(allocation.quantity || 0),
        }))
        .filter((allocation) => allocation.quantity > 0)
}

function validateBranchDistribution() {
    frontendErrors.branch_allocations = ''

    if (!branchDistributionEnabled.value) {
        return true
    }

    const allocations = allocationPayload()

    if (allocations.length !== selectedBranches.value.length) {
        frontendErrors.branch_allocations =
            'Define cuantas piezas recibe cada sucursal seleccionada.'
        return false
    }

    if (totalAllocated.value !== totalQuantity.value) {
        frontendErrors.branch_allocations =
            'La suma por sucursal debe coincidir con la cantidad total.'
        return false
    }

    return true
}

function validateEntry() {
    syncQuantity()

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

    if (entry.value?.received_at !== today.value) {
        frontendErrors.received_at = 'La fecha de entrada debe ser el dia de hoy.'
    }

    if (!entry.value?.expiration_date) {
        frontendErrors.expiration_date = 'La caducidad es obligatoria.'
    }

    if (
        entry.value?.expiration_date &&
        entry.value.expiration_date <= entry.value.received_at
    ) {
        frontendErrors.expiration_date = 'La caducidad debe ser mayor a la fecha de entrada.'
    }

    if (!entry.value?.lot_number?.trim()) {
        frontendErrors.lot_number = 'El numero de lote es obligatorio.'
    }

    const validDistribution = validateBranchDistribution()

    return !frontendErrors.quantity
        && !frontendErrors.received_at
        && !frontendErrors.expiration_date
        && !frontendErrors.lot_number
        && !frontendErrors.batches
        && validDistribution
}

function saveEntry() {
    syncQuantity()
    normalizeLotNumber()

    if (!validateEntry()) return

    const payloadAllocations = allocationPayload()

    saveAdjustment((data) => ({
        ...data,
        branch_allocations: payloadAllocations,
    }))
}

function closeModal() {
    if (form.processing) return

    emit('close')
}

watch(
    () => entry.value?.quantity,
    () => syncQuantity(),
)

watch(
    entry,
    () => ensureEntryReady(),
    { immediate: true },
)

onMounted(() => {
    ensureEntryReady()
})
</script>

<template>
    <GlobalModal
        v-bind="modalConfig"
        @save="saveEntry"
        @close="closeModal"
    >
        <section
            v-if="entry"
            class="min-h-0 w-full"
        >
            <div class="space-y-5 p-5">
                <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.1fr)_minmax(300px,0.9fr)_minmax(250px,0.85fr)]">
                    <FormPanel
                        title="Datos del producto"
                        description="Captura la cantidad y los datos principales del lote."
                        panel-class="border-y shadow-none"
                        body-class="space-y-4"
                    >
                        <div class="grid grid-cols-1 gap-4">
                            <InputField
                                v-model="entry.quantity"
                                :label="`Cantidad total (${unit})`"
                                type="number"
                                field="quantity"
                                :readonly="form.processing"
                                @input="syncQuantity"
                                @blur="validateEntry"
                            />

                            <InputField
                                v-model="entry.lot_number"
                                label="Numero de lote"
                                placeholder="Ej. AIHK-342"
                                field="lot_number"
                                :readonly="form.processing"
                                @update:model-value="normalizeLotNumber"
                                @blur="validateEntry"
                            />

                            <InputField
                                v-model="entry.expiration_date"
                                label="Caducidad"
                                type="date"
                                field="expiration_date"
                                :readonly="form.processing"
                                :min="minExpirationDate"
                                @blur="validateEntry"
                            />

                            <InputField
                                v-model="entry.supplier"
                                label="Proveedor"
                                placeholder="Opcional"
                                field="supplier"
                                :readonly="form.processing"
                            />
                        </div>
                    </FormPanel>

                    <FormPanel
                        title="Distribucion por sucursal"
                        description="Selecciona las sucursales y define sus cantidades."
                        panel-class="border-y shadow-none"
                    >
                        <div class="max-h-[320px] space-y-2 overflow-y-auto overscroll-contain pr-2">
                            <div
                                v-for="branch in branchOptions"
                                :key="branch.id"
                                class="grid grid-cols-[minmax(0,1fr)_88px] items-center gap-2"
                            >
                                <SelectionCheckboxCard
                                    :checked="branchSelected(branch.id)"
                                    :disabled="isCurrentBranch(branch.id) || form.processing"
                                    :title="branch.name"
                                    :description="isCurrentBranch(branch.id) ? 'Sucursal actual' : 'Incluir en la entrada'"
                                    :badge="isCurrentBranch(branch.id) ? 'Fija' : ''"
                                    :highlighted="isCurrentBranch(branch.id)"
                                    variant="solid"
                                    :compact="true"
                                    @toggle="toggleBranch(branch)"
                                />

                                <div>
                                    <input
                                        :value="findAllocation(branch.id)?.quantity ?? ''"
                                        type="number"
                                        min="0"
                                        step="0.001"
                                        :aria-label="`Cantidad para ${branch.name}`"
                                        class="w-full rounded-lg border border-secondary bg-background px-2.5 py-2 text-right text-sm text-text outline-none transition focus:border-primary focus:ring-primary disabled:cursor-not-allowed disabled:bg-secondary"
                                        :disabled="form.processing || !branchSelected(branch.id)"
                                        @input="updateBranchAllocation(branch.id, $event.target.value)"
                                        @blur="validateEntry"
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
                            <span class="rounded-full border border-secondary bg-background px-3 py-1 text-text opacity-80">
                                Asignado: {{ totalAllocated }} / {{ totalQuantity || 0 }} {{ unit }}
                            </span>

                            <span
                                class="rounded-full border px-3 py-1"
                                :class="remainingQuantity === 0
                                    ? 'border-accent bg-secondary text-accent'
                                    : 'border-primary bg-secondary text-primary'"
                            >
                                Pendiente: {{ remainingQuantity }} {{ unit }}
                            </span>
                        </div>
                    </FormPanel>

                    <FormPanel
                        title="Notas"
                        description="Agrega cualquier detalle adicional de esta entrada."
                        panel-class="border-y shadow-none"
                    >
                        <TextareaField
                            v-model="form.notes"
                            label="Notas"
                            placeholder="Opcional"
                            field="notes"
                            :readonly="form.processing"
                            :rows="9"
                            :max-height="280"
                        />
                    </FormPanel>
                </div>

                <div class="hidden">
                    <InputField
                        v-model="entry.received_at"
                        label="Fecha de entrada"
                        type="date"
                        field="received_at"
                        :readonly="true"
                        :min="today"
                        :max="today"
                        @blur="validateEntry"
                    />
                </div>

                <div
                    v-if="frontendErrors.quantity || frontendErrors.received_at || frontendErrors.expiration_date || frontendErrors.lot_number || frontendErrors.batches || frontendErrors.branch_allocations"
                    class="rounded-2xl border border-primary bg-secondary px-4 py-3 space-y-1"
                >
                    <p
                        v-if="frontendErrors.quantity"
                        class="text-sm font-semibold text-primary"
                    >
                        {{ frontendErrors.quantity }}
                    </p>

                    <p
                        v-if="frontendErrors.received_at"
                        class="text-sm font-semibold text-primary"
                    >
                        {{ frontendErrors.received_at }}
                    </p>

                    <p
                        v-if="frontendErrors.expiration_date"
                        class="text-sm font-semibold text-primary"
                    >
                        {{ frontendErrors.expiration_date }}
                    </p>

                    <p
                        v-if="frontendErrors.lot_number"
                        class="text-sm font-semibold text-primary"
                    >
                        {{ frontendErrors.lot_number }}
                    </p>

                    <p
                        v-if="frontendErrors.branch_allocations"
                        class="text-sm font-semibold text-primary"
                    >
                        {{ frontendErrors.branch_allocations }}
                    </p>

                    <p
                        v-if="frontendErrors.batches"
                        class="text-sm font-semibold text-primary"
                    >
                        {{ frontendErrors.batches }}
                    </p>
                </div>
            </div>
        </section>
    </GlobalModal>
</template>
