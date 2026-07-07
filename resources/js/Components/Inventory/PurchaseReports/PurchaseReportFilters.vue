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
    props.filters.category = ''
    props.filters.stock = ''
    props.filters.per_page = 50

    emit('apply')
}
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
        <div class="mb-4 flex flex-col gap-1">
            <h2 class="text-sm font-bold text-slate-900">
                Filtros de inventario
            </h2>
            <p class="text-xs text-slate-500">
                Busca por nombre, codigo interno o codigo de barras.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 lg:grid-cols-12">
            <input v-model="filters.search" type="text" placeholder="Buscar producto, codigo o barcode..."
                class="lg:col-span-4 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-slate-900 focus:ring-slate-900"
                @keyup.enter="emit('apply')">

            <select v-model="filters.category"
                class="lg:col-span-3 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-slate-900 focus:ring-slate-900"
                @change="emit('apply')">
                <option value="">Categorias</option>
                <option v-for="category in categories" :key="category" :value="category">
                    {{ category }}
                </option>
            </select>

            <select v-model="filters.stock"
                class="lg:col-span-3 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-slate-900 focus:ring-slate-900"
                @change="emit('apply')">
                <option value="">Todo el stock</option>
                <option value="LOW">Stock bajo</option>
                <option value="OUT">Agotados</option>
            </select>

            <select v-model="filters.per_page"
                class="lg:col-span-1 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm focus:border-slate-900 focus:ring-slate-900"
                @change="emit('apply')">
                <option :value="25">25</option>
                <option :value="50">50</option>
                <option :value="100">100</option>
            </select>

            <button type="button"
                class="lg:col-span-1 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                @click="clearFilters">
                Limpiar
            </button>
        </div>
    </section>
</template>
