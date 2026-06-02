<script setup>
import ActionIconButton from '@/Components/Forms/ActionIconButton.vue'

defineProps({
    filteredProducts: {
        type: Array,
        default: () => [],
    },
    categories: {
        type: Array,
        default: () => [],
    },
    subcategories: {
        type: Array,
        default: () => [],
    },
    search: String,
    categoryFilter: String,
    subcategoryFilter: String,
    stockFilter: String,
    statusFilter: String,
    expirationStatusFilter: String,
    inactiveCandidateFilter: String,
})

defineEmits([
    'update:search',
    'update:categoryFilter',
    'update:subcategoryFilter',
    'update:stockFilter',
    'update:statusFilter',
    'update:expirationStatusFilter',
    'update:inactiveCandidateFilter',
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

function administrativeStatusLabel(status) {
    return {
        active: 'Activo',
        inactive: 'Inactivo',
        seasonal: 'Temporada',
    }[status] || 'Activo'
}
</script>

<template>
    <div class="hidden md:block bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm min-w-[1500px]">
                <thead class="bg-slate-50 text-slate-600 border-b border-slate-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold">
                            <input :value="search" type="text" placeholder="Buscar producto o código"
                                class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                                @input="$emit('update:search', $event.target.value)" />
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            <select :value="categoryFilter"
                                class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                                @change="$emit('update:categoryFilter', $event.target.value)">
                                <option value="">Categoría...</option>

                                <option v-for="category in categories" :key="category.id" :value="category.id">
                                    {{ category.name }}
                                </option>
                            </select>
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            <select :value="subcategoryFilter"
                                class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                                @change="$emit('update:subcategoryFilter', $event.target.value)">
                                <option value="">Subcategoría...</option>

                                <option v-for="subcategory in subcategories" :key="subcategory.id"
                                    :value="subcategory.id">
                                    {{ subcategory.name }}
                                </option>
                            </select>
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            <select :value="stockFilter"
                                class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                                @change="$emit('update:stockFilter', $event.target.value)">
                                <option value="">Stock...</option>
                                <option value="Disponible">Disponible</option>
                                <option value="Stock bajo">Stock bajo</option>
                                <option value="Agotado">Agotado</option>
                            </select>
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            <select :value="statusFilter"
                                class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                                @change="$emit('update:statusFilter', $event.target.value)">
                                <option value="">Estado...</option>
                                <option value="active">Activo</option>
                                <option value="inactive">Inactivo</option>
                                <option value="seasonal">Temporada</option>
                            </select>
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            <select :value="expirationStatusFilter"
                                class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                                @change="$emit('update:expirationStatusFilter', $event.target.value)">
                                <option value="">Caducidad...</option>
                                <option value="expired">Vencidos</option>
                                <option value="near_expiration">Por vencer</option>
                                <option value="valid">Vigentes</option>
                                <option value="without_expiration">Sin caducidad</option>
                            </select>
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            <select :value="inactiveCandidateFilter"
                                class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                                @change="$emit('update:inactiveCandidateFilter', $event.target.value)">
                                <option value="">Sin surtir...</option>
                                <option value="1">Candidatos</option>
                            </select>
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            Stock
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            Precio
                        </th>

                        <th class="text-left px-4 py-3 font-semibold">
                            Próx. caducidad
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
                            {{ product.raw?.product?.subcategory?.name || 'Sin subcategoría' }}
                        </td>

                        <td class="px-4 py-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold"
                                :class="statusClass(product.status)">
                                {{ product.status }}
                            </span>
                        </td>

                        <td class="px-4 py-4 text-slate-600">
                            {{ administrativeStatusLabel(product.administrativeStatus) }}
                        </td>

                        <td class="px-4 py-4 text-slate-600">
                            {{ product.expirationDate || 'No aplica' }}
                        </td>

                        <td class="px-4 py-4 text-slate-600">
                            {{ product.lastRestockedAt || 'Sin registro' }}
                        </td>

                        <td class="px-4 py-4 text-slate-700">
                            <span class="font-bold">
                                {{ product.stockLabel ?? product.stock }}
                            </span>

                            <span class="text-slate-400">
                                / min. {{ product.minStockLabel ?? product.minStock }}
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
                                <ActionIconButton :icon="product.tracksBatches ? 'inventory_2' : 'sync_alt'" :title="product.tracksBatches
                                    ? 'Movimiento con control por lotes'
                                    : 'Movimiento simple de stock'" variant="green"
                                    @click="$emit('adjust', product)" />

                                <ActionIconButton icon="settings" title="Configurar producto" variant="slate"
                                    @click="$emit('edit', product)" />
                            </div>
                        </td>
                    </tr>

                    <tr v-if="!filteredProducts.length">
                        <td colspan="11" class="px-4 py-10 text-center text-slate-500">
                            No se encontraron productos registrados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>