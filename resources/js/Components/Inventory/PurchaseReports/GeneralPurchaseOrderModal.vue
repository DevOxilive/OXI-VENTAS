<script setup>
import { computed } from 'vue'

import PurchaseOrderModalLayout from '@/Components/Inventory/PurchaseReports/PurchaseOrderModalLayout.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import { formatInventoryQuantity, normalizeInventoryUnit } from '@/utils/quantityFormatter'

const props = defineProps({ order: { type: Object, required: true } })
defineEmits(['close'])

const branchNames = computed(() => [...new Set(
    (props.order.branches ?? []).map((branch) => branch.name).filter(Boolean),
)])
const totalRequested = computed(() => (props.order.items ?? []).reduce(
    (total, item) => total + Number(item.requested_quantity || 0),
    0,
))
const totalRequestedUnit = computed(() =>
    (props.order.items ?? []).some((item) => normalizeInventoryUnit(item.base_unit ?? item.inventory_unit ?? item.unit) === 'kg')
        ? 'kg'
        : 'pza',
)
const summary = computed(() => [
    { label: 'Orden general', value: props.order.folio },
    { label: 'Generada', value: dateTime(props.order.created_at) },
    { label: 'Estado', value: props.order.status_label },
    { label: 'Responsable', value: props.order.created_by?.name },
    { label: 'Productos solicitados', value: `${props.order.items?.length || 0} productos` },
    { label: 'Cantidad solicitada', value: `${quantity(totalRequested.value, totalRequestedUnit.value)} ${totalRequestedUnit.value === 'kg' ? 'kg' : 'piezas'}` },
    { label: 'Sucursales participantes', value: branchNames.value.join(', ') },
])

function itemUnit(item) {
    return normalizeInventoryUnit(item.base_unit ?? item.inventory_unit ?? item.unit ?? 'pza')
}

function unitLabel(item) {
    return itemUnit(item) === 'kg' ? 'kg' : 'pzas.'
}

function quantity(value, unit = 'pza') {
    return formatInventoryQuantity(value, unit)
}

function dateTime(value) {
    return value
        ? new Intl.DateTimeFormat('es-MX', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
        : 'Sin fecha'
}

function status(item) {
    return item.unavailable
        ? 'No encontrado'
        : (props.order.status === 'COMPLETED' ? 'Comprado' : 'Pendiente')
}
</script>

<template>
    <PurchaseOrderModalLayout
        :title="order.folio || 'Orden general de compra'"
        subtitle="Consulta los productos, las sucursales participantes y las piezas solicitadas."
        mode="view"
        :summary="summary"
        @close="$emit('close')"
    >
        <template #products>
            <div class="space-y-3">
                <div class="sticky top-0 z-10 hidden grid-cols-[minmax(0,1fr)_120px_150px] gap-3 border-b border-secondary bg-background px-3 py-2 text-[10px] font-black uppercase tracking-[0.1em] text-text opacity-55 xl:grid">
                    <span>Producto</span>
                    <span>Solicitadas</span>
                    <span>Estado</span>
                </div>

                <article
                    v-for="item in order.items"
                    :key="item.id"
                    class="grid min-w-0 gap-3 rounded-xl border border-secondary bg-background px-3 py-3 xl:grid-cols-[minmax(0,1fr)_120px_150px] xl:items-center"
                >
                    <div class="flex min-w-0 items-start gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-secondary">
                            <img
                                v-if="item.image_url"
                                :src="item.image_url"
                                :alt="item.product_name"
                                class="h-full w-full object-cover"
                            >
                            <span v-else class="material-symbols-outlined text-lg opacity-40">inventory_2</span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <p class="break-words text-sm font-black leading-snug text-text">{{ item.product_name }}</p>
                            <p class="mt-0.5 break-all text-xs text-text opacity-60">{{ item.product_code || 'Sin codigo' }}</p>
                            <div v-if="item.branch_breakdown?.length" class="mt-2 flex flex-wrap gap-1.5">
                                <span
                                    v-for="branch in item.branch_breakdown"
                                    :key="`${item.id}-${branch.branch_id}`"
                                    class="max-w-full rounded-full bg-secondary px-2 py-1 text-[10px] font-semibold leading-tight text-text"
                                >
                                    {{ branch.branch_name }}: {{ quantity(branch.requested_quantity, itemUnit(item)) }} {{ unitLabel(item) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 xl:block">
                        <span class="text-[10px] font-black uppercase tracking-[0.1em] text-text opacity-50 xl:hidden">Solicitadas</span>
                        <strong class="text-sm text-text">{{ quantity(item.requested_quantity, itemUnit(item)) }} {{ unitLabel(item) }}</strong>
                    </div>

                    <span class="w-fit rounded-full bg-secondary px-2 py-1 text-[11px] font-bold text-text">
                        {{ status(item) }}
                    </span>
                </article>

                <p v-if="!order.items?.length" class="py-8 text-center text-sm text-text opacity-60">
                    Esta orden no tiene productos.
                </p>
            </div>
        </template>

        <template #footer>
            <footer class="flex w-full justify-end border-t border-secondary bg-background p-3">
                <AppButton variant="secondary" @click="$emit('close')">Cerrar</AppButton>
            </footer>
        </template>
    </PurchaseOrderModalLayout>
</template>
