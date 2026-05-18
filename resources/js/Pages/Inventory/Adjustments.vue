<script setup>
import { computed, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const search = ref('')
const statusFilter = ref('')

const adjustments = ref([
    { id: 1, folio: 'AJ-001', product: 'Regulador para tanque', systemStock: 7, physicalStock: 6, difference: -1, branch: 'Sucursal Norte', responsible: 'Luis Santos', reason: 'Diferencia en conteo físico', evidence: 'Foto adjunta', status: 'Pendiente', date: '2026-05-18' },
    { id: 2, folio: 'AJ-002', product: 'Mascarilla nebulizadora adulto', systemStock: 40, physicalStock: 35, difference: -5, branch: 'Sucursal Centro', responsible: 'Andrea Mendoza', reason: 'Producto dañado', evidence: 'Acta interna', status: 'Aprobado', date: '2026-05-17' },
    { id: 3, folio: 'AJ-003', product: 'Cánula nasal adulto', systemStock: 30, physicalStock: 34, difference: 4, branch: 'Sucursal Sur', responsible: 'Carlos Ramirez', reason: 'Ingreso no registrado', evidence: 'Sin evidencia', status: 'Rechazado', date: '2026-05-16' }
])

const filteredAdjustments = computed(() => {
    const term = search.value.toLowerCase()

    return adjustments.value.filter(item => {
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
    total: adjustments.value.length,
    pending: adjustments.value.filter(a => a.status === 'Pendiente').length,
    approved: adjustments.value.filter(a => a.status === 'Aprobado').length,
    rejected: adjustments.value.filter(a => a.status === 'Rechazado').length
}))

function statusClass(status) {
    return {
        Pendiente: 'bg-amber-100 text-amber-700',
        Aprobado: 'bg-green-100 text-green-700',
        Rechazado: 'bg-red-100 text-red-700'
    }[status] || 'bg-slate-100 text-slate-700'
}

function differenceClass(value) {
    return value < 0 ? 'text-red-700' : 'text-green-700'
}
</script>

<template>
    <section class="space-y-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Ajustes de inventario</h1>
                <p class="text-sm text-slate-500 mt-1">
                    Correcciones auditadas entre stock del sistema y conteo físico.
                </p>
            </div>

            <button class="bg-[#1f1d2b] text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">tune</span>
                Nuevo ajuste
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Ajustes totales</p>
                <h2 class="text-2xl font-bold text-slate-800 mt-1">{{ stats.total }}</h2>
            </div>
            <div class="bg-white border border-amber-100 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Pendientes</p>
                <h2 class="text-2xl font-bold text-amber-700 mt-1">{{ stats.pending }}</h2>
            </div>
            <div class="bg-white border border-green-100 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Aprobados</p>
                <h2 class="text-2xl font-bold text-green-700 mt-1">{{ stats.approved }}</h2>
            </div>
            <div class="bg-white border border-red-100 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Rechazados</p>
                <h2 class="text-2xl font-bold text-red-700 mt-1">{{ stats.rejected }}</h2>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 grid grid-cols-1 md:grid-cols-2 gap-3">
                <input v-model="search" type="text" placeholder="Buscar folio, producto o responsable..."
                    class="border border-slate-300 rounded-lg px-3 py-2 text-sm" />

                <select v-model="statusFilter" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Estado</option>
                    <option value="Pendiente">Pendiente</option>
                    <option value="Aprobado">Aprobado</option>
                    <option value="Rechazado">Rechazado</option>
                </select>
            </div>

            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200 text-slate-600">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Folio</th>
                            <th class="px-4 py-3 text-left font-semibold">Producto</th>
                            <th class="px-4 py-3 text-left font-semibold">Sistema</th>
                            <th class="px-4 py-3 text-left font-semibold">Físico</th>
                            <th class="px-4 py-3 text-left font-semibold">Diferencia</th>
                            <th class="px-4 py-3 text-left font-semibold">Sucursal</th>
                            <th class="px-4 py-3 text-left font-semibold">Responsable</th>
                            <th class="px-4 py-3 text-left font-semibold">Estado</th>
                            <th class="px-4 py-3 text-center font-semibold">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="item in filteredAdjustments" :key="item.id"
                            class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-4 py-4 font-bold text-slate-800">{{ item.folio }}</td>
                            <td class="px-4 py-4">
                                <p class="font-semibold text-slate-800">{{ item.product }}</p>
                                <p class="text-xs text-slate-400">{{ item.reason }}</p>
                            </td>
                            <td class="px-4 py-4 font-bold text-slate-700">{{ item.systemStock }}</td>
                            <td class="px-4 py-4 font-bold text-slate-700">{{ item.physicalStock }}</td>
                            <td class="px-4 py-4 font-bold" :class="differenceClass(item.difference)">
                                {{ item.difference > 0 ? '+' : '' }}{{ item.difference }}
                            </td>
                            <td class="px-4 py-4 text-slate-600">{{ item.branch }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ item.responsible }}</td>
                            <td class="px-4 py-4">
                                <span class="px-3 py-1 rounded-full text-xs font-bold"
                                    :class="statusClass(item.status)">
                                    {{ item.status }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex justify-center gap-2">
                                    <button
                                        class="bg-blue-50 text-blue-700 px-3 py-2 rounded-lg text-xs font-semibold">Ver</button>
                                    <button
                                        class="bg-green-50 text-green-700 px-3 py-2 rounded-lg text-xs font-semibold">Aprobar</button>
                                    <button
                                        class="bg-red-50 text-red-700 px-3 py-2 rounded-lg text-xs font-semibold">Rechazar</button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!filteredAdjustments.length">
                            <td colspan="9" class="px-4 py-10 text-center text-slate-500">
                                No se encontraron ajustes registrados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="md:hidden p-4 space-y-4">
                <div v-for="item in filteredAdjustments" :key="item.id" class="border border-slate-200 rounded-2xl p-4">
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
                            <p class="text-xs text-slate-400">Sistema</p>
                            <p class="font-bold text-slate-800">{{ item.systemStock }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Físico</p>
                            <p class="font-bold text-slate-800">{{ item.physicalStock }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Diferencia</p>
                            <p class="font-bold" :class="differenceClass(item.difference)">
                                {{ item.difference > 0 ? '+' : '' }}{{ item.difference }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Sucursal</p>
                            <p class="font-medium text-slate-700">{{ item.branch }}</p>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <p class="text-xs text-slate-400">Motivo</p>
                        <p class="text-sm text-slate-700 mt-1">{{ item.reason }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2 mt-4 pt-3 border-t border-slate-100">
                        <button
                            class="flex-1 bg-blue-50 text-blue-700 px-3 py-2 rounded-lg text-xs font-semibold">Ver</button>
                        <button
                            class="flex-1 bg-green-50 text-green-700 px-3 py-2 rounded-lg text-xs font-semibold">Aprobar</button>
                        <button
                            class="flex-1 bg-red-50 text-red-700 px-3 py-2 rounded-lg text-xs font-semibold">Rechazar</button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>