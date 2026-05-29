<script setup>
defineProps({
    notes: {
        type: String,
        default: '',
    },
    selectedProducts: {
        type: Array,
        default: () => [],
    },
    selectedCount: {
        type: Number,
        default: 0,
    },
    totalQuantity: {
        type: Number,
        default: 0,
    },
    estimatedTotal: {
        type: Number,
        default: 0,
    },
})

defineEmits([
    'update-notes',
    'update-item',
    'remove',
    'save',
])
</script>

<template>
    <aside class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:sticky xl:top-6 xl:self-start">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900">
                    Lista de compra
                </h2>
                <p class="text-sm text-slate-500">
                    {{ selectedCount }} productos seleccionados
                </p>
            </div>

            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                Borrador
            </span>
        </div>

        <textarea :value="notes" rows="3" placeholder="Notas generales del reporte..."
            class="mt-4 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-900 focus:ring-slate-900"
            @input="$emit('update-notes', $event.target.value)" />

        <div v-if="!selectedCount" class="mt-6 rounded-xl border border-dashed border-slate-300 p-6 text-center">
            <p class="text-sm font-medium text-slate-600">
                Todavía no hay productos seleccionados.
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Marca productos desde el inventario para agregarlos aquí.
            </p>
        </div>

        <div v-else class="mt-5 max-h-[460px] space-y-3 overflow-y-auto pr-1">
            <div v-for="item in selectedProducts" :key="item.branch_product_id"
                class="rounded-xl border border-slate-200 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-slate-900">
                            {{ item.name }}
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ item.code || 'Sin código' }} · {{ item.main_barcode || 'Sin barcode' }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500">
                            Stock: {{ item.stock }} · Mínimo: {{ item.min_stock }}
                        </p>
                    </div>

                    <button type="button"
                        class="rounded-lg px-2 py-1 text-sm font-bold text-red-500 hover:bg-red-50 hover:text-red-700"
                        @click="$emit('remove', item.branch_product_id)">
                        ×
                    </button>
                </div>

                <div class="mt-3 grid grid-cols-1 gap-3">
                    <input :value="item.requested_quantity" type="number" min="0" step="0.01"
                        placeholder="Cantidad manual"
                        class="rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-900 focus:ring-slate-900"
                        @input="$emit('update-item', item.branch_product_id, 'requested_quantity', $event.target.value)">

                    <input :value="item.notes" type="text" placeholder="Nota del producto..."
                        class="rounded-xl border border-slate-300 px-4 py-3 text-sm focus:border-slate-900 focus:ring-slate-900"
                        @input="$emit('update-item', item.branch_product_id, 'notes', $event.target.value)">
                </div>
            </div>
        </div>

        <div class="mt-5 rounded-xl bg-slate-50 p-4">
            <div class="flex justify-between text-sm">
                <span class="text-slate-500">Cantidad total</span>
                <span class="font-semibold text-slate-900">
                    {{ totalQuantity }}
                </span>
            </div>

            <div class="mt-2 flex justify-between text-sm">
                <span class="text-slate-500">Estimado</span>
                <span class="font-semibold text-slate-900">
                    ${{ Number(estimatedTotal).toFixed(2) }}
                </span>
            </div>
        </div>

        <button type="button" :disabled="!selectedCount"
            class="mt-5 w-full rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
            @click="$emit('save')">
            Guardar borrador
        </button>
    </aside>
</template>