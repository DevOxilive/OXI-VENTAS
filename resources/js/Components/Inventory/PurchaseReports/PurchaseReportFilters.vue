<script setup>
const props = defineProps({
    filters: {
        type: Object,
        required: true,
    },
    categories: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['apply'])

function clearFilters() {
    props.filters.search = ''
    props.filters.category_id = ''
    props.filters.stock = ''
    props.filters.per_page = 50

    emit('apply')
}
</script>

<template>
    <section class="rounded-2xl border border-secondary bg-secondary p-4">
        <div class="mb-4 flex flex-col gap-1">
            <h2 class="text-sm font-bold text-text">
                Filtros de inventario
            </h2>
            <p class="text-xs text-text opacity-70">
                Busca por nombre, código interno o código de barras.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
            <input
                v-model="filters.search"
                type="text"
                placeholder="Buscar producto, código o barcode..."
                class="lg:col-span-4 rounded-xl border border-secondary bg-background px-4 py-3 text-sm text-text focus:border-primary focus:ring-primary"
                @keyup.enter="emit('apply')"
            >

            <select
                v-model="filters.category_id"
                class="lg:col-span-3 rounded-xl border border-secondary bg-background px-4 py-3 text-sm text-text focus:border-primary focus:ring-primary"
                @change="emit('apply')"
            >
                <option value="">Categorías</option>
                <option
                    v-for="category in categories"
                    :key="category.id ?? category"
                    :value="category.id ?? category"
                >
                    {{ category.name ?? category }}
                </option>
            </select>

            <select
                v-model="filters.stock"
                class="lg:col-span-3 rounded-xl border border-secondary bg-background px-4 py-3 text-sm text-text focus:border-primary focus:ring-primary"
                @change="emit('apply')"
            >
                <option value="">Todo el stock</option>
                <option value="LOW">Stock bajo</option>
                <option value="OUT">Agotados</option>
            </select>

            <select
                v-model="filters.per_page"
                class="lg:col-span-1 rounded-xl border border-secondary bg-background px-4 py-3 text-sm text-text focus:border-primary focus:ring-primary"
                @change="emit('apply')"
            >
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
            </select>

            <button
                type="button"
                class="lg:col-span-1 rounded-xl border border-secondary bg-background px-4 py-3 text-sm font-semibold text-text transition hover:bg-secondary"
                @click="clearFilters"
            >
                Limpiar
            </button>
        </div>
    </section>
</template>
