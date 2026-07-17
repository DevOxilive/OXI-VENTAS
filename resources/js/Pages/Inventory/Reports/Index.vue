<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalCard from '@/Components/Cards/GlobalCard.vue'
import { usePermissions } from '@/Composables/usePermissions'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    currentBranch: Object,
})

const { canAny } = usePermissions()
const purchasePermissions = [
    'inventory.purchase-reports.view',
    'inventory.purchase-reports.update',
]

const pageTitle = computed(() => {
    return `Centro de reportes - ${props.currentBranch?.name ?? 'Sucursal'}`
})

const reportGroups = [
    {
        key: 'audits',
        title: 'Reportes de auditoría',
        description: 'Conteos físicos, diferencias, incidencias y validaciones internas.',
        icon: 'fact_check',
        disabled: false,
        routeName: 'inventory.branches.reports.audits',
        permissions: ['audits.physical-counts.reports'],
    },
    {
        key: 'sales',
        title: 'Reportes de ventas',
        description: 'Ventas por periodo, productos, sucursales y comportamiento comercial.',
        icon: 'point_of_sale',
        disabled: true,
    },
    {
        key: 'cash-closures',
        title: 'Reportes de cortes',
        description: 'Cortes de caja registrados, efectivo contado, diferencias y usuario responsable.',
        icon: 'summarize',
        disabled: false,
        routeName: 'ventas.cash-closures.reports',
        usesBranch: false,
        permissions: ['sales.cash-closures.view'],
    },
    {
        key: 'inventory',
        title: 'Reportes de inventario',
        description: 'Caducidades, stock bajo, rotación e inventario general.',
        icon: 'inventory_2',
        disabled: false,
        routeName: 'inventory.branches.reports.inventory',
        permissions: ['inventory.view', 'inventory.branches.view'],
    },
    {
        key: 'movements',
        title: 'Reportes de movimientos',
        description: 'Entradas, salidas, ajustes y trazabilidad por lote.',
        icon: 'sync_alt',
        disabled: false,
        routeName: 'inventory.branches.reports.movements',
        permissions: ['inventory.view', 'inventory.branches.view'],
    },
    {
        key: 'purchases',
        title: 'Reportes de compras',
        description: 'Consulta órdenes generadas y compras completadas.',
        icon: 'shopping_cart_checkout',
        disabled: false,
        routeName: 'inventory.branches.reports.purchase-orders',
        permissions: purchasePermissions,
    },
]

const visibleReportGroups = computed(() => reportGroups.filter((group) => {
    return !group.permissions || canAny(group.permissions)
}))

const toolbarConfig = computed(() => ({
    title: 'Centro de reportes',
    subtitle: 'Consulta información estratégica y operativa del sistema.',
    showSearch: false,
    showRecordsPerPage: false,
    showCounter: false,
    filters: [],
    actions: [],
    tabs: [],
}))

function openReportGroup(group) {
    if (group.disabled || !group.routeName) return

    if (group.usesBranch === false) {
        router.get(route(group.routeName))
        return
    }

    if (!props.currentBranch?.id) return

    router.get(route(group.routeName, {
        branch: props.currentBranch.id,
    }))
}
</script>

<template>
    <Head :title="pageTitle" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar v-bind="toolbarConfig" />
        </template>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <GlobalCard
                v-for="group in visibleReportGroups"
                :key="group.key"
                :title="group.title"
                :description="group.description"
                :icon="group.icon"
                :disabled="group.disabled"
                :badge="group.disabled ? 'Proximamente' : ''"
                badge-variant="neutral"
                @click="openReportGroup(group)"
            />
        </section>
    </PageLayout>
</template>
