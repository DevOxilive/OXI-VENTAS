<script setup>
import ActionIconButton from '@/Components/Forms/ActionIconButton.vue'

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
    <div class="hidden md:block bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full table-fixed text-sm">
                <thead class="bg-slate-50 text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="w-[13%] text-left px-4 py-3 font-semibold">
                            Código
                        </th>

                        <th class="w-[28%] text-left px-4 py-3 font-semibold">
                            Producto
                        </th>

                        <th class="w-[18%] text-left px-4 py-3 font-semibold">
                            Categoría
                        </th>

                        <th class="w-[15%] text-left px-4 py-3 font-semibold">
                            Stock
                        </th>

                        <th class="w-[13%] text-left px-4 py-3 font-semibold">
                            Estado
                        </th>

                        <th class="w-[13%] text-center px-4 py-3 font-semibold">
                            Acciones
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <tr v-for="product in filteredProducts" :key="product.id"
                        class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-4 text-slate-500 font-mono text-xs truncate">
                            {{ product.code }}
                        </td>

                        <td class="px-4 py-4">
                            <div class="font-semibold text-slate-800 truncate">
                                {{ product.name }}
                            </div>
                        </td>

                        <td class="px-4 py-4 text-slate-600 truncate">
                            {{ product.category_name ?? product.category ?? 'Sin categoría' }}
                        </td>

                        <td class="px-4 py-4">
                            <div class="font-semibold text-slate-800 truncate">
                                {{ product.stockLabel ?? product.stock }}
                            </div>

                            <div class="text-xs text-slate-400 truncate">
                                Mínimo: {{ product.minStockLabel ?? product.minStock }}
                            </div>
                        </td>

                        <td class="px-4 py-4">
                            <span
                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold whitespace-nowrap"
                                :class="statusClass(product.status)">
                                {{ product.status }}
                            </span>
                        </td>

                        <td class="px-4 py-4">
                            <div class="flex items-center justify-center gap-2">
                                <ActionIconButton icon="add" title="Entrada" variant="green"
                                    @click="$emit('entry', product)" />

                                <ActionIconButton icon="remove" title="Salida" variant="red"
                                    @click="$emit('exit', product)" />

                                <ActionIconButton icon="history" title="Historial" variant="slate"
                                    @click="$emit('movements', product)" />
                            </div>
                        </td>
                    </tr>

                    <tr v-if="!filteredProducts.length">
                        <td colspan="6" class="px-4 py-10 text-center text-slate-500">
                            No se encontraron productos.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>