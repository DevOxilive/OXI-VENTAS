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
        Estable: 'bg-secondary text-accent',
        Próximo: 'bg-secondary text-accent',
        Crítico: 'bg-secondary text-primary',
        Vencido: 'bg-secondary text-primary'
    }[status] || 'bg-secondary text-text'
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
                <h1 class="text-xl font-bold text-text">Caducidades</h1>
                <p class="mt-1 text-sm text-text opacity-70">
                    Control visual de lotes próximos a vencer, vencidos y responsables por sucursal.
                </p>
            </div>

            <button class="flex items-center gap-2 rounded-lg border border-primary bg-primary px-4 py-2 text-sm text-white">
                <span class="material-symbols-outlined text-[18px]">add_alert</span>
                Nueva alerta
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-secondary bg-background p-5 shadow-sm">
                <p class="text-sm text-text opacity-70">Lotes monitoreados</p>
                <h2 class="mt-1 text-2xl font-bold text-text">{{ stats.total }}</h2>
            </div>
            <div class="rounded-2xl border border-primary bg-background p-5 shadow-sm">
                <p class="text-sm text-text opacity-70">Críticos</p>
                <h2 class="mt-1 text-2xl font-bold text-primary">{{ stats.critical }}</h2>
            </div>
            <div class="rounded-2xl border border-primary bg-background p-5 shadow-sm">
                <p class="text-sm text-text opacity-70">Vencidos</p>
                <h2 class="mt-1 text-2xl font-bold text-primary">{{ stats.expired }}</h2>
            </div>
            <div class="rounded-2xl border border-accent bg-background p-5 shadow-sm">
                <p class="text-sm text-text opacity-70">Estables</p>
                <h2 class="mt-1 text-2xl font-bold text-accent">{{ stats.stable }}</h2>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-secondary bg-background shadow-sm">
            <div class="grid grid-cols-1 gap-3 border-b border-secondary p-4 md:grid-cols-3">
                <input v-model="search" type="text" placeholder="Buscar producto o lote..."
                    class="rounded-lg border border-secondary bg-secondary px-3 py-2 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary" />

                <select v-model="urgencyFilter" class="rounded-lg border border-secondary bg-secondary px-3 py-2 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary">
                    <option value="">Urgencia</option>
                    <option value="Estable">Estable</option>
                    <option value="Próximo">Próximo</option>
                    <option value="Crítico">Crítico</option>
                    <option value="Vencido">Vencido</option>
                </select>

                <select v-model="branchFilter" class="rounded-lg border border-secondary bg-secondary px-3 py-2 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary">
                    <option value="">Sucursal</option>
                    <option value="Sucursal Centro">Sucursal Centro</option>
                    <option value="Sucursal Norte">Sucursal Norte</option>
                    <option value="Sucursal Sur">Sucursal Sur</option>
                </select>
            </div>

            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-secondary bg-secondary text-text opacity-80">
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
                            class="border-b border-secondary hover:bg-secondary">
                            <td class="px-4 py-4 font-semibold text-text">{{ item.product }}</td>
                            <td class="px-4 py-4 text-text opacity-80">{{ item.lot }}</td>
                            <td class="px-4 py-4 text-text opacity-80">{{ item.branch }}</td>
                            <td class="px-4 py-4 font-bold text-text">{{ item.stock }}</td>
                            <td class="px-4 py-4 text-text opacity-80">{{ item.expirationDate }}</td>
                            <td class="px-4 py-4">
                                <span class="font-bold" :class="item.daysLeft <= 0 ? 'text-primary' : 'text-text'">
                                    {{ item.daysLeft }} días
                                </span>
                            </td>
                            <td class="px-4 py-4 text-text opacity-80">{{ item.responsible }}</td>
                            <td class="px-4 py-4">
                                <div class="flex justify-center gap-2">
                                    <button @click="markForReview(item)"
                                        class="rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-text">
                                        Revisar
                                    </button>
                                    <button @click="removeLot(item)"
                                        class="rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-primary">
                                        Retirar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!filteredExpirations.length">
                            <td colspan="8" class="px-4 py-10 text-center text-text opacity-70">
                                No se encontraron lotes registrados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="md:hidden p-4 space-y-4">
                <div v-for="item in filteredExpirations" :key="item.id" class="rounded-2xl border border-secondary bg-background p-4">
                    <div class="flex justify-between gap-3">
                        <div>
                            <p class="font-bold text-text">{{ item.product }}</p>
                            <p class="mt-1 text-xs text-text opacity-50">{{ item.lot }} · {{ item.branch }}</p>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold h-fit" :class="statusClass(item.status)">
                            {{ item.status }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm mt-4">
                        <div>
                            <p class="text-xs text-text opacity-50">Stock</p>
                            <p class="font-bold text-text">{{ item.stock }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-text opacity-50">Días restantes</p>
                            <p class="font-bold" :class="item.daysLeft <= 0 ? 'text-primary' : 'text-text'">
                                {{ item.daysLeft }} días
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-text opacity-50">Caducidad</p>
                            <p class="font-medium text-text">{{ item.expirationDate }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-text opacity-50">Responsable</p>
                            <p class="font-medium text-text">{{ item.responsible }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex gap-2 border-t border-secondary pt-3">
                        <button @click="markForReview(item)"
                            class="flex-1 rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-text">
                            Revisar
                        </button>
                        <button @click="removeLot(item)"
                            class="flex-1 rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-primary">
                            Retirar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
