<script setup>
import { computed } from 'vue'

import ActionIconButton from '@/Components/Forms/ActionIconButton.vue'

import { usePermissions } from '@/Composables/usePermissions'

const { can } = usePermissions()

const props = defineProps({
    filteredProducts: {
        type: Array,
        default: () => []
    },

    search: String,
    branchFilter: String,
    categoryFilter: String,
    stockFilter: String
})

defineEmits([
    'update:search',
    'update:branchFilter',
    'update:categoryFilter',
    'update:stockFilter',
    'view',
    'edit',
    'adjust',
    'delete'
])

const canViewActions = computed(() =>
    can('inventario.productos.ver') ||
    can('inventario.productos.editar') ||
    can('inventario.productos.ajustar') ||
    can('inventario.productos.eliminar')
)

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
    <div class="hidden md:block bg-white rounded-2xl border border-gray-200 overflow-hidden">

        <div class="overflow-x-auto">
            <table class="w-full text-sm">

                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>

                        <th class="px-4 py-3 text-left font-medium text-gray-600">
                            <input :value="search" @input="$emit('update:search', $event.target.value)" type="text"
                                placeholder="Buscar producto..."
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                        </th>

                        <th class="px-4 py-3 text-left font-medium text-gray-600">
                            <select :value="categoryFilter"
                                @change="$emit('update:categoryFilter', $event.target.value)"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                <option value="">Categoría...</option>
                                <option value="Oxígeno medicinal">Oxígeno medicinal</option>
                                <option value="Equipo médico">Equipo médico</option>
                                <option value="Accesorios">Accesorios</option>
                                <option value="Refacciones">Refacciones</option>
                                <option value="Consumibles">Consumibles</option>
                            </select>
                        </th>

                        <th class="px-4 py-3 text-left font-medium text-gray-600">
                            <select :value="branchFilter" @change="$emit('update:branchFilter', $event.target.value)"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                <option value="">Sucursal...</option>
                                <option value="Sucursal Centro">Sucursal Centro</option>
                                <option value="Sucursal Norte">Sucursal Norte</option>
                                <option value="Sucursal Sur">Sucursal Sur</option>
                            </select>
                        </th>

                        <th class="px-4 py-3 text-left font-medium text-gray-600">
                            <select :value="stockFilter" @change="$emit('update:stockFilter', $event.target.value)"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                <option value="">Estado...</option>
                                <option value="Disponible">Disponible</option>
                                <option value="Stock bajo">Stock bajo</option>
                                <option value="Agotado">Agotado</option>
                                <option value="Inactivo">Inactivo</option>
                            </select>
                        </th>

                        <th class="px-4 py-3 text-left font-medium text-gray-600">
                            Stock
                        </th>

                        <th class="px-4 py-3 text-left font-medium text-gray-600">
                            Precio
                        </th>

                        <th class="px-4 py-3 text-left font-medium text-gray-600">
                            Caducidad
                        </th>

                        <th class="px-4 py-3 text-center font-medium text-gray-600">
                            Acciones
                        </th>

                    </tr>
                </thead>

                <tbody>

                    <tr v-for="product in filteredProducts" :key="product.id"
                        class="border-b border-gray-100 hover:bg-gray-50 transition">

                        <td class="px-4 py-4">
                            <div class="font-semibold text-gray-800">
                                {{ product.name }}
                            </div>

                            <div class="text-xs text-gray-400 mt-1">
                                {{ product.code }}
                            </div>
                        </td>

                        <td class="px-4 py-4 text-gray-600">
                            {{ product.category }}
                        </td>

                        <td class="px-4 py-4 text-gray-600">
                            {{ product.branch }}
                        </td>

                        <td class="px-4 py-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold"
                                :class="statusClass(product.status)">
                                {{ product.status }}
                            </span>
                        </td>

                        <td class="px-4 py-4">
                            <div class="font-bold text-gray-800">
                                {{ product.stock }}
                            </div>

                            <div class="text-xs text-gray-400">
                                Min: {{ product.minStock }}
                            </div>
                        </td>

                        <td class="px-4 py-4 font-semibold text-gray-700">
                            {{ formatCurrency(product.salePrice || product.price) }}
                        </td>

                        <td class="px-4 py-4 text-gray-600">
                            {{ product.expirationDate || 'No aplica' }}
                        </td>

                        <td class="px-4 py-4">
                            <div class="flex items-center justify-center gap-2">
                                <ActionIconButton icon="visibility" color="blue" tooltip="Visualizar producto"
                                    @click="$emit('view', product)" />

                                <ActionIconButton icon="edit" color="yellow" tooltip="Editar producto"
                                    @click="$emit('edit', product)" />

                                <ActionIconButton icon="inventory" color="green" tooltip="Ajustar stock"
                                    @click="$emit('adjust', product)" />

                                <ActionIconButton icon="delete" color="red" tooltip="Eliminar producto"
                                    @click="$emit('delete', product)" />

                            </div>
                        </td>

                    </tr>

                    <tr v-if="!filteredProducts.length">
                        <td :colspan="canViewActions ? 8 : 7" class="px-4 py-10 text-center text-gray-500">
                            No se encontraron productos registrados.
                        </td>
                    </tr>

                </tbody>

            </table>
        </div>

    </div>
</template>