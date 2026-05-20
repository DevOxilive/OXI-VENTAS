<script setup>
import { computed } from 'vue'

const props = defineProps({
    search: String,
    categoryFilter: [String, Number],
    subcategoryFilter: [String, Number],

    categoriesDB: {
        type: Array,
        default: () => []
    },

    subcategoriesDB: {
        type: Array,
        default: () => []
    }
})

defineEmits([
    'update:search',
    'update:categoryFilter',
    'update:subcategoryFilter',
    'create',
    'export'
])

const visibleSubcategories = computed(() => {
    if (props.categoryFilter === 'Todas') return []

    return props.subcategoriesDB.filter((subcategory) => {
       return String(subcategory.category_id) === String(props.categoryFilter)
    })
})
</script>

<template>  
  <section class="bg-white rounded-3xl shadow-xl border border-slate-200 p-5">
        

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-[1.4fr_1fr_1fr_auto_auto] gap-4 items-end">

            <div>
                <label class="block text-sm font-semibold text-slate-500 mb-2">
                    Buscar producto
                </label>

                <input
                    :value="search"
                    @input="$emit('update:search', $event.target.value)"
                    type="text"
                    placeholder="Nombre, código o descripción..."
                    class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-500 mb-2">
                    Categoría
                </label>

                <select
                    :value="categoryFilter"
                    @change="$emit('update:categoryFilter', $event.target.value); $emit('update:subcategoryFilter', 'Todas')"
                    class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm bg-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                >
                    <option value="Todas">
                        Todas
                    </option>

                    <option
                        v-for="category in categoriesDB"
                        :key="category.id"
                        :value="category.id"
                    >
                        {{ category.name }}
                    </option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-500 mb-2">
                    Subcategoría
                </label>
<select
    :value="subcategoryFilter"
    :disabled="categoryFilter === 'Todas'"
    @change="$emit('update:subcategoryFilter', $event.target.value)"
    class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm bg-white outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:bg-slate-100 disabled:text-slate-400"
>
    <option value="Todas">
        Todas
    </option>

    <option
        v-for="subcategory in visibleSubcategories"
        :key="subcategory.id"
        :value="subcategory.id"
    >
        {{ subcategory.name }}
    </option>
</select>
            </div>

            <button
                @click="$emit('create')"
                class="px-5 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold transition shadow-sm"
            >
                + Agregar
            </button>

            <button
                @click="$emit('export')"
                class="px-5 py-3 rounded-2xl bg-white border border-blue-300 text-blue-600 hover:bg-blue-50 text-sm font-semibold transition"
            >
                Exportar
            </button>

        </div>

    </section>
</template>