<script setup>
defineProps({
    filteredProducts: {
        type: Array,
        default: () => [],
    },
    productsDb: {
        type: Array,
        default: () => [],
    },
    recordsToShow: {
        type: Number,
        default: 10,
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
    'update:recordsToShow',
    'update:search',
    'update:categoryFilter',
    'update:subcategoryFilter',
    'update:stockFilter',
    'update:statusFilter',
    'update:expirationStatusFilter',
    'update:inactiveCandidateFilter',
])
</script>

<template>
    <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-4 space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3">
            <input :value="search" type="text" placeholder="Buscar producto o código"
                class="w-full border border-slate-300 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                @input="$emit('update:search', $event.target.value)" />

            <div class="flex items-center gap-2">
                <span class="text-sm text-slate-500 whitespace-nowrap">
                    Mostrar
                </span>

                <select :value="recordsToShow"
                    class="border border-slate-300 rounded-xl px-3 py-2.5 text-sm bg-white outline-none focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                    @change="$emit('update:recordsToShow', Number($event.target.value))">
                    <option :value="10">10</option>
                    <option :value="25">25</option>
                    <option :value="50">50</option>
                    <option :value="100">100</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <select :value="categoryFilter"
                class="border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                @change="$emit('update:categoryFilter', $event.target.value)">
                <option value="">Categoría</option>

                <option v-for="category in categories" :key="category.id" :value="category.id">
                    {{ category.name }}
                </option>
            </select>

            <select :value="subcategoryFilter"
                class="border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                @change="$emit('update:subcategoryFilter', $event.target.value)">
                <option value="">Subcategoría</option>

                <option v-for="subcategory in subcategories" :key="subcategory.id" :value="subcategory.id">
                    {{ subcategory.name }}
                </option>
            </select>

            <select :value="stockFilter"
                class="border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                @change="$emit('update:stockFilter', $event.target.value)">
                <option value="">Stock</option>
                <option value="Disponible">Disponible</option>
                <option value="Stock bajo">Stock bajo</option>
                <option value="Agotado">Agotado</option>
            </select>

            <select :value="statusFilter"
                class="border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                @change="$emit('update:statusFilter', $event.target.value)">
                <option value="">Estado</option>
                <option value="active">Activo</option>
                <option value="inactive">Inactivo</option>
                <option value="seasonal">Temporada</option>
            </select>

            <select :value="expirationStatusFilter"
                class="border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                @change="$emit('update:expirationStatusFilter', $event.target.value)">
                <option value="">Caducidad</option>
                <option value="expired">Vencidos</option>
                <option value="near_expiration">Por vencer</option>
                <option value="valid">Vigentes</option>
                <option value="without_expiration">Sin caducidad</option>
            </select>

            <select :value="inactiveCandidateFilter"
                class="border border-slate-300 rounded-xl px-3 py-2 text-sm outline-none bg-white focus:ring-2 focus:ring-blue-100 focus:border-blue-400"
                @change="$emit('update:inactiveCandidateFilter', $event.target.value)">
                <option value="">Sin surtir</option>
                <option value="1">Candidatos</option>
            </select>
        </div>

        <p class="text-xs text-slate-400">
            {{ filteredProducts.length }} de {{ productsDb.length }} productos
        </p>
    </section>
</template>