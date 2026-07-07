<script setup>
defineProps({
    products: {
        type: Array,
        default: () => [],
    },
    selectedItems: {
        type: Object,
        default: () => ({}),
    },
})

defineEmits(['toggle'])

function isSelected(selectedItems, productId) {
    return Boolean(selectedItems[productId])
}

function stockTone(product) {
    if (Number(product.stock) <= 0) {
        return 'bg-red-50 text-red-700 border-red-200'
    }

    if (Number(product.stock) <= Number(product.min_stock)) {
        return 'bg-amber-50 text-amber-700 border-amber-200'
    }

    return 'bg-emerald-50 text-emerald-700 border-emerald-200'
}
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
        <div class="border-b border-slate-200 px-4 py-3">
            <h2 class="text-sm font-bold text-slate-900">
                Productos disponibles
            </h2>
            <p class="text-xs text-slate-500">
                Marca los productos que se van a agregar a la lista de compra.
            </p>
        </div>

        <table class="hidden min-w-full divide-y divide-slate-200 md:table">
            <thead class="bg-slate-50">
                <tr>
                    <th class="w-12 px-4 py-3"></th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                        Producto
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                        Codigo / Barcode
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                        Categoria
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">
                        Stock
                    </th>
                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">
                        Minimo
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-slate-100 bg-white">
                <tr v-for="product in products" :key="product.id" class="transition hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <input type="checkbox" :checked="isSelected(selectedItems, product.id)"
                            class="rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                            @change="$emit('toggle', product)">
                    </td>

                    <td class="px-4 py-3">
                        <p class="text-sm font-semibold text-slate-900">
                            {{ product.name }}
                        </p>
                        <p class="text-xs text-slate-500">
                            ID inventario: {{ product.id }}
                        </p>
                    </td>

                    <td class="px-4 py-3">
                        <p class="text-sm font-medium text-slate-700">
                            {{ product.code || 'Sin codigo' }}
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ product.main_barcode || 'Sin barcode' }}
                        </p>
                    </td>

                    <td class="px-4 py-3">
                        <p class="text-sm text-slate-700">
                            {{ product.category }}
                        </p>
                    </td>

                    <td class="px-4 py-3 text-right">
                        <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold"
                            :class="stockTone(product)">
                            {{ product.stockLabel ?? product.stock }}
                        </span>
                    </td>

                    <td class="px-4 py-3 text-right text-sm font-semibold text-slate-600">
                        {{ product.minStockLabel ?? product.min_stock }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="divide-y divide-slate-100 md:hidden">
            <label v-for="product in products" :key="product.id" class="flex gap-3 p-4">
                <input type="checkbox" :checked="isSelected(selectedItems, product.id)"
                    class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-900"
                    @change="$emit('toggle', product)">

                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-900">
                                {{ product.name }}
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ product.category }}
                            </p>
                        </div>

                        <span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-bold"
                            :class="stockTone(product)">
                            {{ product.stockLabel ?? product.stock }}
                        </span>
                    </div>

                    <p class="mt-2 text-xs text-slate-500">
                        Codigo: {{ product.code || 'Sin codigo' }}
                    </p>

                    <p class="text-xs text-slate-500">
                        Barcode: {{ product.main_barcode || 'Sin barcode' }}
                    </p>

                    <p class="mt-2 text-sm text-slate-600">
                        Minimo: <b>{{ product.minStockLabel ?? product.min_stock }}</b>
                    </p>
                </div>
            </label>
        </div>

        <div v-if="!products.length" class="p-8 text-center">
            <p class="text-sm font-semibold text-slate-700">
                No se encontraron productos.
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Ajusta los filtros para ampliar la busqueda.
            </p>
        </div>
    </div>
</template>
