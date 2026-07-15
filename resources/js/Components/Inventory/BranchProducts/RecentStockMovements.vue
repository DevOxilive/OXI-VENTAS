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
        class="xl:col-span-3 flex min-h-0 flex-col border-t border-secondary pt-5 xl:border-l xl:border-t-0 xl:pl-5 xl:pt-0">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-base font-bold text-text">
                    Movimientos recientes
                </h3>

                <p class="text-sm text-text opacity-70">
                    Últimos movimientos registrados para este producto
                </p>
            </div>
        </div>

        <div v-if="recentMovements.length" class="flex-1 min-h-0 overflow-y-auto space-y-3 pr-2">
            <div v-for="movement in recentMovements" :key="movement.id"
                class="rounded-2xl border border-secondary bg-secondary p-4">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-black px-2 py-1 rounded-full" :class="{
                        'bg-secondary text-accent': movement.type === 'IN',
                        'bg-secondary text-primary': movement.type === 'OUT',
                        'bg-secondary text-text': movement.type === 'ADJUSTMENT',
                    }">
                        {{ movementTypeLabel(movement.type) }}
                    </span>

                    <div class="text-right">
                        <p class="text-xs text-text opacity-50">
                            #{{ movement.id }}
                        </p>

                        <p class="text-xs text-text opacity-50">
                            {{ movement.created_at }}
                        </p>
                    </div>
                </div>

                <p class="mt-3 text-sm font-black text-text">
                    {{ movementReasonLabel(movement.reason) }}
                </p>

                <p class="mt-1 text-xs text-text opacity-70">
                    Movimiento {{ movementTypeLabel(movement.type).toLowerCase() }}
                    por {{ movement.quantity }} {{ unit }} </p>

                <p class="mt-1 text-xs text-text opacity-70">
                    Stock: {{ movement.previous_stock }} {{ unit }} → {{ movement.new_stock }} {{ unit }} </p>

                <div v-if="movement.batches?.length" class="mt-2 space-y-1">
                    <p v-for="batch in movement.batches" :key="batch.id" class="text-xs text-text opacity-70">
                        Lote:
                        {{ batch.product_batch?.lot_number || 'Sin lote' }}
                        · Cantidad:
                        {{ batch.quantity }} {{ unit }} </p>
                </div>

                <p class="mt-2 text-xs text-text opacity-50">
                    Responsable:
                    {{ movement.user?.name || 'Usuario no disponible' }}
                </p>
            </div>
        </div>

        <div v-else class="rounded-2xl border border-dashed border-secondary bg-secondary p-6 text-center">
            <p class="text-sm font-semibold text-text">
                Sin movimientos recientes
            </p>

            <p class="mt-1 text-xs text-text opacity-50">
                Cuando ajustes el stock, aparecerán aquí.
            </p>
        </div>
    </aside>
</template>
