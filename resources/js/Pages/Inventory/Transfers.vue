<script setup>
import { computed, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const search = ref('')
const statusFilter = ref('')

const transfers = ref([
    { id: 1, folio: 'TR-001', product: 'Tanque de oxígeno 680L', quantity: 6, origin: 'Sucursal Centro', destination: 'Sucursal Norte', responsible: 'Carlos Ramirez', status: 'Pendiente', date: '2026-05-18' },
    { id: 2, folio: 'TR-002', product: 'Regulador para tanque', quantity: 2, origin: 'Sucursal Norte', destination: 'Sucursal Sur', responsible: 'Andrea Mendoza', status: 'En tránsito', date: '2026-05-18' },
    { id: 3, folio: 'TR-003', product: 'Cánula nasal adulto', quantity: 20, origin: 'Sucursal Sur', destination: 'Sucursal Centro', responsible: 'Luis Santos', status: 'Completada', date: '2026-05-17' }
])

const filteredTransfers = computed(() => {
    const term = search.value.toLowerCase()

    return transfers.value.filter(item => {
        const matchesSearch =
            item.folio.toLowerCase().includes(term) ||
            item.product.toLowerCase().includes(term) ||
            item.responsible.toLowerCase().includes(term)

        const matchesStatus =
            !statusFilter.value || item.status === statusFilter.value

        return matchesSearch && matchesStatus
    })
})

const stats = computed(() => ({
    total: transfers.value.length,
    pending: transfers.value.filter(t => t.status === 'Pendiente').length,
    transit: transfers.value.filter(t => t.status === 'En tránsito').length,
    completed: transfers.value.filter(t => t.status === 'Completada').length
}))

function statusClass(status) {
    return {
        Pendiente: 'bg-amber-100 text-amber-700',
        'En tránsito': 'bg-blue-100 text-blue-700',
        Completada: 'bg-green-100 text-green-700',
        Cancelada: 'bg-red-100 text-red-700'
    }[status] || 'bg-slate-100 text-slate-700'
}
</script>

<template>
    <section class="space-y-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Transferencias</h1>
                <p class="text-sm text-slate-500 mt-1">
                    Control visual de movimientos de stock entre sucursales.
                </p>
            </div>

            <button class="bg-[#1f1d2b] text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">compare_arrows</span>
                Nueva transferencia
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Transferencias</p>
                <h2 class="text-2xl font-bold text-slate-800 mt-1">{{ stats.total }}</h2>
            </div>
            <div class="bg-white border border-amber-100 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Pendientes</p>
                <h2 class="text-2xl font-bold text-amber-700 mt-1">{{ stats.pending }}</h2>
            </div>
            <div class="bg-white border border-blue-100 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">En tránsito</p>
                <h2 class="text-2xl font-bold text-blue-700 mt-1">{{ stats.transit }}</h2>
            </div>
            <div class="bg-white border border-green-100 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Completadas</p>
                <h2 class="text-2xl font-bold text-green-700 mt-1">{{ stats.completed }}</h2>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 grid grid-cols-1 md:grid-cols-2 gap-3">
                <input v-model="search" type="text" placeholder="Buscar folio, producto o responsable..."
                    class="border border-slate-300 rounded-lg px-3 py-2 text-sm" />

                <select v-model="statusFilter" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Estado</option>
                    <option value="Pendiente">Pendiente</option>
                    <option value="En tránsito">En tránsito</option>
                    <option value="Completada">Completada</option>
                    <option value="Cancelada">Cancelada</option>
                </select>
            </div>

            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Folio</th>
                            <th class="px-4 py-3 text-left font-semibold">Producto</th>
                            <th class="px-4 py-3 text-left font-semibold">Cantidad</th>
                            <th class="px-4 py-3 text-left font-semibold">Origen</th>
                            <th class="px-4 py-3 text-left font-semibold">Destino</th>
                            <th class="px-4 py-3 text-left font-semibold">Responsable</th>
                            <th class="px-4 py-3 text-left font-semibold">Estado</th>
                            <th class="px-4 py-3 text-center font-semibold">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="item in filteredTransfers" :key="item.id"
                            class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-4 py-4 font-bold text-slate-800">{{ item.folio }}</td>
                            <td class="px-4 py-4 text-slate-700">{{ item.product }}</td>
                            <td class="px-4 py-4 font-bold text-slate-800">{{ item.quantity }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ item.origin }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ item.destination }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ item.responsible }}</td>
                            <td class="px-4 py-4">
                                <span class="px-3 py-1 rounded-full text-xs font-bold"
                                    :class="statusClass(item.status)">
                                    {{ item.status }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex justify-center gap-2">
                                    <button class="bg-blue-50 text-blue-700 px-3 py-2 rounded-lg text-xs font-semibold">
                                        Ver
                                    </button>
                                    <button
                                        class="bg-green-50 text-green-700 px-3 py-2 rounded-lg text-xs font-semibold">
                                        Completar
                                    </button>
                                    <button class="bg-red-50 text-red-700 px-3 py-2 rounded-lg text-xs font-semibold">
                                        Cancelar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!filteredTransfers.length">
                            <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                                No se encontraron transferencias.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="md:hidden p-4 space-y-4">
                <div v-for="item in filteredTransfers" :key="item.id" class="border border-slate-200 rounded-2xl p-4">
                    <div class="flex justify-between gap-3">
                        <div>
                            <p class="font-bold text-slate-800">{{ item.folio }}</p>
                            <p class="text-sm text-slate-700 mt-1">{{ item.product }}</p>
                        </div>

                        <span class="px-3 py-1 rounded-full text-xs font-bold h-fit" :class="statusClass(item.status)">
                            {{ item.status }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm mt-4">
                        <div>
                            <p class="text-xs text-slate-400">Cantidad</p>
                            <p class="font-bold text-slate-800">{{ item.quantity }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Responsable</p>
                            <p class="font-medium text-slate-700">{{ item.responsible }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Origen</p>
                            <p class="font-medium text-slate-700">{{ item.origin }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Destino</p>
                            <p class="font-medium text-slate-700">{{ item.destination }}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 mt-4 pt-3 border-t border-slate-100">
                        <button class="flex-1 bg-blue-50 text-blue-700 px-3 py-2 rounded-lg text-xs font-semibold">
                            Ver
                        </button>
                        <button class="flex-1 bg-green-50 text-green-700 px-3 py-2 rounded-lg text-xs font-semibold">
                            Completar
                        </button>
                        <button class="flex-1 bg-red-50 text-red-700 px-3 py-2 rounded-lg text-xs font-semibold">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>