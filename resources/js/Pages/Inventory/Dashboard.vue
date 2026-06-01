<script setup>
import { computed, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import InventoryStatsCards from '@/Components/Inventory/BranchProducts/InventoryStatsCards.vue'

defineOptions({ layout: AdminLayout })

const page = usePage()

const search = ref('')
const branchFilter = ref('')
const categoryFilter = ref('')
const stockFilter = ref('')
const recordsToShow = ref(10)

const branches = computed(() => page.props.branches ?? [])

const defaultBranchSlug = computed(() => {
    return branches.value[0]?.slug ?? 'ajusco'
})

const productsDB = ref([
    {
        id: 1,
        code: 'OXI-001',
        name: 'Tanque de oxígeno 680L',
        category: 'Oxígeno medicinal',
        branch: 'Sucursal Centro',
        stock: 8,
        minStock: 10,
        price: 1450,
        expirationDate: '2026-07-20',
        status: 'Stock bajo'
    },
    {
        id: 2,
        code: 'OXI-002',
        name: 'Concentrador de oxígeno 5L',
        category: 'Equipo médico',
        branch: 'Sucursal Norte',
        stock: 4,
        minStock: 2,
        price: 12500,
        expirationDate: null,
        status: 'Disponible'
    },
    {
        id: 3,
        code: 'OXI-003',
        name: 'Mascarilla nebulizadora adulto',
        category: 'Accesorios',
        branch: 'Sucursal Centro',
        stock: 0,
        minStock: 15,
        price: 85,
        expirationDate: '2026-06-10',
        status: 'Agotado'
    },
    {
        id: 4,
        code: 'OXI-004',
        name: 'Cánula nasal adulto',
        category: 'Accesorios',
        branch: 'Sucursal Sur',
        stock: 37,
        minStock: 20,
        price: 45,
        expirationDate: '2026-09-01',
        status: 'Disponible'
    },
    {
        id: 5,
        code: 'OXI-005',
        name: 'Regulador para tanque',
        category: 'Refacciones',
        branch: 'Sucursal Norte',
        stock: 6,
        minStock: 5,
        price: 780,
        expirationDate: null,
        status: 'Disponible'
    }
])

const inventoryModules = computed(() => [
    {
        title: 'Productos',
        description: 'Alta, edición, consulta y control de stock.',
        icon: 'inventory_2',
        routeName: 'inventory.branches.products.index',
        routeParams: { branch: defaultBranchSlug.value },
        color: 'bg-blue-50 text-blue-700 border-blue-100'
    },
    {
        title: 'Movimientos',
        description: 'Historial de entradas, salidas y ajustes.',
        icon: 'sync_alt',
        routeName: 'inventory.branches.products.index',
        routeParams: { branch: defaultBranchSlug.value },
        color: 'bg-violet-50 text-violet-700 border-violet-100'
    },
    {
        title: 'Caducidades',
        description: 'Productos próximos a vencer y lotes críticos.',
        icon: 'event_busy',
        routeName: 'inventory.branches.products.index',
        routeParams: { branch: defaultBranchSlug.value },
        color: 'bg-amber-50 text-amber-700 border-amber-100'
    },
    {
        title: 'Transferencias',
        description: 'Movimientos de stock entre sucursales.',
        icon: 'compare_arrows',
        routeName: 'inventory.branches.products.index',
        routeParams: { branch: defaultBranchSlug.value },
        color: 'bg-cyan-50 text-cyan-700 border-cyan-100'
    },
    {
        title: 'Ajustes',
        description: 'Correcciones auditadas de inventario físico.',
        icon: 'tune',
        routeName: 'inventory.branches.products.index',
        routeParams: { branch: defaultBranchSlug.value },
        color: 'bg-rose-50 text-rose-700 border-rose-100'
    },
    {
        title: 'Reportes',
        description: 'Exportaciones, análisis y reportes operativos.',
        icon: 'bar_chart',
        routeName: 'inventory.branches.products.index',
        routeParams: { branch: defaultBranchSlug.value },
        color: 'bg-emerald-50 text-emerald-700 border-emerald-100'
    }
])

const recentMovements = [
    {
        id: 1,
        type: 'Entrada',
        product: 'Tanque de oxígeno 680L',
        quantity: '+12',
        branch: 'Sucursal Centro',
        user: 'Carlos Ramirez',
        date: 'Hoy, 09:40'
    },
    {
        id: 2,
        type: 'Salida',
        product: 'Mascarilla nebulizadora adulto',
        quantity: '-5',
        branch: 'Sucursal Centro',
        user: 'Andrea Mendoza',
        date: 'Hoy, 10:15'
    },
    {
        id: 3,
        type: 'Ajuste',
        product: 'Regulador para tanque',
        quantity: '-1',
        branch: 'Sucursal Norte',
        user: 'Luis Santos',
        date: 'Hoy, 11:05'
    }
]

const branchSummary = computed(() => {
    const branchesList = [...new Set(productsDB.value.map(product => product.branch))]

    return branchesList.map(branch => {
        const products = productsDB.value.filter(product => product.branch === branch)

        return {
            branch,
            totalProducts: products.length,
            lowStock: products.filter(product => product.status === 'Stock bajo').length,
            outOfStock: products.filter(product => product.status === 'Agotado').length
        }
    })
})

const criticalAlerts = computed(() => {
    return productsDB.value.filter(product =>
        product.status === 'Stock bajo' ||
        product.status === 'Agotado' ||
        product.expirationDate
    )
})

const filteredProducts = computed(() => {
    return productsDB.value
        .filter(product => {
            const term = search.value.toLowerCase()

            const matchesSearch =
                product.name.toLowerCase().includes(term) ||
                product.code.toLowerCase().includes(term)

            const matchesBranch =
                !branchFilter.value || product.branch === branchFilter.value

            const matchesCategory =
                !categoryFilter.value || product.category === categoryFilter.value

            const matchesStock =
                !stockFilter.value || product.status === stockFilter.value

            return matchesSearch && matchesBranch && matchesCategory && matchesStock
        })
        .slice(0, recordsToShow.value)
})

const stats = computed(() => {
    return {
        total: productsDB.value.length,
        lowStock: productsDB.value.filter(product => product.status === 'Stock bajo').length,
        outOfStock: productsDB.value.filter(product => product.status === 'Agotado').length,
        expiringSoon: productsDB.value.filter(product => product.expirationDate).length
    }
})

function movementClass(type) {
    const classes = {
        Entrada: 'bg-green-100 text-green-700',
        Salida: 'bg-red-100 text-red-700',
        Ajuste: 'bg-amber-100 text-amber-700'
    }

    return classes[type] || 'bg-slate-100 text-slate-700'
}

function openCreateModal() {
    window.location.href = route('inventory.branches.products.index', {
        branch: defaultBranchSlug.value,
    })
}

function exportExcel() {
    console.log('Exportar Excel')
}
</script>

<template>
    <section class="space-y-6">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">
                    Inventario
                </h1>

                <p class="text-sm text-slate-500 mt-1">
                    Panel general para controlar productos, stock, sucursales, movimientos y alertas operativas.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <button @click="openCreateModal"
                    class="bg-[#1f1d2b] text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2 shadow-sm">
                    <span class="material-symbols-outlined text-[18px]">
                        add_circle
                    </span>
                    Nuevo producto
                </button>

                <button @click="exportExcel"
                    class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm flex items-center gap-2 shadow-sm">
                    <span class="material-symbols-outlined text-[18px]">
                        download
                    </span>
                    Excel
                </button>
            </div>
        </div>

        <InventoryStatsCards :stats="stats" />

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            <div class="xl:col-span-2 bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-800">
                            Módulos de inventario
                        </h2>
                        <p class="text-xs text-slate-500 mt-1">
                            Accesos rápidos a las áreas operativas del módulo.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <a v-for="module in inventoryModules" :key="module.title"
                        :href="route(module.routeName, module.routeParams)"
                        class="group border border-slate-200 rounded-2xl p-4 hover:border-slate-300 hover:shadow-md transition-all bg-white">
                        <div class="flex items-start gap-4">
                            <div class="w-11 h-11 rounded-xl border flex items-center justify-center shrink-0"
                                :class="module.color">
                                <span class="material-symbols-outlined text-[22px]">
                                    {{ module.icon }}
                                </span>
                            </div>

                            <div class="min-w-0">
                                <h3 class="font-bold text-slate-800 group-hover:text-[#1f1d2b]">
                                    {{ module.title }}
                                </h3>

                                <p class="text-sm text-slate-500 mt-1 leading-snug">
                                    {{ module.description }}
                                </p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-base font-bold text-slate-800">
                            Alertas críticas
                        </h2>
                        <p class="text-xs text-slate-500 mt-1">
                            Productos que requieren atención.
                        </p>
                    </div>

                    <span class="bg-red-50 text-red-700 text-xs font-bold px-3 py-1 rounded-full">
                        {{ criticalAlerts.length }}
                    </span>
                </div>

                <div class="space-y-3 max-h-[310px] overflow-y-auto pr-1">
                    <div v-for="alert in criticalAlerts" :key="alert.id"
                        class="border border-slate-100 rounded-xl p-3 hover:bg-slate-50 transition">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ alert.name }}
                                </p>

                                <p class="text-xs text-slate-500 mt-1">
                                    {{ alert.branch }} · Stock {{ alert.stock }} / min. {{ alert.minStock }}
                                </p>
                            </div>

                            <span class="text-xs font-bold px-2 py-1 rounded-full" :class="alert.status === 'Agotado'
                                ? 'bg-red-100 text-red-700'
                                : 'bg-amber-100 text-amber-700'">
                                {{ alert.status }}
                            </span>
                        </div>

                        <p v-if="alert.expirationDate" class="text-xs text-slate-400 mt-2">
                            Caducidad: {{ alert.expirationDate }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
                <h2 class="text-base font-bold text-slate-800">
                    Estado por sucursal
                </h2>

                <p class="text-xs text-slate-500 mt-1 mb-4">
                    Resumen operativo por punto de venta.
                </p>

                <div class="space-y-3">
                    <div v-for="branch in branchSummary" :key="branch.branch"
                        class="border border-slate-100 rounded-xl p-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-slate-800 text-sm">
                                {{ branch.branch }}
                            </h3>

                            <span class="text-xs text-slate-400">
                                {{ branch.totalProducts }} productos
                            </span>
                        </div>

                        <div class="flex items-center gap-2 mt-3">
                            <span class="bg-amber-50 text-amber-700 text-xs font-semibold px-2 py-1 rounded-full">
                                {{ branch.lowStock }} bajo stock
                            </span>

                            <span class="bg-red-50 text-red-700 text-xs font-semibold px-2 py-1 rounded-full">
                                {{ branch.outOfStock }} agotados
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="xl:col-span-2 bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
                <h2 class="text-base font-bold text-slate-800">
                    Movimientos recientes
                </h2>

                <p class="text-xs text-slate-500 mt-1 mb-4">
                    Últimas entradas, salidas y ajustes registrados.
                </p>

                <div class="space-y-3">
                    <div v-for="movement in recentMovements" :key="movement.id"
                        class="flex items-center justify-between gap-4 border border-slate-100 rounded-xl p-4 hover:bg-slate-50 transition">
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-bold px-3 py-1 rounded-full"
                                :class="movementClass(movement.type)">
                                {{ movement.type }}
                            </span>

                            <div>
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ movement.product }}
                                </p>

                                <p class="text-xs text-slate-500 mt-1">
                                    {{ movement.branch }} · {{ movement.user }}
                                </p>
                            </div>
                        </div>

                        <div class="text-right">
                            <p class="text-sm font-bold text-slate-800">
                                {{ movement.quantity }}
                            </p>

                            <p class="text-xs text-slate-400">
                                {{ movement.date }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>