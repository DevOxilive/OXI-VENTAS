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
    'entry',
    'exit',
    'movements',
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
                <div class="min-w-0">
                    <p class="font-semibold text-slate-800 truncate">
                        {{ product.name }}
                    </p>

                    <p class="text-xs text-slate-400 mt-0.5">
                        {{ product.code }}
                    </p>
                </div>

                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold shrink-0"
                    :class="statusClass(product.status)">
                    {{ product.status }}
                </span>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <p class="text-xs text-slate-400">
                        Categoría
                    </p>

                    <p class="font-medium text-slate-700 truncate">
                        {{ product.category || 'Sin categoría' }}
                    </p>
                </div>

                <div>
                    <p class="text-xs text-slate-400">
                        Stock
                    </p>

                    <p class="font-semibold text-slate-800">
                        {{ product.stockLabel ?? product.stock }}
                    </p>

                    <p class="text-xs text-slate-400">
                        Min. {{ product.minStockLabel ?? product.minStock }}
                    </p>
                </div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" title="Entrada"
                    class="group w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-white hover:bg-green-50 hover:border-green-200 transition-all duration-200"
                    @click="$emit('entry', product)">
                    <span
                        class="material-symbols-outlined text-[20px] text-slate-500 group-hover:text-green-600 transition">
                        add
                    </span>
                </button>

                <button type="button" title="Salida"
                    class="group w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-white hover:bg-red-50 hover:border-red-200 transition-all duration-200"
                    @click="$emit('exit', product)">
                    <span
                        class="material-symbols-outlined text-[20px] text-slate-500 group-hover:text-red-600 transition">
                        remove
                    </span>
                </button>

                <button type="button" title="Historial"
                    class="group w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 bg-white hover:bg-slate-50 hover:border-slate-300 transition-all duration-200"
                    @click="$emit('movements', product)">
                    <span
                        class="material-symbols-outlined text-[20px] text-slate-500 group-hover:text-slate-700 transition">
                        history
                    </span>
                </button>
            </div>
        </article>

        <div v-if="!filteredProducts.length"
            class="bg-white border border-slate-200 rounded-2xl p-6 text-center text-slate-500 shadow-sm">
            No se encontraron productos.
        </div>
    </div>
</template>