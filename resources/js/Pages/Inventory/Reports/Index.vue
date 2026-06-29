<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalCard from '@/Components/Cards/GlobalCard.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    currentBranch: Object,
})

const pageTitle = computed(() => {
    return `Centro de reportes - ${props.currentBranch?.name ?? 'Sucursal'}`
})

const reportGroups = [
    {
        key: 'audits',
        title: 'Reportes de auditoría',
        description: 'Conteos fisicos, diferencias, incidencias y validaciones internas.',
        icon: 'fact_check',
        disabled: false,
        routeName: 'inventory.branches.reports.audits',
    },
    {
        key: 'sales',
        title: 'Reportes de ventas',
        description: 'Ventas por periodo, productos, sucursales y comportamiento comercial.',
        icon: 'point_of_sale',
        disabled: true,
    },
    {
        key: 'inventory',
        title: 'Reportes de inventario',
        description: 'Caducidades, stock bajo, rotacion e inventario general.',
        icon: 'inventory_2',
        disabled: false,
        routeName: 'inventory.branches.reports.inventory',
    },
    {
        key: 'movements',
        title: 'Reportes de movimientos',
        description: 'Entradas, salidas, ajustes y trazabilidad por lote.',
        icon: 'sync_alt',
        disabled: false,
        routeName: 'inventory.branches.reports.movements',
    },
]

const toolbarConfig = computed(() => ({
    title: 'Centro de reportes',
    subtitle: 'Consulta informacion estrategica y operativa del sistema.',
    showSearch: false,
    showRecordsPerPage: false,
    showCounter: false,
    filters: [],
    actions: [],
    tabs: [],
}))

function openReportGroup(group) {
    if (group.disabled || !group.routeName || !props.currentBranch?.id) return

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

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <GlobalCard
                v-for="group in reportGroups"
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
