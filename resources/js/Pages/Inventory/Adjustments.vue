<script setup>
import { computed, ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import InputField from '@/Components/Forms/InputField.vue'
import { GlobalToolbar } from '@/Components/Toolbars'
import { sanitizeToolbarSearch } from '@/Components/Toolbars/toolbarInputSanitizer'

defineOptions({ layout: AdminLayout })

const search = ref('')
const statusFilter = ref('')

function handleSearchInput(value) {
    search.value = sanitizeToolbarSearch(value)
}

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
        Pendiente: 'bg-secondary text-accent',
        Aprobado: 'bg-secondary text-accent',
        Rechazado: 'bg-secondary text-primary'
    }[status] || 'bg-secondary text-text'
}

function differenceClass(value) {
    return value < 0 ? 'text-primary' : 'text-accent'
}
</script>

<template>
    <section class="space-y-5">
        <GlobalToolbar
            icon="tune"
            title="Ajustes de inventario"
            subtitle="Correcciones auditadas entre stock del sistema y conteo físico."
            :show-search="false"
            :show-records-per-page="false"
            :show-counter="false"
            :actions="[{ id: 'create', label: 'Nuevo ajuste', icon: 'add', variant: 'primary' }]"
        />

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-secondary bg-background p-5 shadow-sm">
                <p class="text-sm text-text opacity-70">Ajustes totales</p>
                <h2 class="mt-1 text-2xl font-bold text-text">{{ stats.total }}</h2>
            </div>
            <div class="rounded-2xl border border-accent bg-background p-5 shadow-sm">
                <p class="text-sm text-text opacity-70">Pendientes</p>
                <h2 class="mt-1 text-2xl font-bold text-accent">{{ stats.pending }}</h2>
            </div>
            <div class="rounded-2xl border border-accent bg-background p-5 shadow-sm">
                <p class="text-sm text-text opacity-70">Aprobados</p>
                <h2 class="mt-1 text-2xl font-bold text-accent">{{ stats.approved }}</h2>
            </div>
            <div class="rounded-2xl border border-primary bg-background p-5 shadow-sm">
                <p class="text-sm text-text opacity-70">Rechazados</p>
                <h2 class="mt-1 text-2xl font-bold text-primary">{{ stats.rejected }}</h2>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl border border-secondary bg-background shadow-sm">
            <div class="grid grid-cols-1 gap-3 border-b border-secondary p-4 md:grid-cols-2">
                <InputField
                    :model-value="search"
                    hide-label
                    field="inventory-adjustment-search"
                    validation-field="toolbar_search"
                    type="search"
                    placeholder="Buscar folio, producto o responsable..."
                    :show-counter="false"
                    @update:model-value="handleSearchInput"
                />

                <select v-model="statusFilter" class="rounded-lg border border-secondary bg-secondary px-3 py-2 text-sm text-text outline-none focus:border-primary focus:ring-2 focus:ring-primary">
                    <option value="">Estado</option>
                    <option value="Pendiente">Pendiente</option>
                    <option value="Aprobado">Aprobado</option>
                    <option value="Rechazado">Rechazado</option>
                </select>
            </div>

            <div class="hidden min-w-0 overflow-hidden md:block">
                <table class="w-full table-fixed border-collapse text-sm">
                    <thead class="border-b border-secondary bg-secondary text-text">
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

                    <tbody class="divide-y divide-secondary bg-background">
                        <tr v-for="item in filteredAdjustments" :key="item.id"
                            class="odd:bg-secondary transition-colors hover:bg-primary/10">
                            <td class="px-4 py-4 font-bold text-text">{{ item.folio }}</td>
                            <td class="px-4 py-4">
                                <p class="font-semibold text-text">{{ item.product }}</p>
                                <p class="text-xs text-text opacity-50">{{ item.reason }}</p>
                            </td>
                            <td class="px-4 py-4 font-bold text-text">{{ item.systemStock }}</td>
                            <td class="px-4 py-4 font-bold text-text">{{ item.physicalStock }}</td>
                            <td class="px-4 py-4 font-bold" :class="differenceClass(item.difference)">
                                {{ item.difference > 0 ? '+' : '' }}{{ item.difference }}
                            </td>
                            <td class="px-4 py-4 text-text opacity-80">{{ item.branch }}</td>
                            <td class="px-4 py-4 text-text opacity-80">{{ item.responsible }}</td>
                            <td class="px-4 py-4">
                                <span class="px-3 py-1 rounded-full text-xs font-bold"
                                    :class="statusClass(item.status)">
                                    {{ item.status }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex justify-center gap-2">
                                    <button
                                        class="rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-text">Ver</button>
                                    <button
                                        class="rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-accent">Aprobar</button>
                                    <button
                                        class="rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-primary">Rechazar</button>
                                </div>
                            </td>
                        </tr>

                        <tr v-if="!filteredAdjustments.length">
                            <td colspan="9" class="px-4 py-10 text-center text-text opacity-70">
                                No se encontraron ajustes registrados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="md:hidden p-4 space-y-4">
                <div v-for="item in filteredAdjustments" :key="item.id" class="rounded-2xl border border-secondary bg-background p-4">
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
                            <p class="text-xs text-text opacity-50">Sistema</p>
                            <p class="font-bold text-text">{{ item.systemStock }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-text opacity-50">Físico</p>
                            <p class="font-bold text-text">{{ item.physicalStock }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-text opacity-50">Diferencia</p>
                            <p class="font-bold" :class="differenceClass(item.difference)">
                                {{ item.difference > 0 ? '+' : '' }}{{ item.difference }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-text opacity-50">Sucursal</p>
                            <p class="font-medium text-text">{{ item.branch }}</p>
                        </div>
                    </div>

                    <div class="mt-4 border-t border-secondary pt-3">
                        <p class="text-xs text-text opacity-50">Motivo</p>
                        <p class="mt-1 text-sm text-text">{{ item.reason }}</p>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-secondary pt-3">
                        <button
                            class="flex-1 rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-text">Ver</button>
                        <button
                            class="flex-1 rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-accent">Aprobar</button>
                        <button
                            class="flex-1 rounded-lg bg-secondary px-3 py-2 text-xs font-semibold text-primary">Rechazar</button>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
