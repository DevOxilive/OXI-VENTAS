<script setup>
import { computed, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineOptions({ layout: AdminLayout })

const search = ref('')
const urgencyFilter = ref('')
const branchFilter = ref('')

const expirations = ref([
    { id: 1, product: 'Mascarilla nebulizadora adulto', lot: 'LOT-001', branch: 'Sucursal Centro', stock: 32, expirationDate: '2026-06-10', daysLeft: 23, responsible: 'Andrea Mendoza', status: 'Próximo' },
    { id: 2, product: 'Cánula nasal adulto', lot: 'LOT-002', branch: 'Sucursal Sur', stock: 18, expirationDate: '2026-05-28', daysLeft: 10, responsible: 'Luis Santos', status: 'Crítico' },
    { id: 3, product: 'Solución salina 500ml', lot: 'LOT-003', branch: 'Sucursal Norte', stock: 0, expirationDate: '2026-05-05', daysLeft: -13, responsible: 'Carlos Ramirez', status: 'Vencido' },
    { id: 4, product: 'Filtro para concentrador', lot: 'LOT-004', branch: 'Sucursal Centro', stock: 45, expirationDate: '2026-09-01', daysLeft: 106, responsible: 'Andrea Mendoza', status: 'Estable' }
])

const filteredExpirations = computed(() => {
    const term = search.value.toLowerCase()

    return expirations.value.filter(item => {
        const matchesSearch =
            item.product.toLowerCase().includes(term) ||
            item.lot.toLowerCase().includes(term)

        const matchesUrgency =
            !urgencyFilter.value || item.status === urgencyFilter.value

        const matchesBranch =
            !branchFilter.value || item.branch === branchFilter.value

        return matchesSearch && matchesUrgency && matchesBranch
    })
})

const stats = computed(() => ({
    total: expirations.value.length,
    critical: expirations.value.filter(i => i.status === 'Crítico').length,
    expired: expirations.value.filter(i => i.status === 'Vencido').length,
    stable: expirations.value.filter(i => i.status === 'Estable').length
}))

function statusClass(status) {
    return {
        Estable: 'bg-green-100 text-green-700',
        Próximo: 'bg-amber-100 text-amber-700',
        Crítico: 'bg-orange-100 text-orange-700',
        Vencido: 'bg-red-100 text-red-700'
    }[status] || 'bg-slate-100 text-slate-700'
}

function markForReview(item) {
    console.log('Revisar lote', item)
}

function removeLot(item) {
    console.log('Retirar lote', item)
}
</script>

<template>
    <section class="space-y-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-800">Caducidades</h1>
                <p class="text-sm text-slate-500 mt-1">
                    Control visual de lotes próximos a vencer, vencidos y responsables por sucursal.
                </p>
            </div>

            <button class="bg-[#1f1d2b] text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">add_alert</span>
                Nueva alerta
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Lotes monitoreados</p>
                <h2 class="text-2xl font-bold text-slate-800 mt-1">{{ stats.total }}</h2>
            </div>
            <div class="bg-white border border-orange-100 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Críticos</p>
                <h2 class="text-2xl font-bold text-orange-700 mt-1">{{ stats.critical }}</h2>
            </div>
            <div class="bg-white border border-red-100 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Vencidos</p>
                <h2 class="text-2xl font-bold text-red-700 mt-1">{{ stats.expired }}</h2>
            </div>
            <div class="bg-white border border-green-100 rounded-2xl p-5 shadow-sm">
                <p class="text-sm text-slate-500">Estables</p>
                <h2 class="text-2xl font-bold text-green-700 mt-1">{{ stats.stable }}</h2>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 grid grid-cols-1 md:grid-cols-3 gap-3">
                <input v-model="search" type="text" placeholder="Buscar producto o lote..."
                    class="border border-slate-300 rounded-lg px-3 py-2 text-sm" />

                <select v-model="urgencyFilter" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Urgencia</option>
                    <option value="Estable">Estable</option>
                    <option value="Próximo">Próximo</option>
                    <option value="Crítico">Crítico</option>
                    <option value="Vencido">Vencido</option>
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
                            <th class="px-4 py-3 text-left font-semibold">Producto</th>
                            <th class="px-4 py-3 text-left font-semibold">Lote</th>
                            <th class="px-4 py-3 text-left font-semibold">Sucursal</th>
                            <th class="px-4 py-3 text-left font-semibold">Stock</th>
                            <th class="px-4 py-3 text-left font-semibold">Caducidad</th>
                            <th class="px-4 py-3 text-left font-semibold">Días</th>
                            <th class="px-4 py-3 text-left font-semibold">Responsable</th>
                            <th class="px-4 py-3 text-center font-semibold">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-for="item in filteredExpirations" :key="item.id"
                            class="border-b border-slate-100 hover:bg-slate-50">
                            <td class="px-4 py-4 font-semibold text-slate-800">{{ item.product }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ item.lot }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ item.branch }}</td>
                            <td class="px-4 py-4 font-bold text-slate-800">{{ item.stock }}</td>
                            <td class="px-4 py-4 text-slate-600">{{ item.expirationDate }}</td>
                            <td class="px-4 py-4">
                                <span class="font-bold" :class="item.daysLeft <= 0 ? 'text-red-700' : 'text-slate-800'">
                                    {{ item.daysLeft }} días
                                </span>
                            </td>
                            <td class="px-4 py-4 text-slate-600">{{ item.responsible }}</td>
                            <td class="px-4 py-4">
                                <div class="flex justify-center gap-2">
                                    <button @click="markForReview(item)"
                                        class="bg-blue-50 text-blue-700 px-3 py-2 rounded-lg text-xs font-semibold">
                                        Revisar
                                    </button>
                                    <button @click="removeLot(item)"
                                        class="bg-red-50 text-red-700 px-3 py-2 rounded-lg text-xs font-semibold">
                                        Retirar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!filteredExpirations.length">
                            <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                                No se encontraron lotes registrados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="md:hidden p-4 space-y-4">
                <div v-for="item in filteredExpirations" :key="item.id" class="border border-slate-200 rounded-2xl p-4">
                    <div class="flex justify-between gap-3">
                        <div>
                            <p class="font-bold text-slate-800">{{ item.product }}</p>
                            <p class="text-xs text-slate-400 mt-1">{{ item.lot }} · {{ item.branch }}</p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold h-fit" :class="statusClass(item.status)">
                            {{ item.status }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm mt-4">
                        <div>
                            <p class="text-xs text-slate-400">Stock</p>
                            <p class="font-bold text-slate-800">{{ item.stock }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Días restantes</p>
                            <p class="font-bold" :class="item.daysLeft <= 0 ? 'text-red-700' : 'text-slate-800'">
                                {{ item.daysLeft }} días
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Caducidad</p>
                            <p class="font-medium text-slate-700">{{ item.expirationDate }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400">Responsable</p>
                            <p class="font-medium text-slate-700">{{ item.responsible }}</p>
                        </div>
                    </div>

                    <div class="flex gap-2 mt-4 pt-3 border-t border-slate-100">
                        <button @click="markForReview(item)"
                            class="flex-1 bg-blue-50 text-blue-700 px-3 py-2 rounded-lg text-xs font-semibold">
                            Revisar
                        </button>
                        <button @click="removeLot(item)"
                            class="flex-1 bg-red-50 text-red-700 px-3 py-2 rounded-lg text-xs font-semibold">
                            Retirar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>