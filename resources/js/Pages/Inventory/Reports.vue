<script setup>
import { computed, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    branches: Array,
    summary: Object,
    branchReports: Array,
    currentBranch: Object,
})

const branchFilter = ref('Todas')
const currentBranch = computed(() => props.currentBranch ?? null)
const reports = computed(() => props.branchReports ?? [])

const filteredReports = computed(() => {
    if (branchFilter.value === 'Todas') return reports.value

    return reports.value.filter(item => item.branch_id === Number(branchFilter.value))
})

const totalSummary = computed(() => props.summary ?? {
    total_products: 0,
    total_stock: 0,
    inventory_value: 0,
    low_stock: 0,
    out_of_stock: 0,
})

const money = (value) => {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(value ?? 0)
}
</script>

<template>
    <section class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800">
                Reportes de inventario - {{ currentBranch?.name ?? 'Sucursal' }}
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Resumen general del inventario por sucursal.
            </p>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-slate-800">
                        Filtros
                    </h2>
                    <p class="text-sm text-slate-500">
                        Consulta información general o por sucursal.
                    </p>
                </div>

                <select v-model="branchFilter" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="Todas">Todas las sucursales</option>

                    <option v-for="branch in branches" :key="branch.id" :value="branch.id">
                        {{ branch.name }}
                    </option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Productos</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2">
                    {{ totalSummary.total_products }}
                </h3>
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Stock total</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2">
                    {{ totalSummary.total_stock }}
                </h3>
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Valor inventario</p>
                <h3 class="text-2xl font-bold text-slate-800 mt-2">
                    {{ money(totalSummary.inventory_value) }}
                </h3>
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Stock bajo</p>
                <h3 class="text-2xl font-bold text-orange-600 mt-2">
                    {{ totalSummary.low_stock }}
                </h3>
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Agotados</p>
                <h3 class="text-2xl font-bold text-red-600 mt-2">
                    {{ totalSummary.out_of_stock }}
                </h3>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100">
                <h2 class="text-base font-bold text-slate-800">
                    Resumen por sucursal
                </h2>
            </div>

            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Sucursal</th>
                            <th class="px-4 py-3 text-left font-semibold">Productos</th>
                            <th class="px-4 py-3 text-left font-semibold">Stock total</th>
                            <th class="px-4 py-3 text-left font-semibold">Valor inventario</th>
                            <th class="px-4 py-3 text-left font-semibold">Stock bajo</th>
                            <th class="px-4 py-3 text-left font-semibold">Agotados</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="item in filteredReports" :key="item.branch_id"
                            class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-4 py-4 font-semibold text-slate-800">
                                {{ item.branch_name }}
                            </td>
                            <td class="px-4 py-4 text-slate-600">
                                {{ item.products_count }}
                            </td>
                            <td class="px-4 py-4 text-slate-600">
                                {{ item.total_stock }}
                            </td>
                            <td class="px-4 py-4 text-slate-600">
                                {{ money(item.inventory_value) }}
                            </td>
                            <td class="px-4 py-4 text-orange-600 font-semibold">
                                {{ item.low_stock }}
                            </td>
                            <td class="px-4 py-4 text-red-600 font-semibold">
                                {{ item.out_of_stock }}
                            </td>
                        </tr>

                        <tr v-if="filteredReports.length === 0">
                            <td colspan="6" class="text-center py-10 text-slate-400">
                                No hay información para mostrar.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="md:hidden p-4 space-y-4">
                <div v-for="item in filteredReports" :key="item.branch_id"
                    class="border border-slate-200 rounded-2xl p-4">
                    <p class="font-bold text-slate-800">{{ item.branch_name }}</p>
                    <p class="text-sm text-slate-500 mt-1">
                        Productos: {{ item.products_count }} · Stock: {{ item.total_stock }}
                    </p>
                    <p class="text-sm text-slate-500 mt-1">
                        Valor: {{ money(item.inventory_value) }}
                    </p>
                    <p class="text-xs text-orange-600 mt-2">
                        Stock bajo: {{ item.low_stock }}
                    </p>
                    <p class="text-xs text-red-600 mt-1">
                        Agotados: {{ item.out_of_stock }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>