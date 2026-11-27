<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

import PurchaseOrderModalLayout from '@/Components/Inventory/PurchaseReports/PurchaseOrderModalLayout.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import QuantityStepper from '@/Components/Forms/QuantityStepper.vue'
import ActionIconButton from '@/Components/Forms/ActionIconButton.vue'
import { getModalRequestOptions } from '@/Components/Modales/useModalConfig'
import { formatInventoryQuantityWithUnit, normalizeInventoryUnit } from '@/utils/quantityFormatter'
import { formatOperationalDate } from '@/utils/operationalDateTime'

const props = defineProps({
    order: { type: Object, required: true },
    branchId: { type: [Number, String], required: true },
    updateUrl: { type: String, required: true },
})

const emit = defineEmits(['close', 'updated'])
const form = useForm({
    record_version: props.order.record_version || props.order.updated_at || null,
    items: (props.order.items ?? []).map((item) => ({
        branch_product_id: item.branch_product_id,
        requested_quantity: Number(item.requested_quantity ?? 1),
        product_name: item.product_name,
        product_code: item.product_code,
        base_unit: item.base_unit ?? item.inventory_unit ?? item.unit ?? 'pza',
        purchase_presentation: item.purchase_presentation ?? 'Pieza',
        units_per_package: Number(item.units_per_package ?? 1) || 1,
    })),
})

const modalConfig = computed(() => ({
    title: `Editar ${props.order.folio || 'orden de compra'}`,
    subtitle: '',
    mode: 'edit',
    size: '2xl',
    showFooter: false,
    processing: form.processing,
    closeOnBackdrop: !form.processing,
    closeOnEsc: !form.processing,
}))

const summary = computed(() => [
    { label: 'Orden', value: props.order.folio },
    { label: 'Fecha de solicitud', value: formatDate(props.order.requested_at) },
    { label: 'Productos solicitados', value: `${form.items.length} ${form.items.length === 1 ? 'producto' : 'productos'}` },
    { label: 'Sucursal', value: props.order.branch?.name },
    { label: 'Solicitado por', value: props.order.user?.name || props.order.requested_by_name },
])

function removeItem(index) {
    form.items.splice(index, 1)
}

function itemUnit(item) { return normalizeInventoryUnit(item.base_unit ?? item.inventory_unit ?? item.unit ?? 'pza') }
function presentationFactor(item) {
    return ['Caja', 'Paquete'].includes(item.purchase_presentation)
        ? Math.max(1, Number(item.units_per_package || 1))
        : 1
}
function presentationQuantity(item) { return Number(item.requested_quantity || 0) / presentationFactor(item) }
function presentationLabel(item) {
    return { Caja: 'cajas', Paquete: 'paquetes', Kilo: 'kg', Gramo: 'g', Pieza: 'pzas.' }[item.purchase_presentation] || (itemUnit(item) === 'kg' ? 'kg' : 'pzas.')
}
function quantity(value, item = null) {
    if (!item) return formatInventoryQuantityWithUnit(value, 'pza')
    const amount = presentationQuantity(item)
    const formatted = new Intl.NumberFormat('es-MX', { maximumFractionDigits: itemUnit(item) === 'kg' ? 3 : 0 }).format(amount)
    return `${formatted} ${presentationLabel(item)}`
}
function increase(item) { item.requested_quantity = Number(item.requested_quantity || 0) + presentationFactor(item) }
function decrease(item) {
    const next = Number(item.requested_quantity || 0) - presentationFactor(item)
    if (next >= presentationFactor(item)) item.requested_quantity = next
}
function updateQuantity(item, value) {
    if (value === '') {
        item.requested_quantity = ''
        return
    }
    const minimum = itemUnit(item) === 'kg' ? 0.001 : 1
    item.requested_quantity = Math.max(minimum, Number(value || minimum) * presentationFactor(item))
}

function formatDate(value) {
    if (!value) return 'Sin fecha'
    return formatOperationalDate(value)
}

function save() {
    if (form.processing) return

    form.put(
        props.updateUrl,
        getModalRequestOptions({
            mode: 'update',
            entityName: 'Orden de compra',
            successTitle: 'Orden de compra actualizada',
            errorTitle: 'No se pudo actualizar la orden',
            errorMessage: 'Revisa que quede al menos un producto y que todas las cantidades sean válidas.',
            onSuccess: () => emit('updated'),
        }),
    )
}
</script>

<template>
    <PurchaseOrderModalLayout
        :title="modalConfig.title"
        :subtitle="modalConfig.subtitle"
        mode="edit"
        :processing="form.processing"
        :summary="summary"
        @close="emit('close')"
    >
        <template #products>
            <div class="space-y-2">
                <div class="sticky top-0 z-10 hidden grid-cols-[minmax(0,1fr)_110px_150px_40px] gap-3 border-b border-secondary bg-background px-3 py-2 text-[10px] font-black uppercase tracking-[0.1em] text-text opacity-55 lg:grid">
                    <span>Producto</span><span>Solicitadas</span><span>Ajustar</span><span></span>
                </div>
            <article
                v-for="(item, index) in form.items"
                :key="item.branch_product_id"
                class="grid gap-3 rounded-xl border border-secondary bg-background px-3 py-3 lg:grid-cols-[minmax(0,1fr)_110px_150px_40px] lg:items-center"
            >
                <div class="min-w-0">
                    <p class="truncate text-sm font-black text-text">{{ item.product_name }}</p>
                    <p class="mt-1 truncate text-xs text-text opacity-60">{{ item.product_code || 'Sin código' }}</p>
                </div>

                <div class="text-sm text-text"><span class="lg:hidden text-xs opacity-55">Solicitadas: </span><strong>{{ quantity(item.requested_quantity, item) }}</strong></div>

                <div><span class="mb-1 block text-xs opacity-55 lg:hidden">Ajustar {{ presentationLabel(item) }}</span><QuantityStepper :data-modal-autofocus="index === 0 ? '' : null" :value="presentationQuantity(item)" :allow-decimal="itemUnit(item) === 'kg'" :validation-field="itemUnit(item) === 'kg' ? 'kilogram_quantity' : 'product_piece_quantity'" :aria-label="`Cantidad solicitada de ${item.product_name} en ${presentationLabel(item)}`" :decrease-disabled="Number(item.requested_quantity) <= presentationFactor(item)" @decrease="decrease(item)" @increase="increase(item)" @update="updateQuantity(item, $event)" /></div>

                <ActionIconButton
                    icon="delete"
                    title="Eliminar producto"
                    variant="red"
                    @click="removeItem(index)"
                />
            </article>

            <p v-if="!form.items.length" class="rounded-xl border border-primary bg-secondary px-4 py-3 text-sm text-text">
                La orden debe conservar al menos un producto.
            </p>
            <p v-if="form.errors.items" class="rounded-xl border border-primary bg-secondary px-4 py-3 text-sm text-primary">
                {{ form.errors.items }}
            </p>
            </div>
        </template>

        <template #footer>
            <footer class="flex w-full flex-col-reverse gap-2 border-t border-secondary bg-background p-3 sm:flex-row sm:justify-end">
                <AppButton variant="secondary" :disabled="form.processing" @click="emit('close')">Cancelar</AppButton>
                <AppButton :disabled="form.processing || !form.items.length" @click="save">
                    {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
                </AppButton>
            </footer>
        </template>
    </PurchaseOrderModalLayout>
</template>
