<script setup>
import { computed, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const search = ref('')
const typeFilter = ref('')
const branchFilter = ref('')

const movements = ref([
    {
        id: 1,
        type: 'Entrada',
        product: 'Tanque de oxígeno 680L',
        code: 'OXI-001',
        quantity: 12,
        branch: 'Sucursal Centro',
        responsible: 'Carlos Ramirez',
        reason: 'Compra a proveedor',
        date: '2026-05-18 09:40'
    },
    {
        id: 2,
        type: 'Salida',
        product: 'Mascarilla nebulizadora adulto',
        code: 'OXI-003',
        quantity: 5,
        branch: 'Sucursal Centro',
        responsible: 'Andrea Mendoza',
        reason: 'Venta mostrador',
        date: '2026-05-18 10:15'
    },
    {
        id: 3,
        type: 'Ajuste',
        product: 'Regulador para tanque',
        code: 'OXI-005',
        quantity: 1,
        branch: 'Sucursal Norte',
        responsible: 'Luis Santos',
        reason: 'Diferencia en conteo físico',
        date: '2026-05-18 11:05'
    }
])

const filteredMovements = computed(() => {
    const term = search.value.toLowerCase()

    return movements.value.filter(movement => {
        const matchesSearch =
            movement.product.toLowerCase().includes(term) ||
            movement.code.toLowerCase().includes(term) ||
            movement.responsible.toLowerCase().includes(term)

        const matchesType =
            !typeFilter.value || movement.type === typeFilter.value

        const matchesBranch =
            !branchFilter.value || movement.branch === branchFilter.value

        return matchesSearch && matchesType && matchesBranch
    })
})

const stats = computed(() => ({
    total: movements.value.length,
    entries: movements.value.filter(m => m.type === 'Entrada').length,
    exits: movements.value.filter(m => m.type === 'Salida').length,
    adjustments: movements.value.filter(m => m.type === 'Ajuste').length
}))

function typeClass(type) {
    return {
        Entrada: 'bg-green-100 text-green-700',
        Salida: 'bg-red-100 text-red-700',
        Ajuste: 'bg-amber-100 text-amber-700'
    }[type] || 'bg-slate-100 text-slate-700'
}
</script>

<template>
    <section class="space-y-5">
        <div>
            <h1 class="text-xl font-bold text-slate-800">
                Movimientos de inventario
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Historial de entradas, salidas y ajustes realizados sobre el stock.
            </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Movimientos totales</p>
                <h2 class="text-2xl font-bold text-slate-800 mt-1">{{ stats.total }}</h2>
            </div>

            <div class="bg-white border border-green-100 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Entradas</p>
                <h2 class="text-2xl font-bold text-green-700 mt-1">{{ stats.entries }}</h2>
            </div>

            <div class="bg-white border border-red-100 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Salidas</p>
                <h2 class="text-2xl font-bold text-red-700 mt-1">{{ stats.exits }}</h2>
            </div>

            <div class="bg-white border border-amber-100 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Ajustes</p>
                <h2 class="text-2xl font-bold text-amber-700 mt-1">{{ stats.adjustments }}</h2>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 grid grid-cols-1 md:grid-cols-3 gap-3">
                <input v-model="search" type="text" placeholder="Buscar producto, código o responsable..."
                    class="border border-slate-300 rounded-lg px-3 py-2 text-sm" />

                <select v-model="typeFilter" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Tipo de movimiento</option>
                    <option value="Entrada">Entrada</option>
                    <option value="Salida">Salida</option>
                    <option value="Ajuste">Ajuste</option>
                </select>

                <select v-model="branchFilter" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Sucursal</option>
                    <option value="Sucursal Centro">Sucursal Centro</option>
                    <option value="Sucursal Norte">Sucursal Norte</option>
                    <option value="Sucursal Sur">Sucursal Sur</option>
                </select>
            </div>

            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Tipo</th>
                            <th class="px-4 py-3 text-left font-semibold">Producto</th>
                            <th class="px-4 py-3 text-left font-semibold">Cantidad</th>
                            <th class="px-4 py-3 text-left font-semibold">Sucursal</th>
                            <th class="px-4 py-3 text-left font-semibold">Responsable</th>
                            <th class="px-4 py-3 text-left font-semibold">Motivo</th>
                            <th class="px-4 py-3 text-left font-semibold">Fecha</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="movement in filteredMovements" :key="movement.id"
                            class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-4 py-4">
                                <span class="px-3 py-1 rounded-full text-xs font-bold"
                                    :class="typeClass(movement.type)">
                                    {{ movement.type }}
                                </span>
                            </td>

                            <td class="px-4 py-4">
                                <p class="font-semibold text-slate-800">
                                    {{ movement.product }}
                                </p>
                                <p class="text-xs text-slate-400">
                                    {{ movement.code }}
                                </p>
                            </td>

                            <td class="px-4 py-4 font-bold text-slate-800">
                                {{ movement.type === 'Salida' ? '-' : '+' }}{{ movement.quantity }}
                            </td>

                            <td class="px-4 py-4 text-slate-600">
                                {{ movement.branch }}
                            </td>

                            <td class="px-4 py-4 text-slate-600">
                                {{ movement.responsible }}
                            </td>

                            <td class="px-4 py-4 text-slate-600">
                                {{ movement.reason }}
                            </td>

                            <td class="px-4 py-4 text-slate-500">
                                {{ movement.date }}
                            </td>
                        </tr>

                        <tr v-if="!filteredMovements.length">
                            <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                                No se encontraron movimientos registrados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="md:hidden p-4 space-y-4">
                <div v-for="movement in filteredMovements" :key="movement.id"
                    class="border border-slate-200 rounded-2xl p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-bold text-slate-800">
                                {{ movement.product }}
                            </p>
                            <p class="text-xs text-slate-400 mt-1">
                                {{ movement.code }}
                            </p>
                        </div>

                        <span class="px-3 py-1 rounded-full text-xs font-bold" :class="typeClass(movement.type)">
                            {{ movement.type }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm mt-4">
                        <div>
                            <p class="text-xs text-slate-400">Cantidad</p>
                            <p class="font-bold text-slate-800 mt-1">
                                {{ movement.type === 'Salida' ? '-' : '+' }}{{ movement.quantity }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-slate-400">Sucursal</p>
                            <p class="font-medium text-slate-700 mt-1">
                                {{ movement.branch }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-slate-400">Responsable</p>
                            <p class="font-medium text-slate-700 mt-1">
                                {{ movement.responsible }}
                            </p>
                        </div>

                        <div>
                            <p class="text-xs text-slate-400">Fecha</p>
                            <p class="font-medium text-slate-700 mt-1">
                                {{ movement.date }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <p class="text-xs text-slate-400">Motivo</p>
                        <p class="text-sm text-slate-700 mt-1">
                            {{ movement.reason }}
                        </p>
                    </div>
                </div>

                <div v-if="!filteredMovements.length"
                    class="border border-slate-200 rounded-2xl p-8 text-center text-slate-500">
                    No se encontraron movimientos registrados.
                </div>
            </div>
        </div>
    </section>
</template>