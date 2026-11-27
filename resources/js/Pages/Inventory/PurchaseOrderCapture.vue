<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import FormPanel from '@/Components/Cards/FormPanel.vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import StockEntryModal from '@/Components/Inventory/BranchProducts/StockEntryModal.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import InputField from '@/Components/Forms/InputField.vue'
import QuantityStepper from '@/Components/Forms/QuantityStepper.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales/useModalConfig'
import { formatInventoryQuantity, normalizeInventoryUnit } from '@/utils/quantityFormatter'
import { localDateInput } from '@/utils/localDate'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    currentBranch: { type: Object, required: true },
    centralWarehouse: { type: Object, default: null },
    distributionBranches: { type: Array, default: () => [] },
    orderDB: { type: Object, required: true },
})
let batchSequence = 0

function presentationOptions(item) {
    if (normalizeInventoryUnit(item.base_unit) === 'kg') {
        return [
            { label: 'Kilogramo', value: 'Kilo' },
            { label: 'Gramo', value: 'Gramo' },
        ]
    }

    return [
        { label: 'Pieza', value: 'Pieza' },
        ...(item.has_package_presentation ? [{ label: 'Paquete', value: 'Paquete' }] : []),
        ...(item.has_box_presentation ? [{ label: 'Caja', value: 'Caja' }] : []),
    ]
}
const form = reactive({
    purchased_at: props.orderDB.purchased_at || localDateInput(),
    items: (props.orderDB.items || []).map((item) => ({
        ...item,
        physically_reviewed: Boolean(item.physically_reviewed),
        branch_allocations: item.branch_allocations || [],
        manual_distribution: Boolean(item.branch_allocations?.length),
        purchase_presentation: normalizeInventoryUnit(item.base_unit) === 'kg'
            ? (item.purchase_presentation === 'Gramo' ? 'Gramo' : 'Kilo')
            : (item.purchase_presentation === 'Caja' && item.has_box_presentation
                ? 'Caja'
                : (item.purchase_presentation === 'Paquete' && item.has_package_presentation ? 'Paquete' : 'Pieza')),
        package_quantity: number(item.package_quantity ?? item.requested_quantity),
        units_per_package: item.purchase_presentation === 'Caja'
            ? number(item.product_pieces_per_box ?? item.units_per_package) || 1
            : (item.purchase_presentation === 'Paquete'
                ? number(item.product_pieces_per_package ?? item.units_per_package) || 1
                : 1),
        purchase_price: number(item.purchase_price ?? item.previous_cost),
        promotion_status: item.purchase_notes ? 'YES' : 'NO',
        purchase_notes: item.purchase_notes || '',
        unavailable: Boolean(item.unavailable),
        image_failed: false,
        batches: (item.batches || []).map((batch) => ({
            ...batch,
            client_key: `batch_${item.id}_${++batchSequence}`,
            quantity: number(batch.quantity),
        })),
    })),
})
const completing = ref(false)
const editingItemId = ref(null)
function preloadProduct(item) {
    const assignedBranchIds = props.distributionBranches.map(branch => branch.id)

    return { id: item.product_id, name: item.product_name, inventory_unit: item.base_unit, has_box_presentation: item.has_box_presentation, has_package_presentation: item.has_package_presentation, pieces_per_box: item.product_pieces_per_box, pieces_per_package: item.product_pieces_per_package, assigned_branch_ids: assignedBranchIds }
}

function distributionBranchesFor(item) {
    const requestedByBranch = new Map((item.branch_breakdown || []).map((branch) => [
        Number(branch.branch_id),
        Number(branch.requested_quantity || 0),
    ]))

    return props.distributionBranches.map((branch) => ({
        id: branch.id,
        name: branch.name,
        is_central_warehouse: Boolean(branch.is_central_warehouse),
        requested_quantity: requestedByBranch.get(Number(branch.id)) || 0,
    }))
}
function preloadData(item) {
    return { notes: item.purchase_notes, lots: item.batches.map(batch => ({ ...batch, presentation: item.purchase_presentation === 'Caja' ? 'box' : item.purchase_presentation === 'Paquete' ? 'package' : 'piece', quantity: item.purchase_presentation === 'Gramo' ? Number(batch.quantity) / 1000 : batch.quantity, allocations: batch.allocations || [] })) }
}
function applyStockPreload(item, data) {
    item.purchase_presentation = normalizeInventoryUnit(item.base_unit) === 'kg' ? 'Kilo' : 'Pieza'
    item.units_per_package = 1
    item.batches = data.lots.map(lot => ({ ...lot, quantity: lot.base_quantity, client_key: `batch_${item.id}_${++batchSequence}` }))
    item.package_quantity = roundQuantity(item.batches.reduce((total, lot) => total + lot.quantity, 0))
    item.manual_distribution = true
    const allocations = new Map()
    for (const lot of data.lots) {
        for (const allocation of lot.allocations || []) {
            const branchId = Number(allocation.branch_id)
            allocations.set(branchId, roundQuantity((allocations.get(branchId) || 0) + number(allocation.quantity)))
        }
    }
    item.branch_allocations = [...allocations.entries()].map(([branch_id, quantity]) => ({ branch_id, quantity }))
    item.purchase_notes = data.notes
    closeCapture()
}
const editingDraft = ref(null)
const captureSections = [{ id: 1, label: 'Datos del lote' }, { id: 2, label: 'Distribución' }, { id: 3, label: 'Vista previa' }]
const captureSection = ref(1)
function openCapture(item) {
    editingDraft.value = JSON.parse(JSON.stringify(item))
    editingItemId.value = item.id
    captureSection.value = 1
}
function closeCapture() {
    editingItemId.value = null
    editingDraft.value = null
}
function applyCaptureDraft() {
    if (captureState(editingDraft.value) !== 'ready') return
    const target = form.items.find(item => item.id === editingItemId.value)
    Object.assign(target, JSON.parse(JSON.stringify(editingDraft.value)))
    closeCapture()
}
watch(() => editingDraft.value ? purchasedQuantity(editingDraft.value) : null, () => {
    if (editingDraft.value && !editingDraft.value.manual_distribution) distribute(editingDraft.value)
})
function captureState(item) {
    if (item.unavailable || !item.physically_reviewed) return 'neutral'
    if (!item.batches.length) return 'pending'
    const invalid = !Number.isFinite(Number(item.purchase_price)) || !Number.isFinite(Number(item.package_quantity))
        || number(item.purchase_price) <= 0 || number(item.package_quantity) <= 0
        || Math.abs(batchTotal(item) - number(item.package_quantity)) > 0.0009
        || Math.abs(distributionPending(item)) > 0.0009
        || item.branch_allocations.some(row => !Number.isFinite(Number(row.quantity)) || Number(row.quantity) < 0)
        || item.batches.some(batch => !Number.isFinite(Number(batch.quantity)) || number(batch.quantity) <= 0)
    return invalid ? 'invalid' : 'ready'
}
function cardTone(item) {
    return { neutral: '', pending: 'capture-pending', invalid: 'capture-invalid', ready: 'capture-ready' }[captureState(item)]
}
function distribute(item) {
    const scale = normalizeInventoryUnit(item.base_unit) === 'kg' ? 1000 : 1
    const total = Math.round(purchasedQuantity(item) * scale)
    const branches = item.branch_breakdown || []
    const requested = branches.reduce((sum, branch) => sum + number(branch.requested_quantity), 0)
    const shares = branches.map((branch) => {
        const exact = requested ? total * number(branch.requested_quantity) / requested : 0
        return { branch_id: branch.branch_id, quantity: Math.floor(exact), remainder: exact - Math.floor(exact) }
    })
    let remaining = total - shares.reduce((sum, row) => sum + row.quantity, 0)
    const ranked = [...shares].sort((a, b) => b.remainder - a.remainder || a.branch_id - b.branch_id)
    if (requested > 0) for (const row of ranked) { if (remaining-- > 0) row.quantity++ }
    item.branch_allocations = shares.map((row) => ({ branch_id: row.branch_id, quantity: row.quantity / scale }))
    item.manual_distribution = false
}
function distributionPending(item) {
    return roundQuantity(purchasedQuantity(item) - item.branch_allocations.reduce((sum, row) => sum + number(row.quantity), 0))
}
watch(() => form.items.map((item) => purchasedQuantity(item)), () => {
    for (const item of form.items) if (!item.manual_distribution || item.unavailable) distribute(item)
}, { immediate: true })
const purchaseSummary = computed(() => form.items.reduce((summary, item) => {
    if (item.unavailable) {
        summary.unavailable += 1
        return summary
    }

    summary.captured += 1
    const unit = normalizeInventoryUnit(item.base_unit)
    summary[unit === 'kg' ? 'kg' : 'pza'] += purchasedQuantity(item)
    return summary
}, { captured: 0, unavailable: 0, pza: 0, kg: 0 }))
const branchSummary = computed(() => {
    const branches = new Map()

    for (const branch of props.orderDB.branches || []) {
        const branchId = Number(branch.id)
        const current = branches.get(branchId) || {
            id: branchId,
            name: branch.name || 'Sucursal',
            folios: [],
            productIds: new Set(),
        }

        if (branch.folio && !current.folios.includes(branch.folio)) {
            current.folios.push(branch.folio)
        }

        branches.set(branchId, current)
    }

    for (const item of form.items) {
        for (const branch of item.branch_breakdown || []) {
            const branchId = Number(branch.branch_id)
            const current = branches.get(branchId) || {
                id: branchId,
                name: branch.branch_name || 'Sucursal',
                folios: [],
                productIds: new Set(),
            }

            current.productIds.add(Number(item.product_id || item.id))

            if (branch.order_folio && !current.folios.includes(branch.order_folio)) {
                current.folios.push(branch.order_folio)
            }

            branches.set(branchId, current)
        }
    }

    return [...branches.values()]
        .map((branch) => ({ ...branch, products_count: branch.productIds.size }))
        .sort((first, second) => first.name.localeCompare(second.name, 'es'))
})

const centralWarehouseProducts = computed(() => {
    const warehouseId = Number(props.centralWarehouse?.id)
    if (!warehouseId) return []

    return form.items.map((item) => {
        const allocation = item.branch_allocations.find((row) => Number(row.branch_id) === warehouseId)
        const quantity = number(allocation?.quantity)

        return quantity > 0 ? {
            id: item.id,
            name: item.product_name,
            quantity,
            unit: item.base_unit || 'pzas',
            lots: (item.batches || []).filter((batch) => number(
                (batch.allocations || []).find((row) => Number(row.branch_id) === warehouseId)?.quantity,
            ) > 0),
        } : null
    }).filter(Boolean)
})
const toolbarConfig = computed(() => ({
    title: `Compra Central · ${props.orderDB.folio}`,
    subtitle: `${form.items.length} productos de ${branchSummary.value.length} sucursales.`,
    backButton: true,
    backLabel: 'Seguimiento',
    showSearch: false,
    showRecordsPerPage: false,
    showCounter: false,
    filters: [],
    actions: [],
    tabs: [],
}))

function number(value) {
    return Number(value || 0)
}

function quantityInput(value, allowDecimal = false) {
    if (value === '') return ''

    const rawValue = String(value ?? '').replace(',', '.')
    const sanitized = allowDecimal
        ? rawValue.replace(/[^\d.]/g, '').replace(/(\..*)\./g, '$1')
        : rawValue.replace(/[^\d]/g, '')
    const quantity = Number(sanitized)

    return Number.isFinite(quantity) ? quantity : 0
}

function updateItemQuantity(item, field, value, minimum = null) {
    const resolvedMinimum = minimum ?? quantityMinimum(item, field)
    item[field] = value === ''
        ? ''
        : Math.max(resolvedMinimum, quantityInput(value, allowsDecimalQuantity(item, field)))
}

function increaseItemQuantity(item, field) {
    item[field] = roundQuantity(quantityInput(item[field], allowsDecimalQuantity(item, field)) + quantityStep(item, field))
}

function decreaseItemQuantity(item, field) {
    item[field] = roundQuantity(Math.max(quantityMinimum(item, field), quantityInput(item[field], allowsDecimalQuantity(item, field)) - quantityStep(item, field)))
}

function quantityStep(item, field = 'package_quantity') {
    if (field === 'package_quantity' && item.purchase_presentation === 'Gramo') return 50
    return field === 'package_quantity' && item.purchase_presentation === 'Kilo' ? 0.05 : 1
}

function quantityMinimum(item, field = 'package_quantity') {
    return field === 'package_quantity' && item.purchase_presentation === 'Kilo' ? 0.001 : 1
}

function roundQuantity(value) {
    return Math.round(number(value) * 1000) / 1000
}

function quantity(value, unit = 'pza') {
    return formatInventoryQuantity(value, unit)
}

function allowsDecimalQuantity(item, field = 'package_quantity') {
    return field === 'package_quantity' && item.purchase_presentation === 'Kilo'
}

function currency(value) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(number(value))
}

function requiresContainerContent(item) {
    return ['Caja', 'Paquete'].includes(item.purchase_presentation)
}

function presentationContent(item) {
    if (item.purchase_presentation === 'Caja') return number(item.product_pieces_per_box) || 1
    if (item.purchase_presentation === 'Paquete') return number(item.product_pieces_per_package) || 1
    return 1
}

function changePresentation(item) {
    item.units_per_package = presentationContent(item)
    item.package_quantity = Math.max(quantityMinimum(item), number(item.package_quantity) || quantityMinimum(item))
    item.batches = []
}

function batchTotal(item) {
    return roundQuantity((item.batches || []).reduce((total, batch) => total + number(batch.quantity), 0))
}

function batchPending(item) {
    return roundQuantity(Math.max(0, number(item.package_quantity) - batchTotal(item)))
}

function batchPresentationQuantity(value) {
    return new Intl.NumberFormat('es-MX', {
        maximumFractionDigits: 3,
    }).format(number(value))
}

function addBatch(item) {
    const pending = batchPending(item)
    if (pending <= 0) return
    item.batches.push({
        client_key: `batch_${item.id}_${++batchSequence}`,
        lot_number: '',
        expiration_date: '',
        quantity: pending > 0 ? pending : quantityMinimum(item),
    })
}

function removeBatch(item, index) {
    item.batches.splice(index, 1)
}

function updateBatchQuantity(item, batch, value) {
    batch.quantity = value === ''
        ? ''
        : Math.max(quantityMinimum(item), quantityInput(value, allowsDecimalQuantity(item)))
}

function increaseBatchQuantity(item, batch) {
    batch.quantity = roundQuantity(number(batch.quantity) + quantityStep(item))
}

function decreaseBatchQuantity(item, batch) {
    batch.quantity = roundQuantity(Math.max(quantityMinimum(item), number(batch.quantity) - quantityStep(item)))
}

function presentationName(item) {
    return item.purchase_presentation || 'Pieza'
}

function quantityLabel(item) {
    return {
        Caja: 'Cajas compradas',
        Kilo: 'Kilos comprados',
        Gramo: 'Gramos comprados',
        Costal: 'Costales comprados',
        Bolsa: 'Bolsas compradas',
        Paquete: 'Paquetes comprados',
        Pieza: 'Piezas compradas',
    }[presentationName(item)] || 'Cantidad comprada'
}

function quantitySuffix(item) {
    return {
        Caja: 'cajas',
        Kilo: 'kg',
        Gramo: 'g',
        Costal: 'costales',
        Bolsa: 'bolsas',
        Paquete: 'paquetes',
        Pieza: 'pzas.',
    }[presentationName(item)] || ''
}

function purchasedQuantity(item) {
    if (item.unavailable) return 0

    if (item.purchase_presentation === 'Gramo') return number(item.package_quantity) / 1000
    return number(item.package_quantity) * (requiresContainerContent(item) ? presentationContent(item) : 1)
}

function togglePromotion(item) {
    const enabled = item.promotion_status !== 'YES'

    item.promotion_status = enabled ? 'YES' : 'NO'

    if (!enabled) {
        item.purchase_notes = ''
    }
}

function payload() {
    return {
        purchased_at: form.purchased_at,
        record_version: props.orderDB.record_version || props.orderDB.updated_at || null,
        items: form.items.map((item) => ({
            id: item.id,
            physically_reviewed: item.physically_reviewed,
            branch_allocations: item.branch_allocations,
            purchase_presentation: presentationName(item),
            package_quantity: item.unavailable ? 0 : item.package_quantity,
            units_per_package: item.unavailable
                ? 0
                : (requiresContainerContent(item) ? presentationContent(item) : 1),
            purchase_price: item.unavailable ? 0 : item.purchase_price,
            purchase_notes: item.promotion_status === 'YES' ? item.purchase_notes : null,
            unavailable: item.unavailable,
            batches: item.unavailable ? [] : item.batches.map((batch) => ({
                lot_number: batch.lot_number || null,
                allocations: batch.allocations || [],
                supplier: batch.supplier || null,
                expiration_date: batch.expiration_date || null,
                quantity: batch.quantity,
            })),
        })),
    }
}

function routeParams() {
    return {
        branch: props.currentBranch.id,
        generalPurchaseOrder: props.orderDB.id,
    }
}

function backToTracking() {
    router.get(route('inventory.branches.reports.purchase-orders', {
        branch: props.currentBranch.id,
        status: 'PURCHASING',
    }))
}

function saveOrder() {
    router.put(
        route('inventory.branches.reports.purchase-orders.update', routeParams()),
        payload(),
        getModalRequestOptions({
            mode: 'update',
            entityName: 'Orden general',
            successTitle: 'Orden general actualizada correctamente',
            errorTitle: 'No se pudo actualizar la orden general',
            errorMessage: 'Revisa las cantidades y los precios de compra capturados.',
        }),
    )
}

async function completeOrder() {
    if (completing.value) return

    const result = await confirmModalAction({
        mode: 'update',
        entityName: 'orden general',
        title: 'Completar compra general',
        message: 'Se actualizará el último costo de cada producto encontrado y las Órdenes de compra pasarán a revisión.',
        confirmText: 'Completar compra',
    })

    if (!result.isConfirmed) return

    completing.value = true
    router.post(
        route('inventory.branches.reports.purchase-orders.complete', routeParams()),
        payload(),
        getModalRequestOptions({
            mode: 'update',
            entityName: 'Compra general',
            successTitle: 'Compra general completada correctamente',
            errorTitle: 'No se pudo completar la compra general',
            errorMessage: 'Revisa que cada producto tenga cantidad y precio de compra.',
            onFinish: () => {
                completing.value = false
            },
        }),
    )
}
</script>

<template>
    <Head :title="`Compra Central · ${orderDB.folio}`" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar v-bind="toolbarConfig" @back="backToTracking" />
        </template>

        <div class="grid min-w-0 gap-3 2xl:grid-cols-[minmax(0,1fr)_260px]">
            <FormPanel
                title="Productos"
                panel-class="min-w-0 shadow-none"
            >
                <div class="grid grid-cols-1 items-start gap-3 xl:grid-cols-2">
                    <article
                        v-for="item in form.items"
                        :key="item.id"
                        class="min-w-0 rounded-2xl border border-secondary bg-background p-4"
                        :class="[cardTone(item), item.unavailable ? 'opacity-65' : '']"
                    >
                        <section class="flex min-w-0 flex-col gap-2">
                            <div class="flex min-w-0 flex-1 gap-3">
                                <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-secondary bg-secondary">
                                    <img
                                        v-if="item.image_url && !item.image_failed"
                                        :src="item.image_url"
                                        :alt="item.product_name"
                                        class="h-full w-full object-cover"
                                        @error="item.image_failed = true"
                                    />
                                    <span v-else class="material-symbols-outlined text-2xl text-text opacity-35">inventory_2</span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-black text-text">{{ item.product_name }}</p>
                                    <p class="mt-0.5 truncate text-xs text-text opacity-50">{{ item.product_code || 'Sin código' }}</p>
                                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-text">
                                        <span><span class="opacity-55">Solicitado:</span> <strong>{{ quantity(item.requested_quantity, item.base_unit) }} {{ item.base_unit || 'pzas.' }}</strong></span>
                                        <span><span class="opacity-55">Costo anterior:</span> <strong class="ml-1 text-lg">{{ currency(item.previous_cost) }}</strong></span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex max-w-full flex-wrap gap-1.5">
                                    <span
                                        v-for="branch in item.branch_breakdown"
                                        :key="`${item.id}_${branch.order_id || branch.branch_id}`"
                                        class="rounded-full bg-secondary px-2.5 py-1 text-xs font-semibold text-text"
                                    >
                                        {{ branch.branch_name }} · {{ quantity(branch.requested_quantity, item.base_unit) }}
                                    </span>
                            </div>
                        </section>

                        <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-secondary pt-3">
                            <SelectionCheckboxCard compact variant="soft" :checked="item.unavailable" title="No encontrado" @toggle="item.unavailable = !item.unavailable" />
                            <SelectionCheckboxCard compact variant="soft" :checked="item.promotion_status === 'YES'" :disabled="item.unavailable" title="Descuento o promoción" @toggle="togglePromotion(item)" />
                            <SelectionCheckboxCard compact variant="soft" :checked="item.physically_reviewed" :disabled="item.unavailable" title="Listo" @toggle="item.physically_reviewed = !item.physically_reviewed" />
                            <AppButton variant="secondary" :disabled="!item.physically_reviewed || item.unavailable" @click="openCapture(item)">Editar</AppButton>
                            <span class="text-xs font-semibold">{{ { neutral: '', pending: 'Pendiente', invalid: 'Revisar cantidades', ready: 'Carga completa' }[captureState(item)] }}</span>
                        </div>
                        <StockEntryModal v-if="editingItemId === item.id" preload :product="preloadProduct(item)" :branches="distributionBranchesFor(item)" :initial-preload="preloadData(item)" @close="closeCapture" @preloaded="applyStockPreload(item, $event)" />
                    </article>
                </div>
            </FormPanel>

            <aside class="min-w-0 space-y-4 2xl:sticky 2xl:top-5 2xl:self-start">
                <FormPanel title="Sucursales participantes" panel-class="shadow-none">
                    <div class="space-y-2">
                        <div
                            v-for="branch in branchSummary"
                            :key="branch.id"
                            class="rounded-xl border border-secondary bg-secondary p-3 text-sm text-text"
                        >
                            <p class="font-bold">{{ branch.name }}</p>
                            <p class="mt-0.5 text-xs opacity-55">{{ branch.folios.join(', ') || 'Orden de compra incluida' }}</p>
                            <p class="mt-2 font-semibold">
                                {{ branch.products_count }} {{ branch.products_count === 1 ? 'producto' : 'productos' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 border-t border-secondary pt-4 text-sm text-text">
                        <div class="rounded-xl bg-secondary p-3">
                            <span class="block text-xs opacity-60">Capturados</span>
                            <strong class="mt-1 block text-xl">{{ purchaseSummary.captured }}/{{ form.items.length }}</strong>
                        </div>
                        <div class="rounded-xl bg-secondary p-3">
                            <span class="block text-xs opacity-60">No encontrados</span>
                            <strong class="mt-1 block text-xl">{{ purchaseSummary.unavailable }}</strong>
                        </div>
                        <div v-if="purchaseSummary.pza" class="rounded-xl bg-secondary p-3">
                            <span class="block text-xs opacity-60">Entrada en piezas</span>
                            <strong class="mt-1 block text-lg">{{ quantity(purchaseSummary.pza, 'pza') }}</strong>
                        </div>
                        <div v-if="purchaseSummary.kg" class="rounded-xl bg-secondary p-3">
                            <span class="block text-xs opacity-60">Entrada en kilos</span>
                            <strong class="mt-1 block text-lg">{{ quantity(purchaseSummary.kg, 'kg') }}</strong>
                        </div>
                    </div>
                </FormPanel>

                <FormPanel
                    v-if="centralWarehouseProducts.length"
                    title="Almacén central"
                    panel-class="shadow-none"
                >
                    <div class="max-h-56 space-y-2 overflow-y-auto pr-1">
                        <div
                            v-for="product in centralWarehouseProducts"
                            :key="product.id"
                            class="rounded-xl border border-secondary bg-secondary p-3 text-sm text-text"
                        >
                            <p class="font-bold">{{ product.name }}</p>
                            <p class="mt-1 text-xs font-semibold">{{ quantity(product.quantity, product.unit) }} {{ product.unit }}</p>
                            <p class="mt-1 text-xs opacity-60">
                                {{ product.lots.map((lot) => lot.lot_number || 'Sin lote').join(', ') || 'Sin lote' }}
                            </p>
                        </div>
                    </div>
                </FormPanel>

                <div class="grid gap-2">
                    <AppButton block variant="secondary" @click="saveOrder">Guardar avance</AppButton>
                    <AppButton block :disabled="completing" @click="completeOrder">
                        {{ completing ? 'Completando...' : 'Completar compra' }}
                    </AppButton>
                </div>
            </aside>
        </div>
    </PageLayout>
</template>

<style scoped>
.capture-pending { background-color: #fffbeb; border-color: #d4a017; }
.capture-invalid { background-color: #fff1f2; border-color: #e11d48; }
.capture-ready { background-color: #ecfdf5; border-color: #16a34a; }
</style>
