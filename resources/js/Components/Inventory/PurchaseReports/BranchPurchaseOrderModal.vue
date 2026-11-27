<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

import PurchaseOrderModalLayout from '@/Components/Inventory/PurchaseReports/PurchaseOrderModalLayout.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import QuantityStepper from '@/Components/Forms/QuantityStepper.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales/useModalConfig'
import { formatInventoryQuantityWithUnit, normalizeInventoryUnit } from '@/utils/quantityFormatter'
import { formatOperationalDateTime } from '@/utils/operationalDateTime'

const props = defineProps({
    order: { type: Object, required: true },
    mode: { type: String, default: 'view' },
    branchId: { type: [Number, String], required: true },
})
const emit = defineEmits(['close', 'completed'])
const isEditing = computed(() => props.mode === 'edit')
const form = useForm({
    record_version: props.order.record_version || props.order.updated_at || null,
    items: (props.order.items ?? []).map((item) => ({
        id: item.id,
        received_quantity: item.received_quantity ?? 0,
        receipt_notes: item.receipt_notes ?? '',
        base_unit: item.base_unit ?? item.inventory_unit ?? item.unit ?? 'pza',
    })),
})
const itemLookup = computed(() => new Map((props.order.items ?? []).map((item) => [Number(item.id), item])))
const summary = computed(() => [
    { label: 'Orden', value: props.order.folio },
    { label: 'Estado', value: props.order.workflow_status_label || props.order.status_label || props.order.review_status_label },
    { label: 'Productos', value: `${props.order.items_count || props.order.items?.length || 0} productos` },
    { label: 'Solicitada', value: dateTime(props.order.requested_at) },
    { label: 'Solicitó', value: props.order.user?.name || props.order.requested_by_name },
    ...(props.order.general_order ? [{ label: 'Responsable de compra', value: props.order.general_order.created_by?.name }] : []),
    { label: 'Sucursal', value: props.order.branch?.name || props.order.branch_name },
])

function sourceItem(item) {
    return itemLookup.value.get(Number(item.id)) ?? {}
}

function itemUnit(item) {
    return normalizeInventoryUnit((isEditing.value ? sourceItem(item).base_unit ?? item.base_unit : item.base_unit) ?? item.inventory_unit ?? item.unit ?? 'pza')
}

function presentationFactor(item) {
    return ['Caja', 'Paquete'].includes(item.purchase_presentation)
        ? Math.max(1, Number(item.units_per_package || 1))
        : 1
}

function quantity(value, item = null) {
    if (!item) return formatInventoryQuantityWithUnit(value, 'pza')
    const presentation = item.purchase_presentation || (itemUnit(item) === 'kg' ? 'Kilo' : 'Pieza')
    const amount = Number(value || 0) / presentationFactor(item)
    const label = { Caja: 'cajas', Paquete: 'paquetes', Kilo: 'kg', Gramo: 'g', Pieza: 'pzas.' }[presentation] || 'pzas.'
    return `${new Intl.NumberFormat('es-MX', { maximumFractionDigits: itemUnit(item) === 'kg' ? 3 : 0 }).format(amount)} ${label}`
}

function dateTime(value) {
    return formatOperationalDateTime(value, { fallback: 'Sin fecha' })
}

function status(item) {
    const received = Number(item.received_quantity || 0)
    const requested = Number(sourceItem(item).requested_quantity || 0)

    return received <= 0 ? 'No recibido' : (Math.abs(received - requested) < 0.001 ? 'Completo' : 'Incompleto')
}

function increase(item) {
    item.received_quantity = Number(item.received_quantity || 0) + 1
}

function decrease(item) {
    item.received_quantity = Math.max(0, Number(item.received_quantity || 0) - 1)
}

function updateReceivedQuantity(item, value) {
    item.received_quantity = value === '' ? '' : Math.max(0, Number(value || 0))
}

async function completeReceipt() {
    if (form.processing) return
    const result = await confirmModalAction({
        mode: 'update',
        entityName: 'orden de compra',
        title: 'Completar orden de compra',
        message: 'Se guardaran las piezas recibidas y la orden pasara a Completadas. Deseas continuar?',
        confirmText: 'Completar orden',
    })
    if (!result.isConfirmed) return
    form.post(route('inventory.branches.purchase-reports.complete', { branch: props.branchId, purchaseReport: props.order.id }), getModalRequestOptions({
        mode: 'update',
        entityName: 'Orden de compra',
        successTitle: 'Orden completada correctamente',
        errorTitle: 'No se pudo completar la orden',
        errorMessage: 'Revisa las piezas recibidas y las notas.',
        onSuccess: () => emit('completed'),
    }))
}
</script>

<template>
    <PurchaseOrderModalLayout
        :title="order.folio || 'Orden de compra'"
        :subtitle="isEditing ? 'Recibir mercancía' : ''"
        :mode="mode"
        :processing="form.processing"
        :summary="summary"
        @close="emit('close')"
    >
        <template #products>
            <div class="space-y-2">
                <div
                    class="sticky top-0 z-10 hidden gap-3 border-b border-secondary bg-background px-2 py-2 text-[10px] font-black uppercase tracking-[0.1em] text-text opacity-55 md:grid"
                    :class="isEditing ? 'md:grid-cols-[minmax(0,1fr)_100px_140px_190px]' : 'md:grid-cols-[minmax(0,1fr)_100px_150px]'"
                >
                    <span>Producto</span>
                    <span>Solicitadas</span>
                    <span>{{ isEditing ? 'Recibidas' : 'Estado' }}</span>
                    <span v-if="isEditing">Notas</span>
                </div>

                <article
                    v-for="item in (isEditing ? form.items : order.items)"
                    :key="item.id"
                    class="grid min-w-0 grid-cols-2 gap-x-3 gap-y-2 rounded-xl border border-secondary bg-background px-3 py-2.5 md:items-center"
                    :class="isEditing ? 'md:grid-cols-[minmax(0,1fr)_100px_140px_190px]' : 'md:grid-cols-[minmax(0,1fr)_100px_150px]'"
                >
                    <div class="col-span-2 flex min-w-0 items-start gap-3 md:col-span-1">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-secondary">
                            <img
                                v-if="item.image_url"
                                :src="item.image_url"
                                :alt="item.product_name"
                                class="h-full w-full object-cover"
                            >
                            <span v-else class="material-symbols-outlined text-lg opacity-40">inventory_2</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="break-words text-sm font-black leading-snug text-text">{{ isEditing ? sourceItem(item).product_name : item.product_name }}</p>
                            <p class="break-all text-xs opacity-60">{{ (isEditing ? sourceItem(item).product_code : item.product_code) || 'Sin código' }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 md:block">
                        <span class="text-[10px] font-black uppercase tracking-[0.1em] text-text opacity-50 md:hidden">Solicitadas</span>
                        <strong class="text-sm">{{ quantity(isEditing ? sourceItem(item).requested_quantity : item.requested_quantity, item) }}</strong>
                    </div>

                    <QuantityStepper
                        v-if="isEditing"
                        data-modal-autofocus
                        :value="item.received_quantity"
                        :allow-decimal="itemUnit(item) === 'kg'"
                        :validation-field="itemUnit(item) === 'kg' ? 'kilogram_quantity' : 'product_piece_quantity'"
                        :aria-label="`Cantidad recibida de ${sourceItem(item).product_name || 'producto'}`"
                        :decrease-disabled="Number(item.received_quantity) <= 0"
                        @decrease="decrease(item)"
                        @increase="increase(item)"
                        @update="updateReceivedQuantity(item, $event)"
                    />
                    <div v-else class="min-w-0">
                        <span class="text-[10px] font-black uppercase tracking-[0.1em] text-text opacity-50 md:hidden">Recepción</span>
                        <span class="mt-1 block w-fit rounded-full bg-secondary px-2 py-1 text-[11px] font-bold md:mt-0">{{ item.status_label }}</span>
                    </div>

                    <TextareaField
                        v-if="isEditing"
                        v-model="item.receipt_notes"
                        :field="`receipt_notes_${item.id}`"
                        placeholder="Notas"
                        :rows="3"
                        class="min-h-20 resize-y"
                    />
                </article>

                <p v-if="form.errors.items" class="rounded-xl border border-primary bg-secondary px-3 py-2 text-sm text-primary">{{ form.errors.items }}</p>

                <section v-if="!isEditing && order.change_history?.length" class="rounded-xl border border-secondary bg-background p-3">
                    <h4 class="text-sm font-black text-text">Historial de cambios</h4>
                    <div class="mt-2 space-y-2">
                        <article v-for="change in order.change_history" :key="change.id" class="rounded-lg bg-secondary/50 px-3 py-2 text-sm">
                            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                                <strong>{{ change.user_name || 'Sistema' }}</strong>
                                <time class="text-xs opacity-60">{{ dateTime(change.occurred_at) }}</time>
                            </div>
                            <p class="mt-1 text-xs opacity-75">{{ change.summary }}</p>
                            <ul v-if="change.changes?.length" class="mt-1 list-disc space-y-0.5 pl-4 text-xs">
                                <li v-for="detail in change.changes" :key="detail">{{ detail }}</li>
                            </ul>
                        </article>
                    </div>
                </section>
            </div>
        </template>

        <template #footer>
            <footer class="flex w-full flex-col-reverse gap-2 border-t border-secondary bg-background p-3 sm:flex-row sm:justify-end">
                <AppButton variant="secondary" :disabled="form.processing" @click="emit('close')">{{ isEditing ? 'Cancelar' : 'Cerrar' }}</AppButton>
                <AppButton v-if="isEditing" :disabled="form.processing" @click="completeReceipt">{{ form.processing ? 'Guardando...' : 'Marcar como completada' }}</AppButton>
            </footer>
        </template>
    </PurchaseOrderModalLayout>
</template>
