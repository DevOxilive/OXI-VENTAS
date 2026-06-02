<script setup>
defineProps({
    filteredProducts: {
        type: Array,
        default: () => [],
    },
})

defineEmits([
    'view',
    'edit',
    'adjust',
    'delete',
])

function statusClass(status) {
    const classes = {
        Disponible: 'bg-green-100 text-green-700',
        'Stock bajo': 'bg-amber-100 text-amber-700',
        Agotado: 'bg-red-100 text-red-700',
    }

    return classes[status] || 'bg-slate-100 text-slate-700'
}
</script>

<template>
    <div class="md:hidden space-y-3 mt-4">
        <article v-for="product in filteredProducts" :key="product.id"
            class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm">
            <div class="flex justify-between items-start gap-3">
                <div>
                    <p class="font-semibold text-slate-800">
                        {{ product.name }}
                    </p>

                    <p class="text-sm text-slate-500">
                        {{ product.code }}
                    </p>
                </div>

                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold shrink-0"
                    :class="statusClass(product.status)">
                    {{ product.status }}
                </span>
            </div>

            <div class="mt-4 text-sm text-slate-500 space-y-1">
                <p>
                    <span class="font-medium text-slate-600">
                        Categoría:
                    </span>

                    {{ product.category }}
                </p>

                <p>
                    <span class="font-medium text-slate-600">
                        Stock:
                    </span>

                    {{ product.stockLabel ?? product.stock }} / min. {{ product.minStockLabel ?? product.minStock }}
                </p>

                <p>
                    <span class="font-medium text-slate-600">
                        Precio:
                    </span>

                    ${{ product.price }}
                </p>

                <p>
                    <span class="font-medium text-slate-600">
                        Caducidad:
                    </span>

                    {{ product.expirationDate || 'No aplica' }}
                </p>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" :title="product.tracksBatches
                    ? 'Movimiento con control por lotes'
                    : 'Movimiento simple de stock'"
                    class="group w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-white hover:bg-green-50 hover:border-green-200 transition-all duration-200"
                    @click="$emit('adjust', product)">
                    <span
                        class="material-symbols-outlined text-[20px] text-slate-500 group-hover:text-green-600 transition">
                        {{ product.tracksBatches ? 'inventory_2' : 'sync_alt' }}
                    </span>
                </button>

                <button type="button" title="Configurar producto"
                    class="group w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition-all duration-200"
                    @click="$emit('edit', product)">
                    <span class="material-symbols-outlined text-[20px] text-slate-500">
                        settings
                    </span>
                </button>
            </div>
        </article>

        <div v-if="!filteredProducts.length"
            class="bg-white border border-slate-200 rounded-2xl p-6 text-center text-slate-500 shadow-sm">
            No se encontraron productos registrados.
        </div>
    </div>
</template>
