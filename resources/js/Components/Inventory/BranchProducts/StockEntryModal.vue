<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useAdjustStockForm } from '@/Composables/Inventory/useAdjustStockForm'
import FormPanel from '@/Components/Cards/FormPanel.vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import { getStockEntryModalConfig } from '@/config/ModalConfigs/stockEntryModalConfig'
import { localDateInput } from '@/utils/localDate'

import InputField from '@/Components/Forms/InputField.vue'
import QuantityStepper from '@/Components/Forms/QuantityStepper.vue'
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'

const emit = defineEmits(['close', 'saved', 'preloaded'])

const props = defineProps({
    preload: { type: Boolean, default: false },
    initialPreload: { type: Object, default: null },
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

const inventoryQuantityMode = computed(() => props.product?.inventory_quantity_mode ?? 'base')
const unit = computed(() => props.product?.inventory_unit ?? props.product?.unit ?? 'pza')
const isKilogramUnit = computed(() => ['kg', 'kilo', 'kilogramo'].includes(
    String(unit.value).trim().toLowerCase(),
))
const hasBoxPresentation = computed(() => Boolean(props.product?.has_box_presentation)
    && inventoryQuantityMode.value === 'base')
const hasPackagePresentation = computed(() => Boolean(props.product?.has_package_presentation)
    && inventoryQuantityMode.value === 'base')
const isBoxUnit = computed(() => hasBoxPresentation.value)
const isLegacyPresentation = computed(() => inventoryQuantityMode.value === 'legacy_presentation')
const piecesPerBox = computed(() => {
    const pieces = Number(props.product?.pieces_per_box ?? 1)

    return Number.isInteger(pieces) && pieces > 0 ? pieces : 1
})
const entryPresentation = ref(hasBoxPresentation.value ? 'box' : (hasPackagePresentation.value ? 'package' : 'piece'))
const distributionUnit = ref(hasBoxPresentation.value ? 'boxes' : 'pieces')
const entryQuantityLabel = computed(() => {
    if (entryPresentation.value === 'box') return 'cajas'
    if (entryPresentation.value === 'package') return 'paquetes'
    if (isKilogramUnit.value) return 'kilogramos'

    return 'piezas'
})
const distributionQuantityLabel = computed(() => {
    if (!isBoxUnit.value) return entryQuantityLabel.value

    return distributionUnit.value === 'boxes' ? 'cajas' : 'piezas'
})
const piecesPerPackage = computed(() => {
    const pieces = Number(props.product?.pieces_per_package ?? 1)
    return Number.isInteger(pieces) && pieces > 0 ? pieces : 1
})
const entryFactor = computed(() => entryPresentation.value === 'box'
    ? piecesPerBox.value
    : entryPresentation.value === 'package' ? piecesPerPackage.value : 1)
const quantityLimit = computed(() => {
    if (isKilogramUnit.value) return 999.999
    if (entryPresentation.value === 'piece') return 9999
    return Math.floor(100000 / entryFactor.value)
})
const quantityStep = computed(() => isKilogramUnit.value ? 0.001 : 1)

const activeBatch = ref(0)
const preloadLots = ref([])
const preloadError = ref('')
const preview = ref(false)
const previewLots = ref([])
const entry = computed(() => form.batches?.[0] ?? null)
function keepLot() {
    if (!props.preload || !entry.value) return
    preloadLots.value[activeBatch.value] = {
        ...entry.value,
        quantity: quantityNumber(entry.value.quantity),
        presentation: entryPresentation.value,
        allocations: form.branch_allocations.map(row => ({ branch_id: Number(row.branch_id), quantity: allocationQuantityInPieces(row.quantity) })),
    }
}
function selectLot(index) {
    keepLot()
    activeBatch.value = index
    const lot = preloadLots.value[index]
    entryPresentation.value = lot.presentation || 'piece'
    distributionUnit.value = 'pieces'
    form.branch_allocations = JSON.parse(JSON.stringify(lot.allocations || []))
    form.batches = [{ ...lot }]
    preview.value = false
}
function newLot() {
    keepLot()
    preloadLots.value.push({ quantity: '', lot_number: '', expiration_date: '', supplier: '', presentation: entryPresentation.value, allocations: [] })
    selectLot(preloadLots.value.length - 1)
    goToSection(1)
}
function removeLot(index) {
    keepLot()
    preloadLots.value.splice(index, 1)
    if (!preloadLots.value.length) preloadLots.value.push({ quantity: '', presentation: 'piece', allocations: [] })
    const lot = preloadLots.value[0]
    activeBatch.value = 0
    entryPresentation.value = lot.presentation || 'piece'
    distributionUnit.value = 'pieces'
    form.branch_allocations = JSON.parse(JSON.stringify(lot.allocations || []))
    form.batches = [{ ...lot }]
    preview.value = false
}
function preloadPayload() {
    keepLot()
    return preloadLots.value.map(lot => {
        const factor = lot.presentation === 'box' ? piecesPerBox.value : lot.presentation === 'package' ? piecesPerPackage.value : 1
        return { ...lot, base_quantity: Math.round(Number(lot.quantity) * factor * 1000) / 1000 }
    })
}

function preloadLotStock(lot) {
    const presentation = lot?.presentation ?? 'piece'
    const quantity = quantityNumber(lot?.quantity)
    const factor = presentation === 'box'
        ? piecesPerBox.value
        : presentation === 'package' ? piecesPerPackage.value : 1
    const stock = Math.round(quantity * factor * 1000) / 1000

    return `${stock || 0} ${isKilogramUnit.value ? 'kg' : 'pzas'}`
}

const today = computed(() => localDateInput())
const activeSection = ref(1)
const modalSections = [
    { id: 1, label: 'Datos del lote' },
    { id: 2, label: 'Distribución por sucursal' },
    { id: 3, label: 'Notas' },
]

const minExpirationDate = computed(() => {
    const date = new Date(today.value)
    date.setDate(date.getDate() + 1)

    return localDateInput(date)
})

const totalErrors = computed(() => errorSummary.value.length)

const modalConfig = computed(() => ({ ...getStockEntryModalConfig({
    totalErrors: totalErrors.value,
    processing: form.processing,
    productName: productName.value,
}), ...(props.preload ? {
    title: `Precarga - ${productName.value}`,
    subtitle: 'Captura los lotes y reparte su stock antes de enviarlo a cada sucursal.',
    size: '2xl',
    height: 'full',
    scrollMode: 'controlled',
    contentClass: 'p-0',
} : {}) }))

const branchOptions = computed(() => {
    const assignedBranchIds = new Set(
        (props.product?.assigned_branch_ids ?? []).map(Number),
    )

    return (props.branches ?? []).filter((branch) => {
        if (!branch?.id) return false

        return assignedBranchIds.has(Number(branch.id))
    })
})

const currentBranchId = computed(() => props.currentBranch?.id ?? null)

const selectedBranches = computed(() => {
    return branchOptions.value.filter((branch) => {
        return Boolean(findAllocation(branch.id))
    })
})

function quantityNumber(value) {
    const quantity = Number(String(value ?? '').replace(',', '.'))

    if (!Number.isFinite(quantity)) return 0

    return Math.min(quantityLimit.value, Math.max(0, Math.round(quantity * 1000) / 1000))
}

function storedQuantityNumber(value) {
    const quantity = Number(String(value ?? '').replace(',', '.'))

    if (!Number.isFinite(quantity)) return 0

    return Math.max(0, Math.round(quantity * 1000) / 1000)
}

function quantityText(value) {
    const rawValue = String(value ?? '').replace(',', '.')

    if (!isKilogramUnit.value) {
        return rawValue.replace(/[^\d]/g, '').slice(0, entryPresentation.value === 'piece' ? 4 : 6)
    }

    const cleanValue = rawValue.replace(/[^\d.]/g, '')
    const [integerPart = '', ...decimalParts] = cleanValue.split('.')
    const integer = integerPart.slice(0, 3)
    const decimal = decimalParts.join('').slice(0, 3)

    return cleanValue.includes('.')
        ? `${integer || '0'}.${decimal}`
        : integer
}

function formattedQuantity(value) {
    return String(Math.round(quantityNumber(value) * 1000) / 1000)
}

function entryQuantityInPieces(value = entry.value?.quantity) {
    const quantity = quantityNumber(value)

    return quantity * entryFactor.value
}

function allocationQuantityInPieces(value) {
    const quantity = quantityNumber(value)

    return isBoxUnit.value && distributionUnit.value === 'boxes'
        ? quantity * piecesPerBox.value
        : quantity
}

function allocationQuantityFromPieces(value) {
    if (!isBoxUnit.value || distributionUnit.value === 'pieces') {
        return String(storedQuantityNumber(value))
    }

    return String(storedQuantityNumber(value) / piecesPerBox.value)
}

const totalAllocatedInPieces = computed(() => {
    return form.branch_allocations.reduce((sum, allocation) => {
        return sum + allocationQuantityInPieces(allocation.quantity)
    }, 0)
})

const totalQuantity = computed(() => quantityNumber(entry.value?.quantity))
const totalPieces = computed(() => entryQuantityInPieces())
const totalDistributionQuantity = computed(() => allocationQuantityFromPieces(totalPieces.value))
const totalAllocatedDistributionQuantity = computed(() => allocationQuantityFromPieces(totalAllocatedInPieces.value))

const remainingQuantity = computed(() => {
    return Math.round((totalPieces.value - totalAllocatedInPieces.value) * 1000) / 1000
})
const remainingDistributionQuantity = computed(() => allocationQuantityFromPieces(remainingQuantity.value))

const branchDistributionEnabled = computed(() => {
    return selectedBranches.value.length > 1
})

function normalizeLotNumber(value = entry.value?.lot_number) {
    if (value === null || value === undefined) return

    entry.value.lot_number = value.toString().toUpperCase()
    form.clearErrors('batches.0.lot_number')
    frontendErrors.lot_number = ''
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
            buildAllocation(currentBranchId.value, totalDistributionQuantity.value || ''),
        )
        return
    }

    if (forceQuantity) {
        existingAllocation.quantity = totalDistributionQuantity.value || ''
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

    if (value === '') {
        allocation.quantity = ''
        return
    }

    allocation.quantity = formattedQuantity(Math.min(
        quantityNumber(value),
        allocationLimitInCurrentUnit(branchId),
    ))
}

function increaseBranchAllocation(branchId) {
    const allocation = findAllocation(branchId)
    if (!allocation) return

    allocation.quantity = formattedQuantity(Math.min(
        quantityNumber(allocation.quantity) + quantityStep.value,
        allocationLimitInCurrentUnit(branchId),
    ))
}

function decreaseBranchAllocation(branchId) {
    const allocation = findAllocation(branchId)
    if (!allocation) return

    allocation.quantity = formattedQuantity(Math.max(0, quantityNumber(allocation.quantity) - quantityStep.value))
}

function allocationLimitInPieces(branchId) {
    const currentQuantity = allocationQuantityInPieces(findAllocation(branchId)?.quantity)

    return Math.max(0, totalPieces.value - (totalAllocatedInPieces.value - currentQuantity))
}

function allocationLimitInCurrentUnit(branchId) {
    const limit = allocationLimitInPieces(branchId)

    if (isBoxUnit.value && distributionUnit.value === 'boxes') {
        return Math.floor(limit / piecesPerBox.value)
    }

    return limit
}

function requestedQuantityLabel(branch) {
    const quantity = storedQuantityNumber(branch?.requested_quantity)

    if (!quantity) return branch?.is_central_warehouse ? 'Almacén central' : 'Sin solicitud'

    return `Solicitó ${quantity} ${isKilogramUnit.value ? 'kg' : 'pzas'}`
}

function updateEntryQuantity(value) {
    if (!entry.value) return

    entry.value.quantity = value === '' ? '' : quantityText(value)
    syncQuantity()
}

function clearBatchFieldError(field) {
    frontendErrors[field] = ''
    form.clearErrors(`batches.0.${field}`)
}

function increaseEntryQuantity() {
    if (!entry.value) return

    entry.value.quantity = formattedQuantity(quantityNumber(entry.value.quantity) + quantityStep.value)
    syncQuantity()
}

function decreaseEntryQuantity() {
    if (!entry.value) return

    entry.value.quantity = formattedQuantity(Math.max(quantityStep.value, quantityNumber(entry.value.quantity) - quantityStep.value))
    syncQuantity()
}

function goToSection(section) {
    if (props.preload) keepLot()
    activeSection.value = Math.min(3, Math.max(1, Number(section) || 1))
}

function focusFirstErrorSection() {
    const errorKeys = [
        ...Object.entries(frontendErrors)
            .filter(([, message]) => Boolean(message))
            .map(([key]) => key),
        ...Object.keys(form.errors || {}),
    ]

    if (!errorKeys.length) return

    if (errorKeys.some((key) => [
        'quantity',
        'lot_number',
        'expiration_date',
        'received_at',
        'batches',
        'batches.0.lot_number',
        'batches.0.expiration_date',
        'batches.0.received_at',
    ].includes(key) || key.startsWith('batch_'))) {
        goToSection(1)
        return
    }

    if (errorKeys.some((key) => key.includes('branch_allocations'))) {
        goToSection(2)
    }
}

function allocationPayload() {
    if (!branchDistributionEnabled.value) {
        return []
    }

    return form.branch_allocations
        .map((allocation) => ({
            branch_id: Number(allocation.branch_id),
            quantity: allocationQuantityInPieces(allocation.quantity),
        }))
        .filter((allocation) => allocation.quantity > 0)
}

function setDistributionUnit(nextUnit) {
    if (!isBoxUnit.value || nextUnit === distributionUnit.value) return

    const currentUnit = distributionUnit.value

    form.branch_allocations.forEach((allocation) => {
        const currentQuantity = quantityNumber(allocation.quantity)
        const pieces = currentUnit === 'boxes'
            ? currentQuantity * piecesPerBox.value
            : currentQuantity

        distributionUnit.value = nextUnit
        allocation.quantity = allocationQuantityFromPieces(pieces)
        distributionUnit.value = currentUnit
    })

    distributionUnit.value = nextUnit
}

function saveEntry() {
    if (props.preload) {
        const lots = preloadPayload()
        const validIds = new Set(branchOptions.value.map(branch => Number(branch.id)))
        const invalid = lots.some(lot => !(lot.base_quantity > 0)
            || (lot.expiration_date && lot.expiration_date <= today.value)
            || lot.allocations.some(row => !validIds.has(row.branch_id) || !Number.isFinite(row.quantity) || row.quantity < 0 || (!isKilogramUnit.value && !Number.isInteger(row.quantity)))
            || Math.abs(lot.allocations.reduce((sum, row) => sum + row.quantity, 0) - lot.base_quantity) > 0.0009)
        if (invalid) { preloadError.value = 'Revisa las cantidades, la caducidad y el reparto de cada lote.'; return }
        preloadError.value = ''
        if (!preview.value) { previewLots.value = lots; preview.value = true; return }
        emit('preloaded', { lots, notes: form.notes })
        return
    }
    if (isLegacyPresentation.value) {
        form.setError('branch_product_id', 'Este producto conserva existencias históricas por conciliar antes de recibir nuevas piezas.')
        return
    }

    syncQuantity()
    normalizeLotNumber()

    const payloadAllocations = allocationPayload()

    saveAdjustment((data) => ({
        ...data,
        entry_presentation: entryPresentation.value,
        quantity: entryQuantityInPieces(data.quantity),
        batches: (data.batches ?? []).map((batch) => ({
            ...batch,
            quantity: entryQuantityInPieces(batch.quantity),
        })),
        branch_allocations: payloadAllocations,
    }), {
        onSuccess: () => {
            emit('saved', props.product)
            emit('close')
        },
    })
    focusFirstErrorSection()
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
    () => entry.value?.expiration_date,
    () => clearBatchFieldError('expiration_date'),
)

watch(
    () => entry.value?.received_at,
    () => clearBatchFieldError('received_at'),
)

watch(
    entry,
    () => ensureEntryReady(),
    { immediate: true },
)

onMounted(() => {
    activeSection.value = 1
    ensureEntryReady()
    if (props.preload) {
        preloadLots.value = JSON.parse(JSON.stringify(props.initialPreload?.lots || []))
        form.notes = props.initialPreload?.notes || ''
        if (!preloadLots.value.length) preloadLots.value.push({ quantity: '', presentation: 'piece', allocations: [] })
        const lot = preloadLots.value[0]
        entryPresentation.value = lot.presentation || 'piece'
        distributionUnit.value = 'pieces'
        form.branch_allocations = JSON.parse(JSON.stringify(lot.allocations || []))
        form.batches = [{ ...lot }]
    }
})
</script>

<template>
    <GlobalModal
        v-bind="modalConfig"
        :sections="modalSections"
        :active-section="activeSection"
        :show-footer="false"
        @select-section="goToSection"
        @save="saveEntry"
        @close="closeModal"
    >
        <section
            v-if="entry"
            class="min-h-0 w-full"
            :class="preload ? 'h-full' : ''"
        >
            <div :class="preload ? 'grid h-full min-h-0 grid-cols-1 xl:grid-cols-[260px_minmax(0,1fr)]' : ''">
                <div class="min-w-0 xl:order-2">
                    <div v-if="preload && preview" class="bg-background p-4 md:p-5 xl:p-6">
                        <div class="space-y-3">
                            <p v-if="preloadError" class="rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-700">{{ preloadError }}</p>
                            <div v-for="(lot, index) in previewLots" :key="index" class="rounded-xl border border-secondary bg-secondary/20 p-3 text-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <strong>Lote: {{ lot.lot_number || `Lote ${index + 1}` }}</strong>
                                    <span class="rounded-full bg-background px-2.5 py-1 text-xs font-semibold">Stock: {{ lot.base_quantity }} {{ isKilogramUnit ? 'kg' : 'pzas' }}</span>
                                </div>
                                <p class="mt-1 text-xs text-text opacity-70">{{ lot.expiration_date || 'Sin caducidad' }} · {{ lot.supplier || 'Sin proveedor' }}</p>
                                <p v-for="row in lot.allocations" :key="row.branch_id" class="mt-1 text-xs">{{ branchOptions.find(branch => Number(branch.id) === row.branch_id)?.name }}: {{ row.quantity }} {{ isKilogramUnit ? 'kg' : 'pzas' }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-show="!preview" class="bg-background p-4 md:p-5 xl:p-6">
                <FormPanel
                    v-show="activeSection === 1"
                    title="Datos del lote"
                    description="Captura la cantidad y los datos principales del lote."
                    panel-class="shadow-none"
                    body-class="space-y-4"
                >
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div class="lg:col-span-2">
                            <div v-if="hasBoxPresentation || hasPackagePresentation" class="mb-3 flex flex-wrap gap-2">
                                <button type="button" class="rounded-lg border px-3 py-1.5 text-xs font-semibold" :class="entryPresentation === 'piece' ? 'border-primary bg-primary text-white' : 'border-secondary'" @click="entryPresentation = 'piece'">Pieza</button>
                                <button v-if="hasPackagePresentation" type="button" class="rounded-lg border px-3 py-1.5 text-xs font-semibold" :class="entryPresentation === 'package' ? 'border-primary bg-primary text-white' : 'border-secondary'" @click="entryPresentation = 'package'">Paquete</button>
                                <button v-if="hasBoxPresentation" type="button" class="rounded-lg border px-3 py-1.5 text-xs font-semibold" :class="entryPresentation === 'box' ? 'border-primary bg-primary text-white' : 'border-secondary'" @click="entryPresentation = 'box'">Caja</button>
                            </div>
                            <label class="mb-1 block text-sm font-semibold text-text">
                                Cantidad total ({{ entryQuantityLabel }})
                            </label>
                            <QuantityStepper
                                data-modal-autofocus
                                :value="entry.quantity"
                                :aria-label="`Cantidad total en ${entryQuantityLabel}`"
                                :disabled="form.processing"
                                :allow-decimal="isKilogramUnit"
                                :validation-field="isKilogramUnit ? 'kilogram_quantity' : 'product_piece_quantity'"
                                :max-integer-digits="entryPresentation === 'piece' ? 4 : 6"
                                :max-decimal-digits="3"
                                :decrease-disabled="quantityNumber(entry.quantity) <= quantityStep"
                                @decrease="decreaseEntryQuantity"
                                @increase="increaseEntryQuantity"
                                @update="updateEntryQuantity"
                            />

                            <p
                                v-if="entryPresentation === 'box'"
                                class="mt-2 text-xs text-text opacity-70"
                            >
                                Cada caja contiene {{ piecesPerBox }} piezas. Esta entrada registrará {{ totalPieces }} piezas.
                            </p>
                            <p v-else-if="entryPresentation === 'package'" class="mt-2 text-xs text-text opacity-70">
                                Cada paquete contiene {{ piecesPerPackage }} piezas. Esta entrada registrará {{ totalPieces }} piezas.
                            </p>
                        </div>

                        <InputField
                            v-model="entry.lot_number"
                            label="Numero de lote"
                            placeholder="Ej. AIHK-342"
                            field="lot_number"
                            :readonly="form.processing"
                            :error="frontendErrors.lot_number || form.errors['batches.0.lot_number']"
                            @update:model-value="normalizeLotNumber"
                        />

                        <InputField
                            v-model="entry.expiration_date"
                            label="Caducidad"
                            type="date"
                            field="expiration_date"
                            :readonly="form.processing"
                            :min="minExpirationDate"
                            :error="frontendErrors.expiration_date || form.errors['batches.0.expiration_date']"
                        />

                        <InputField
                            v-model="entry.supplier"
                            class="lg:col-span-2"
                            label="Proveedor"
                            placeholder="Opcional"
                            field="supplier"
                            tabindex="-1"
                            :readonly="form.processing"
                        />
                    </div>
                </FormPanel>

                <FormPanel
                    v-show="activeSection === 2"
                    title="Distribucion por sucursal"
                    description="Selecciona las sucursales y define sus cantidades."
                    panel-class="shadow-none"
                >
                    <div
                        v-if="isBoxUnit"
                        class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <p class="text-xs font-semibold text-text">
                            Distribuir por
                        </p>

                        <div class="inline-flex w-full rounded-xl border border-secondary bg-background p-1 sm:w-auto">
                            <button
                                type="button"
                                class="flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition sm:flex-none"
                                :class="distributionUnit === 'boxes'
                                    ? 'bg-primary text-white'
                                    : 'text-text hover:text-primary'"
                                :disabled="form.processing"
                                @click="setDistributionUnit('boxes')"
                            >
                                Cajas
                            </button>
                            <button
                                type="button"
                                class="flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition sm:flex-none"
                                :class="distributionUnit === 'pieces'
                                    ? 'bg-primary text-white'
                                    : 'text-text hover:text-primary'"
                                :disabled="form.processing"
                                @click="setDistributionUnit('pieces')"
                            >
                                Piezas
                            </button>
                        </div>
                    </div>

                    <div class="max-h-[46dvh] space-y-3 overflow-y-auto overscroll-contain pr-1 sm:max-h-[360px] sm:pr-2">
                        <div
                            v-for="branch in branchOptions"
                            :key="branch.id"
                            class="grid grid-cols-1 gap-3 rounded-2xl border border-secondary bg-background p-3 sm:grid-cols-[minmax(0,1fr)_132px] sm:items-center"
                        >
                            <SelectionCheckboxCard
                                :data-modal-autofocus="activeSection === 2 && branch.id === branchOptions[0]?.id ? '' : null"
                                :checked="branchSelected(branch.id)"
                                :disabled="isCurrentBranch(branch.id) || form.processing"
                                :title="branch.name"
                                :description="requestedQuantityLabel(branch)"
                                :badge="isCurrentBranch(branch.id) ? 'Fija' : ''"
                                :highlighted="isCurrentBranch(branch.id)"
                                variant="solid"
                                :compact="true"
                                @toggle="toggleBranch(branch)"
                            />

                            <QuantityStepper
                                class="w-full sm:justify-self-end"
                                :value="findAllocation(branch.id)?.quantity ?? ''"
                                :aria-label="`Cantidad de ${distributionQuantityLabel} para ${branch.name}`"
                                :disabled="form.processing || !branchSelected(branch.id)"
                                :allow-decimal="isKilogramUnit"
                                :validation-field="isKilogramUnit ? 'kilogram_quantity' : 'product_piece_quantity'"
                                :max-integer-digits="3"
                                :max-decimal-digits="3"
                                :decrease-disabled="quantityNumber(findAllocation(branch.id)?.quantity) <= 0"
                                :increase-disabled="allocationQuantityInPieces(findAllocation(branch.id)?.quantity) >= allocationLimitInPieces(branch.id)"
                                @decrease="decreaseBranchAllocation(branch.id)"
                                @increase="increaseBranchAllocation(branch.id)"
                                @update="updateBranchAllocation(branch.id, $event)"
                            />
                        </div>
                    </div>

                    <div class="mt-4 grid gap-2 text-xs font-semibold sm:flex sm:flex-wrap sm:items-center">
                        <span class="rounded-full border border-secondary bg-background px-3 py-1.5 text-center text-text opacity-80">
                            Asignado: {{ totalAllocatedDistributionQuantity }} / {{ totalDistributionQuantity || 0 }} {{ distributionQuantityLabel }}
                        </span>

                        <span
                            class="rounded-full border px-3 py-1.5 text-center"
                            :class="remainingQuantity === 0
                                ? 'border-accent bg-secondary text-accent'
                                : 'border-primary bg-secondary text-primary'"
                        >
                            Pendiente: {{ remainingDistributionQuantity }} {{ distributionQuantityLabel }}
                        </span>
                    </div>
                </FormPanel>

                <FormPanel
                    v-show="activeSection === 3"
                    title="Notas"
                    description="Agrega cualquier detalle adicional de esta entrada."
                    panel-class="shadow-none"
                >
                    <TextareaField
                        v-model="form.notes"
                        label="Notas"
                        placeholder="Opcional"
                        field="notes"
                        :readonly="form.processing"
                        :rows="8"
                        :max-height="280"
                    />
                </FormPanel>

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
                </div>

                <aside
                    v-if="preload"
                    class="order-first flex min-h-0 items-center gap-3 border-b border-secondary bg-secondary/10 px-4 py-3 xl:order-1 xl:flex-col xl:items-stretch xl:gap-0 xl:border-b-0 xl:border-r xl:p-4"
                >
                    <div class="flex shrink-0 items-center gap-2 xl:mb-3 xl:justify-between">
                        <h3 class="hidden text-sm font-bold text-text xl:block">Lotes</h3>
                        <button
                            type="button"
                            class="rounded-lg border border-primary bg-primary px-2.5 py-1.5 text-xs font-semibold text-white transition hover:brightness-95"
                            :disabled="form.processing"
                            @click="newLot"
                        >
                            <span class="xl:hidden">+ Lote</span>
                            <span class="hidden xl:inline">+ Agregar</span>
                        </button>
                    </div>

                    <p v-if="preloadError" class="hidden rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-700 xl:mb-3 xl:block">{{ preloadError }}</p>

                    <div class="flex min-w-0 flex-1 gap-2 overflow-x-auto overscroll-x-contain pb-1 pr-1 xl:block xl:max-h-none xl:space-y-2 xl:overflow-y-auto xl:overflow-x-hidden">
                        <button
                            v-for="(lot, index) in preloadLots"
                            :key="index"
                            type="button"
                            class="min-w-[112px] shrink-0 rounded-lg border px-3 py-2.5 text-left text-xs transition xl:w-full xl:min-w-0 xl:rounded-xl xl:p-3"
                            :class="activeBatch === index ? 'border-primary bg-background ring-1 ring-primary/30' : 'border-secondary bg-background hover:border-primary/60'"
                            :disabled="form.processing"
                            @click="selectLot(index)"
                        >
                            <span class="block whitespace-nowrap font-semibold text-text xl:hidden">{{ lot.lot_number || `Lote ${index + 1}` }}</span>
                            <span class="hidden text-[11px] font-semibold uppercase tracking-wide text-text opacity-60 xl:block">Lote</span>
                            <strong class="mt-0.5 hidden truncate text-sm text-text xl:block">{{ lot.lot_number || `Lote ${index + 1}` }}</strong>
                            <span class="mt-2 hidden text-xs text-text opacity-75 xl:block">Stock: {{ preloadLotStock(lot) }}</span>
                        </button>
                    </div>

                    <button
                        type="button"
                        class="shrink-0 text-xs font-semibold text-primary transition hover:underline disabled:opacity-50 lg:mt-3 lg:self-start"
                        :disabled="form.processing || preloadLots.length <= 1"
                        @click="removeLot(activeBatch)"
                    >
                        <span class="xl:hidden">Eliminar</span>
                        <span class="hidden xl:inline">Eliminar lote seleccionado</span>
                    </button>
                </aside>
            </div>
        </section>

        <template #footer>
            <div class="flex items-center justify-between gap-3 border-t border-secondary bg-background px-4 py-4 md:px-8">
                <button
                    v-if="activeSection > 1"
                    type="button"
                    class="rounded-xl border border-secondary bg-background px-4 py-2.5 text-sm font-semibold text-text transition hover:border-primary hover:text-primary"
                    :disabled="form.processing"
                    :tabindex="preload ? -1 : 0"
                    @click="goToSection(activeSection - 1)"
                >
                    Volver
                </button>
                <span v-else />

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-xl border border-secondary bg-background px-4 py-2.5 text-sm font-semibold text-text transition hover:border-primary hover:text-primary"
                        :disabled="form.processing"
                        :tabindex="preload ? -1 : 0"
                        @click="closeModal"
                    >
                        Cancelar
                    </button>
                    <button
                        v-if="activeSection < 3"
                        type="button"
                        class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60"
                        :data-modal-autofocus="activeSection === 2 ? null : ''"
                        :disabled="form.processing"
                        @click="goToSection(activeSection + 1)"
                    >
                        Continuar
                    </button>
                    <button
                        v-else
                        type="button"
                        class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white transition hover:brightness-95 disabled:cursor-not-allowed disabled:opacity-60"
                        data-modal-autofocus
                        :disabled="form.processing"
                        @click="saveEntry"
                    >
                        {{ preload ? (preview ? 'Aplicar precarga' : 'Previsualizar') : (form.processing ? 'Registrando...' : 'Registrar entrada') }}
                    </button>
                </div>
            </div>
        </template>
    </GlobalModal>
</template>
