<script setup>
import { computed, onMounted, watch } from 'vue'
import { useAdjustStockForm } from '@/Composables/Inventory/useAdjustStockForm'
import FormPanel from '@/Components/Cards/FormPanel.vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import { getStockEntryModalConfig } from '@/config/ModalConfigs/stockEntryModalConfig'

import InputField from '@/Components/Forms/InputField.vue'
import QuantityStepper from '@/Components/Forms/QuantityStepper.vue'
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

function quantityNumber(value) {
    const quantity = Number(String(value ?? '').replace(/[^\d]/g, ''))

    return Number.isFinite(quantity) ? quantity : 0
}

const totalAllocated = computed(() => {
    return form.branch_allocations.reduce((sum, allocation) => {
        return sum + quantityNumber(allocation.quantity)
    }, 0)
})

const totalQuantity = computed(() => quantityNumber(entry.value?.quantity))

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
}

function updateBranchAllocation(branchId, value) {
    const allocation = findAllocation(branchId)
    if (!allocation) return

    allocation.quantity = value === '' ? '' : String(quantityNumber(value))
}

function increaseBranchAllocation(branchId) {
    const allocation = findAllocation(branchId)
    if (!allocation) return

    allocation.quantity = String(quantityNumber(allocation.quantity) + 1)
}

function decreaseBranchAllocation(branchId) {
    const allocation = findAllocation(branchId)
    if (!allocation) return

    allocation.quantity = String(Math.max(0, quantityNumber(allocation.quantity) - 1))
}

function updateEntryQuantity(value) {
    if (!entry.value) return

    entry.value.quantity = value === '' ? '' : String(quantityNumber(value))
    syncQuantity()
}

function increaseEntryQuantity() {
    if (!entry.value) return

    entry.value.quantity = String(quantityNumber(entry.value.quantity) + 1)
    syncQuantity()
}

function decreaseEntryQuantity() {
    if (!entry.value) return

    entry.value.quantity = String(Math.max(1, quantityNumber(entry.value.quantity) - 1))
    syncQuantity()
}

function allocationPayload() {
    if (!branchDistributionEnabled.value) {
        return []
    }

    return form.branch_allocations
        .map((allocation) => ({
            branch_id: Number(allocation.branch_id),
            quantity: quantityNumber(allocation.quantity),
        }))
        .filter((allocation) => allocation.quantity > 0)
}

function saveEntry() {
    syncQuantity()
    normalizeLotNumber()

    const payloadAllocations = allocationPayload()

    saveAdjustment((data) => ({
        ...data,
        branch_allocations: payloadAllocations,
    }), { skipValidation: true })
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
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-text">
                                    Cantidad total ({{ unit }})
                                </label>
                                <QuantityStepper
                                    :value="entry.quantity"
                                    :aria-label="`Cantidad total en ${unit}`"
                                    :disabled="form.processing"
                                    :decrease-disabled="quantityNumber(entry.quantity) <= 1"
                                    @decrease="decreaseEntryQuantity"
                                    @increase="increaseEntryQuantity"
                                    @update="updateEntryQuantity"
                                />
                            </div>

                            <InputField
                                v-model="entry.lot_number"
                                label="Numero de lote"
                                placeholder="Ej. AIHK-342"
                                field="lot_number"
                                :readonly="form.processing"
                                @update:model-value="normalizeLotNumber"
                            />

                            <InputField
                                v-model="entry.expiration_date"
                                label="Caducidad"
                                type="date"
                                field="expiration_date"
                                :readonly="form.processing"
                                :min="minExpirationDate"
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
                                class="grid grid-cols-[minmax(0,1fr)_132px] items-center gap-2"
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

                                <QuantityStepper
                                    class="justify-self-end"
                                    :value="findAllocation(branch.id)?.quantity ?? ''"
                                    :aria-label="`Cantidad para ${branch.name}`"
                                    :disabled="form.processing || !branchSelected(branch.id)"
                                    :decrease-disabled="quantityNumber(findAllocation(branch.id)?.quantity) <= 0"
                                    @decrease="decreaseBranchAllocation(branch.id)"
                                    @increase="increaseBranchAllocation(branch.id)"
                                    @update="updateBranchAllocation(branch.id, $event)"
                                />
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
                    />
                </div>
            </div>
        </section>
    </GlobalModal>
</template>
