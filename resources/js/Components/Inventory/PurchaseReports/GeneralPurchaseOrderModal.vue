<script setup>
import { computed } from 'vue'

import PurchaseOrderModalLayout from '@/Components/Inventory/PurchaseReports/PurchaseOrderModalLayout.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'

const props = defineProps({ order: { type: Object, required: true } })
defineEmits(['close'])
const summary = computed(() => [
    { label: 'Orden general', value: props.order.folio },
    { label: 'Fecha', value: date(props.order.completed_at || props.order.created_at) },
    { label: 'Productos solicitados', value: `${props.order.items?.length || 0} productos` },
    { label: 'Sucursales', value: `${new Set((props.order.branches ?? []).map((branch) => branch.id)).size} sucursales` },
])
function quantity(value) { return new Intl.NumberFormat('es-MX', { maximumFractionDigits: 2 }).format(Number(value || 0)) }
function date(value) { return value ? new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value)) : 'Sin fecha' }
function status(item) { return item.unavailable ? 'No encontrado' : (props.order.status === 'COMPLETED' ? 'Comprado' : 'Pendiente') }
</script>

<template>
    <PurchaseOrderModalLayout
        :title="order.folio || 'Orden general de compra'"
        subtitle="Consulta los productos incluidos en la orden."
        mode="view"
        :summary="summary"
        @close="$emit('close')"
    >
        <template #products>
            <div class="space-y-2">
                <div class="sticky top-0 z-10 hidden grid-cols-[minmax(0,1fr)_120px_150px] gap-3 border-b border-secondary bg-background px-3 py-2 text-[10px] font-black uppercase tracking-[0.1em] text-text opacity-55 md:grid"><span>Producto</span><span>Solicitadas</span><span>Estado</span></div>
                <article v-for="item in order.items" :key="item.id" class="grid gap-3 rounded-xl border border-secondary bg-background px-3 py-3 md:grid-cols-[minmax(0,1fr)_120px_150px] md:items-center">
                    <div class="flex min-w-0 items-center gap-3"><div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-secondary"><img v-if="item.image_url" :src="item.image_url" :alt="item.product_name" class="h-full w-full object-cover"><span v-else class="material-symbols-outlined text-lg opacity-40">inventory_2</span></div><div class="min-w-0"><p class="truncate text-sm font-black text-text">{{ item.product_name }}</p><p class="truncate text-xs text-text opacity-60">{{ item.product_code || 'Sin código' }}</p></div></div>
                    <strong class="text-sm text-text">{{ quantity(item.requested_quantity) }} pzas.</strong>
                    <span class="w-fit rounded-full bg-secondary px-2 py-1 text-[11px] font-bold text-text">{{ status(item) }}</span>
                </article>
                <p v-if="!order.items?.length" class="py-8 text-center text-sm text-text opacity-60">Esta orden no tiene productos.</p>
            </div>
        </template>
        <template #footer><footer class="flex w-full justify-end border-t border-secondary bg-background p-3"><AppButton variant="secondary" @click="$emit('close')">Cerrar</AppButton></footer></template>
    </PurchaseOrderModalLayout>
</template>
