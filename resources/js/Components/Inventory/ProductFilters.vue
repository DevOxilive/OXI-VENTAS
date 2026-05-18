<script setup>
defineProps({
    search: String,
    branchFilter: String,
    categoryFilter: String,
    categoriesDB: {
        type: Array,
        default: () => []
    },
    branchesDB: {
        type: Array,
        default: () => []
    }
})

defineEmits([
    'update:search',
    'update:branchFilter',
    'update:categoryFilter'
])
</script>

<template>
    <aside class="bg-white rounded-2xl shadow-sm p-5 h-fit">

        <h2 class="text-lg font-semibold text-slate-800 mb-5">
            Filtros
        </h2>

        <div>
            <label class="text-sm font-semibold text-slate-500">
                Búsqueda
            </label>

            <input :value="search" @input="$emit('update:search', $event.target.value)" type="text"
                placeholder="Código o nombre..."
                class="w-full mt-2 rounded-xl border-slate-200 text-sm focus:border-blue-500 focus:ring-blue-500" />
        </div>

        <div class="border-t my-5"></div>

        <div>
            <label class="text-sm font-semibold text-slate-500">
                Tienda
            </label>

            <div class="space-y-2 mt-3">
                <button @click="$emit('update:branchFilter', 'Todas')"
                    class="w-full text-left px-4 py-2 rounded-xl text-sm border transition" :class="branchFilter === 'Todas'
                        ? 'bg-blue-600 text-white border-blue-600'
                        : 'bg-white text-blue-600 border-blue-300 hover:bg-blue-50'">
                    Todas las tiendas
                </button>

                <button v-for="branch in branchesDB" :key="branch.id" @click="$emit('update:branchFilter', branch.name)"
                    class="w-full text-left px-4 py-2 rounded-xl text-sm border transition" :class="branchFilter === branch.name
                        ? 'bg-blue-600 text-white border-blue-600'
                        : 'bg-white text-blue-600 border-blue-300 hover:bg-blue-50'">
                    🏪 {{ branch.name }}
                </button>
            </div>
        </div>

        <div class="border-t my-5"></div>

        <div>
            <label class="text-sm font-semibold text-slate-500">
                Categoría
            </label>

            <div class="space-y-2 mt-3">
                <button @click="$emit('update:categoryFilter', 'Todas')"
                    class="w-full text-left px-4 py-2 rounded-xl text-sm border transition" :class="categoryFilter === 'Todas'
                        ? 'bg-blue-600 text-white border-blue-600'
                        : 'bg-white text-blue-600 border-blue-300 hover:bg-blue-50'">
                    Todas las categorías
                </button>

                <button v-for="category in categoriesDB" :key="category.id"
                    @click="$emit('update:categoryFilter', category.name)"
                    class="w-full text-left px-4 py-2 rounded-xl text-sm border transition" :class="categoryFilter === category.name
                        ? 'bg-blue-600 text-white border-blue-600'
                        : 'bg-white text-blue-600 border-blue-300 hover:bg-blue-50'">
                    {{ category.name }}
                </button>
            </div>
        </div>

    </aside>
</template>