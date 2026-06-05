<script setup>
defineProps({
    recentMovements: {
        type: Array,
        default: () => [],
    },
    unit: {
        type: String,
        default: 'piezas',
    },
})

function movementTypeLabel(type) {
    return {
        IN: 'Entrada',
        OUT: 'Salida',
        ADJUSTMENT: 'Ajuste manual',
    }[type] || type
}

function movementReasonLabel(reason) {
    return {
        PURCHASE: 'Compra',
        SALE: 'Venta',
        DAMAGED: 'Producto dañado',
        EXPIRED: 'Producto caducado',
        INVENTORY_DIFFERENCE: 'Diferencia de inventario',
    }[reason] || reason
}
</script>

<template>
    <aside
        class="xl:col-span-3 flex flex-col min-h-0 border-t xl:border-t-0 xl:border-l border-slate-200 pt-5 xl:pt-0 xl:pl-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="font-bold text-base text-slate-900">
                    Movimientos recientes
                </h3>

                <p class="text-sm text-slate-500">
                    Últimos movimientos registrados para este producto
                </p>
            </div>
        </div>

        <div v-if="recentMovements.length" class="flex-1 min-h-0 overflow-y-auto space-y-3 pr-2">
            <div v-for="movement in recentMovements" :key="movement.id"
                class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-black px-2 py-1 rounded-full" :class="{
                        'bg-emerald-100 text-emerald-700': movement.type === 'IN',
                        'bg-red-100 text-red-700': movement.type === 'OUT',
                        'bg-blue-100 text-blue-700': movement.type === 'ADJUSTMENT',
                    }">
                        {{ movementTypeLabel(movement.type) }}
                    </span>

                    <div class="text-right">
                        <p class="text-xs text-slate-400">
                            #{{ movement.id }}
                        </p>

                        <p class="text-xs text-slate-400">
                            {{ movement.created_at }}
                        </p>
                    </div>
                </div>

                <p class="mt-3 text-sm font-black text-slate-900">
                    {{ movementReasonLabel(movement.reason) }}
                </p>

                <p class="text-xs text-slate-500 mt-1">
                    Movimiento {{ movementTypeLabel(movement.type).toLowerCase() }}
                    por {{ movement.quantity }} {{ unit }} </p>

                <p class="text-xs text-slate-500 mt-1">
                    Stock: {{ movement.previous_stock }} {{ unit }} → {{ movement.new_stock }} {{ unit }} </p>

                <div v-if="movement.batches?.length" class="mt-2 space-y-1">
                    <p v-for="batch in movement.batches" :key="batch.id" class="text-xs text-slate-500">
                        Lote:
                        {{ batch.product_batch?.lot_number || 'Sin lote' }}
                        · Cantidad:
                        {{ batch.quantity }} {{ unit }} </p>
                </div>

                <p class="text-xs text-slate-400 mt-2">
                    Responsable:
                    {{ movement.user?.name || 'Usuario no disponible' }}
                </p>
            </div>
        </div>

        <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
            <p class="text-sm font-semibold text-slate-600">
                Sin movimientos recientes
            </p>

            <p class="text-xs text-slate-400 mt-1">
                Cuando ajustes el stock, aparecerán aquí.
            </p>
        </div>
    </aside>
</template>
