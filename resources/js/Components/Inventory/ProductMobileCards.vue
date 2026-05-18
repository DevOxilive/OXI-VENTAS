<script setup>
defineProps({
    filteredProducts: {
        type: Array,
        default: () => []
    }
})

defineEmits([
    'view',
    'edit',
    'adjust',
    'delete'
])

function statusClass(status) {
    return {
        Disponible: 'bg-green-100 text-green-700',
        'Stock bajo': 'bg-amber-100 text-amber-700',
        Agotado: 'bg-red-100 text-red-700',
        Inactivo: 'bg-slate-100 text-slate-700'
    }[status] || 'bg-slate-100 text-slate-700'
}

function formatCurrency(value) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN'
    }).format(Number(value || 0))
}
</script>

<template>
    <div class="md:hidden space-y-4">
        <div v-for="product in filteredProducts" :key="product.id"
            class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h3 class="font-bold text-gray-800 leading-tight">
                            {{ product.name }}
                        </h3>

                        <p class="text-xs text-gray-400 mt-1">
                            {{ product.code }}
                        </p>
                    </div>

                    <span
                        class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap"
                        :class="statusClass(product.status)">
                        {{ product.status }}
                    </span>
                </div>

                <div class="grid grid-cols-2 gap-3 mt-4 text-sm">
                    <div>
                        <p class="text-xs text-gray-400">Categoría</p>
                        <p class="font-medium text-gray-700 mt-1">
                            {{ product.category }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-400">Sucursal</p>
                        <p class="font-medium text-gray-700 mt-1">
                            {{ product.branch }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-400">Stock</p>
                        <p class="font-bold text-gray-800 mt-1">
                            {{ product.stock }}
                        </p>
                        <p class="text-[11px] text-gray-400">
                            Min: {{ product.minStock }}
                        </p>
                    </div>

                    <div>
                        <p class="text-xs text-gray-400">Precio</p>
                        <p class="font-bold text-gray-800 mt-1">
                            {{ formatCurrency(product.salePrice || product.price) }}
                        </p>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-gray-100">
                    <p class="text-xs text-gray-400">Caducidad</p>
                    <p class="text-sm text-gray-700 mt-1">
                        {{ product.expirationDate || 'No aplica' }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2 mt-4 pt-3 border-t border-gray-100">
                    <button type="button" @click="$emit('view', product)"
                        class="flex-1 min-w-[120px] bg-blue-50 text-blue-700 px-3 py-2 rounded-lg text-xs font-semibold flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">visibility</span>
                        Ver
                    </button>

                    <button type="button" @click="$emit('edit', product)"
                        class="flex-1 min-w-[120px] bg-yellow-50 text-yellow-700 px-3 py-2 rounded-lg text-xs font-semibold flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">edit</span>
                        Editar
                    </button>

                    <button type="button" @click="$emit('adjust', product)"
                        class="flex-1 min-w-[120px] bg-green-50 text-green-700 px-3 py-2 rounded-lg text-xs font-semibold flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">inventory</span>
                        Ajustar
                    </button>

                    <button type="button" @click="$emit('delete', product)"
                        class="flex-1 min-w-[120px] bg-red-50 text-red-700 px-3 py-2 rounded-lg text-xs font-semibold flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">delete</span>
                        Eliminar
                    </button>
                </div>
            </div>
        </div>

        <div v-if="!filteredProducts.length"
            class="bg-white border border-gray-200 rounded-2xl p-8 text-center text-gray-500">
            No se encontraron productos registrados.
        </div>
    </div>
</template>