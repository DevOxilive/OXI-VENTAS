<script setup>
import ActionIconButton from '@/Components/Forms/ActionIconButton.vue'

defineProps({
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

function statusClass(status) {
    const classes = {
        Disponible: 'bg-green-100 text-green-700',
        'Stock bajo': 'bg-amber-100 text-amber-700',
        Agotado: 'bg-red-100 text-red-700'
    }

    return classes[status] || 'bg-slate-100 text-slate-700'
}
</script>

<template>
    <div class="hidden md:block bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[1100px]">
                <thead class="bg-slate-50 text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold">
                            <input :value="search" @input="$emit('update:search', $event.target.value)" type="text"
                                placeholder="Buscar producto o código"
                                class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400" />
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            <select :value="categoryFilter"
                                @change="$emit('update:categoryFilter', $event.target.value)"
                                class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                                <option value="">Categoría...</option>
                                <option value="Oxígeno medicinal">Oxígeno medicinal</option>
                                <option value="Equipo médico">Equipo médico</option>
                                <option value="Accesorios">Accesorios</option>
                                <option value="Refacciones">Refacciones</option>
                            </select>
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            <select :value="branchFilter" @change="$emit('update:branchFilter', $event.target.value)"
                                class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                                <option value="">Sucursal...</option>
                                <option value="Sucursal Centro">Sucursal Centro</option>
                                <option value="Sucursal Norte">Sucursal Norte</option>
                                <option value="Sucursal Sur">Sucursal Sur</option>
                            </select>
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            <select :value="stockFilter" @change="$emit('update:stockFilter', $event.target.value)"
                                class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400">
                                <option value="">Estado...</option>
                                <option value="Disponible">Disponible</option>
                                <option value="Stock bajo">Stock bajo</option>
                                <option value="Agotado">Agotado</option>
                            </select>
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            Stock
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            Precio
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            Caducidad
                        </th>

                        <th class="text-center px-4 py-3 font-semibold">
                            Acciones
                        </th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-100">
                    <tr v-for="product in filteredProducts" :key="product.id"
                        class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-4">
                            <div class="font-semibold text-slate-800">
                                {{ product.name }}
                            </div>
                            <div class="text-xs text-slate-400 mt-1">
                                {{ product.code }}
                            </div>
                        </td>

                        <td class="px-4 py-4 text-slate-600">
                            {{ product.category }}
                        </td>

                        <td class="px-4 py-4 text-slate-600">
                            {{ product.branch }}
                        </td>

                        <td class="px-4 py-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold"
                                :class="statusClass(product.status)">
                                {{ product.status }}
                            </span>
                        </td>

                        <td class="px-4 py-4 text-slate-700">
                            <span class="font-bold">
                                {{ product.stock }}
                            </span>
                            <span class="text-slate-400">
                                / min. {{ product.minStock }}
                            </span>
                        </td>

                        <td class="px-4 py-4 font-semibold text-slate-700">
                            ${{ product.price }}
                        </td>

                        <td class="px-4 py-4 text-slate-600">
                            {{ product.expirationDate || 'No aplica' }}
                        </td>

                        <td class="px-4 py-4">
                            <div class="flex items-center justify-center gap-2">
                                <ActionIconButton icon="visibility" title="Visualizar producto" variant="blue"
                                    @click="$emit('view', product)" />

                                <ActionIconButton icon="edit" title="Editar producto" variant="amber"
                                    @click="$emit('edit', product)" />

                                <ActionIconButton icon="inventory" title="Ajustar stock" variant="green"
                                    @click="$emit('adjust', product)" />

                                <ActionIconButton icon="delete" title="Eliminar producto" variant="red"
                                    @click="$emit('delete', product)" />
                            </div>
                        </td>
                    </tr>

                    <tr v-if="!filteredProducts.length">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                            No se encontraron productos registrados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>