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
        Pendiente: 'bg-secondary text-accent',
        'En tránsito': 'bg-secondary text-text',
        Completada: 'bg-secondary text-accent',
        Cancelada: 'bg-secondary text-primary'
    }[status] || 'bg-secondary text-text'
}
</script>

<template>
    <section class="space-y-5">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-text">Transferencias</h1>
                <p class="mt-1 text-sm text-text opacity-70">
                    Control visual de movimientos de stock entre sucursales.
                </p>
            </div>

            <button class="flex items-center gap-2 rounded-lg border border-primary bg-primary px-4 py-2 text-sm text-white">
                <span class="material-symbols-outlined text-[18px]">compare_arrows</span>
                Nueva transferencia
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-secondary bg-background p-5 shadow-sm">
                <p class="text-sm text-text opacity-70">Transferencias</p>
                <h2 class="mt-1 text-2xl font-bold text-text">{{ stats.total }}</h2>
            </div>
            <div class="rounded-2xl border border-accent bg-background p-5 shadow-sm">
                <p class="text-sm text-text opacity-70">Pendientes</p>
                <h2 class="mt-1 text-2xl font-bold text-accent">{{ stats.pending }}</h2>
            </div>
            <div class="rounded-2xl border border-secondary bg-background p-5 shadow-sm">
                <p class="text-sm text-text opacity-70">En tránsito</p>
                <h2 class="mt-1 text-2xl font-bold text-text">{{ stats.transit }}</h2>
            </div>
            <div class="rounded-2xl border border-accent bg-background p-5 shadow-sm">
                <p class="text-sm text-text opacity-70">Completadas</p>
                <h2 class="mt-1 text-2xl font-bold text-accent">{{ stats.completed }}</h2>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-secondary bg-background shadow-sm">
            <div class="grid grid-cols-1 gap-3 border-b border-secondary p-4 md:grid-cols-2">
                <input v-model="search" type="text" placeholder="Buscar folio, producto o responsable..."
                    class="rounded-lg border border-secondary bg-secondary px-3 py-2 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary" />

                <select v-model="statusFilter" class="rounded-lg border border-secondary bg-secondary px-3 py-2 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary">
                    <option value="">Estado</option>
                    <option value="Pendiente">Pendiente</option>
                    <option value="En tránsito">En tránsito</option>
                    <option value="Completada">Completada</option>
                    <option value="Cancelada">Cancelada</option>
                </select>
            </div>

            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-secondary bg-secondary text-text opacity-80">
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
                            class="border-b border-secondary hover:bg-secondary">
                            <td class="px-4 py-4 font-bold text-text">{{ item.folio }}</td>
                            <td class="px-4 py-4 text-text">{{ item.product }}</td>
                            <td class="px-4 py-4 font-bold text-text">{{ item.quantity }}</td>
                            <td class="px-4 py-4 text-text opacity-80">{{ item.origin }}</td>
                            <td class="px-4 py-4 text-text opacity-80">{{ item.destination }}</td>
                            <td class="px-4 py-4 text-text opacity-80">{{ item.responsible }}</td>
                            <td class="px-4 py-4">
                                <span class="px-3 py-1 rounded-full text-xs font-bold"
                                    :class="statusClass(item.status)">
                                    {{ item.status }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex justify-center gap-2">
                                    <button class="rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-text">
                                        Ver
                                    </button>
                                    <button
                                        class="rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-accent">
                                        Completar
                                    </button>
                                    <button class="rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-primary">
                                        Cancelar
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!filteredTransfers.length">
                            <td colspan="8" class="px-4 py-10 text-center text-text opacity-70">
                                No se encontraron transferencias.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="md:hidden p-4 space-y-4">
                <div v-for="item in filteredTransfers" :key="item.id" class="rounded-2xl border border-secondary bg-background p-4">
                    <div class="flex justify-between gap-3">
                        <div>
                            <p class="font-bold text-text">{{ item.folio }}</p>
                            <p class="mt-1 text-sm text-text">{{ item.product }}</p>
                        </div>

                        <span class="px-3 py-1 rounded-full text-xs font-bold h-fit" :class="statusClass(item.status)">
                            {{ item.status }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 text-sm mt-4">
                        <div>
                            <p class="text-xs text-text opacity-50">Cantidad</p>
                            <p class="font-bold text-text">{{ item.quantity }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-text opacity-50">Responsable</p>
                            <p class="font-medium text-text">{{ item.responsible }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-text opacity-50">Origen</p>
                            <p class="font-medium text-text">{{ item.origin }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-text opacity-50">Destino</p>
                            <p class="font-medium text-text">{{ item.destination }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-secondary pt-3">
                        <button class="flex-1 rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-text">
                            Ver
                        </button>
                        <button class="flex-1 rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-accent">
                            Completar
                        </button>
                        <button class="flex-1 rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-primary">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
