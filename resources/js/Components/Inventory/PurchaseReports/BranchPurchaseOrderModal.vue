<script setup>
import { computed } from 'vue'
import { useForm } from '@inertiajs/vue3'

import PurchaseOrderModalLayout from '@/Components/Inventory/PurchaseReports/PurchaseOrderModalLayout.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import QuantityStepper from '@/Components/Forms/QuantityStepper.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales/useModalConfig'

const props = defineProps({ order: { type: Object, required: true }, mode: { type: String, default: 'view' }, branchId: { type: [Number, String], required: true } })
const emit = defineEmits(['close', 'completed'])
const isEditing = computed(() => props.mode === 'edit')
const form = useForm({
    items: (props.order.items ?? []).map((item) => ({ id: item.id, received_quantity: item.received_quantity ?? 0, receipt_notes: item.receipt_notes ?? '' })),
})
const itemLookup = computed(() => new Map((props.order.items ?? []).map((item) => [Number(item.id), item])))
const summary = computed(() => [
    { label: 'Orden', value: props.order.folio },
    { label: 'Fecha de solicitud', value: date(props.order.requested_at) },
    { label: 'Productos solicitados', value: `${props.order.items_count || props.order.items?.length || 0} productos` },
    { label: 'Solicitado por', value: props.order.user?.name || props.order.requested_by_name },
    { label: 'Sucursal', value: props.order.branch?.name || props.order.branch_name },
])

function sourceItem(item) { return itemLookup.value.get(Number(item.id)) ?? {} }
function quantity(value) { return new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 }).format(Number(value || 0)) }
function date(value) { return value ? new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value)) : 'Sin fecha' }
function status(item) {
    const received = Number(item.received_quantity || 0)
    const requested = Number(sourceItem(item).requested_quantity || 0)
    return received <= 0 ? 'No recibido' : (Math.abs(received - requested) < 0.001 ? 'Completo' : 'Incompleto')
}
function increase(item) { item.received_quantity = Number(item.received_quantity || 0) + 1 }
function decrease(item) { item.received_quantity = Math.max(0, Number(item.received_quantity || 0) - 1) }
async function completeReceipt() {
    if (form.processing) return
    const result = await confirmModalAction({ mode: 'update', entityName: 'orden de compra', title: 'Completar orden de compra', message: 'Se guardarán las piezas recibidas y la orden pasará a Completadas. ¿Deseas continuar?', confirmText: 'Completar orden' })
    if (!result.isConfirmed) return
    form.post(route('inventory.branches.purchase-reports.complete', { branch: props.branchId, purchaseReport: props.order.id }), getModalRequestOptions({ mode: 'update', entityName: 'Orden de compra', successTitle: 'Orden completada correctamente', errorTitle: 'No se pudo completar la orden', errorMessage: 'Revisa las piezas recibidas y las notas.', onSuccess: () => emit('completed') }))
}
</script>

<template>
    <PurchaseOrderModalLayout
        :title="order.folio || 'Orden de compra'"
        :subtitle="isEditing ? 'Confirma las piezas recibidas por la sucursal.' : 'Consulta los productos incluidos en la orden.'"
        :mode="mode"
        :processing="form.processing"
        :summary="summary"
        @close="emit('close')"
    >
        <template #products>
            <div class="space-y-2">
                <div class="sticky top-0 z-10 hidden grid-cols-[minmax(0,1fr)_110px_150px_100px] gap-3 border-b border-secondary bg-background px-3 py-2 text-[10px] font-black uppercase tracking-[0.1em] text-text opacity-55 md:grid">
                    <span>Producto</span><span>Solicitadas</span><span>{{ isEditing ? 'Recibidas' : 'Estado' }}</span><span v-if="isEditing">Notas</span>
                </div>
                <article v-for="item in (isEditing ? form.items : order.items)" :key="item.id" class="grid gap-3 rounded-xl border border-secondary bg-background px-3 py-3 md:grid-cols-[minmax(0,1fr)_110px_150px_100px] md:items-center">
                    <div class="flex min-w-0 items-center gap-3"><div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-secondary"><img v-if="item.image_url" :src="item.image_url" :alt="item.product_name" class="h-full w-full rounded-lg object-cover"><span v-else class="material-symbols-outlined text-lg opacity-40">inventory_2</span></div><div class="min-w-0"><p class="truncate text-sm font-black">{{ isEditing ? sourceItem(item).product_name : item.product_name }}</p><p class="truncate text-xs opacity-60">{{ (isEditing ? sourceItem(item).product_code : item.product_code) || 'Sin código' }}</p></div></div>
                    <strong class="text-sm">{{ quantity(isEditing ? sourceItem(item).requested_quantity : item.requested_quantity) }} pzas.</strong>
                    <QuantityStepper v-if="isEditing" :value="quantity(item.received_quantity)" :decrease-disabled="Number(item.received_quantity) <= 0" @decrease="decrease(item)" @increase="increase(item)" />
                    <span v-else class="w-fit rounded-full bg-secondary px-2 py-1 text-[11px] font-bold">{{ item.status_label }}</span>
                    <TextareaField v-if="isEditing" v-model="item.receipt_notes" :field="`receipt_notes_${item.id}`" placeholder="Notas" :rows="1" />
                </article>
                <p v-if="form.errors.items" class="rounded-xl border border-primary bg-secondary px-3 py-2 text-sm text-primary">{{ form.errors.items }}</p>
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
