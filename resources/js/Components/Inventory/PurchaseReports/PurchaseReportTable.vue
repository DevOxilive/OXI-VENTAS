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
        return 'bg-secondary text-primary border-primary'
    }

    if (Number(product.stock) <= Number(product.min_stock)) {
        return 'bg-secondary text-accent border-accent'
    }

    return 'bg-secondary text-accent border-accent'
}
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-secondary bg-background">
        <div class="border-b border-secondary px-4 py-3">
            <h2 class="text-sm font-bold text-text">
                Productos disponibles
            </h2>
            <p class="text-xs text-text opacity-70">
                Marca los productos que se van a agregar a la lista de compra.
            </p>
        </div>

        <table class="hidden w-full table-fixed border-collapse text-sm md:table">
            <thead class="border-b border-secondary bg-secondary text-text">
                <tr>
                    <th class="w-12 px-4 py-3"></th>
                    <th class="px-4 py-3 text-left font-semibold">
                        Producto
                    </th>
                    <th class="px-4 py-3 text-left font-semibold">
                        Codigo / Barcode
                    </th>
                    <th class="px-4 py-3 text-left font-semibold">
                        Departamento
                    </th>
                    <th class="px-4 py-3 text-left font-semibold">
                        Categoria
                    </th>
                    <th class="px-4 py-3 text-right font-semibold">
                        Stock
                    </th>
                    <th class="px-4 py-3 text-right font-semibold">
                        Minimo
                    </th>
                </tr>
            </thead>

            <tbody class="divide-y divide-secondary bg-background">
                <tr v-for="product in products" :key="product.id" class="odd:bg-secondary transition-colors hover:bg-primary/10">
                    <td class="px-4 py-3">
                        <input type="checkbox" :checked="isSelected(selectedItems, product.id)"
                            class="rounded border-secondary text-primary focus:ring-primary"
                            @change="$emit('toggle', product)">
                    </td>

                    <td class="min-w-0 px-4 py-3">
                        <p class="truncate text-sm font-semibold text-text" :title="product.name">
                            {{ product.name }}
                        </p>
                        <p class="text-xs text-text opacity-70">
                            ID inventario: {{ product.id }}
                        </p>
                    </td>

                    <td class="min-w-0 px-4 py-3">
                        <p class="truncate text-sm font-medium text-text" :title="product.main_barcode || product.code || 'Sin codigo'">
                            {{ product.main_barcode || product.code || 'Sin codigo' }}
                        </p>
                    </td>

                    <td class="min-w-0 px-4 py-3">
                        <p class="truncate text-sm text-text" :title="product.department || product.product_department_name || 'Sin departamento'">
                            {{ product.department || product.product_department_name || 'Sin departamento' }}
                        </p>
                    </td>

                    <td class="min-w-0 px-4 py-3">
                        <p class="truncate text-sm text-text" :title="product.category">
                            {{ product.category }}
                        </p>
                    </td>

                    <td class="px-4 py-3 text-right">
                        <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold"
                            :class="stockTone(product)">
                            {{ product.stockLabel ?? product.stock }}
                        </span>
                    </td>

                    <td class="px-4 py-3 text-right text-sm font-semibold text-text opacity-80">
                        {{ product.minStockLabel ?? product.min_stock }}
                    </td>
                </tr>
            </tbody>
        </table>

        <div class="divide-y divide-secondary md:hidden">
            <label v-for="product in products" :key="product.id" class="flex gap-3 p-4">
                <input type="checkbox" :checked="isSelected(selectedItems, product.id)"
                    class="mt-1 rounded border-secondary text-primary focus:ring-primary"
                    @change="$emit('toggle', product)">

                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-text">
                                {{ product.name }}
                            </p>
                            <p class="text-xs text-text opacity-70">
                                {{ product.department || product.product_department_name || 'Sin departamento' }} / {{ product.category }}
                            </p>
                        </div>

                        <span class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-bold"
                            :class="stockTone(product)">
                            {{ product.stockLabel ?? product.stock }}
                        </span>
                    </div>

                    <p class="mt-2 text-xs text-text opacity-70">
                        Codigo: {{ product.main_barcode || product.code || 'Sin codigo' }}
                    </p>

                    <p class="mt-2 text-sm text-text opacity-80">
                        Minimo: <b>{{ product.minStockLabel ?? product.min_stock }}</b>
                    </p>
                </div>
            </label>
        </div>

        <div v-if="!products.length" class="p-8 text-center">
            <p class="text-sm font-semibold text-text">
                No se encontraron productos.
            </p>
            <p class="mt-1 text-xs text-text opacity-70">
                Ajusta los filtros para ampliar la busqueda.
            </p>
        </div>
    </div>
</template>
