<script setup>
import { usePermissions } from '@/Composables/usePermissions'

const { can } = usePermissions()

defineProps({
    products: {
        type: Array,
        default: () => []
    }
})

defineEmits(['view', 'edit', 'delete'])
</script>

<template>
    <div class="md:hidden space-y-4">

        <div
            v-for="product in products"
            :key="product.id"
            class="bg-white rounded-2xl shadow-sm p-4"
        >
            <div class="flex justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-slate-800">
                        {{ product.name }}
                    </h3>

                    <p class="text-xs text-slate-400">
                        {{ product.barcode }}
                    </p>
                </div>

                <span class="h-fit px-3 py-1 rounded-full bg-slate-100 text-xs text-slate-600">
                    {{ product.category_name }}
                </span>
            </div>

            <div class="grid grid-cols-2 gap-3 mt-4 text-sm">
                <div>
                    <p class="text-slate-400 text-xs">Tienda</p>
                    <p class="font-medium">{{ product.branch_name }}</p>
                </div>

                <div>
                    <p class="text-slate-400 text-xs">Stock</p>
                    <p class="font-medium">{{ product.stock }}</p>
                </div>

                <div>
                    <p class="text-slate-400 text-xs">Costo</p>
                    <p class="font-medium">${{ product.cost }}</p>
                </div>

                <div>
                    <p class="text-slate-400 text-xs">Precio</p>
                    <p class="font-medium">${{ product.price }}</p>
                </div>
            </div>

            <div class="flex justify-end gap-2 mt-4">
                <button
                    v-if="can('productos.ver')"
                    @click="$emit('view', product)"
                    class="px-3 py-2 rounded-xl bg-sky-100 text-sky-600 text-sm"
                >
                    Ver
                </button>

                <button
                    v-if="can('productos.editar')"
                    @click="$emit('edit', product)"
                    class="px-3 py-2 rounded-xl bg-amber-100 text-amber-600 text-sm"
                >
                    Editar
                </button>

                <button
                    v-if="can('productos.eliminar')"
                    @click="$emit('delete', product)"
                    class="px-3 py-2 rounded-xl bg-red-100 text-red-600 text-sm"
                >
                    Eliminar
                </button>
            </div>
        </div>

    </div>
</template>