<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive } from 'vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalCard from '@/Components/Cards/GlobalCard.vue'
import ReportTable from '@/Components/Inventory/Reports/ReportTable.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    currentBranch: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    reports: {
        type: Object,
        default: () => ({}),
    },
    reportHistory: {
        type: Array,
        default: () => [],
    },
    activeReport: {
        type: String,
        default: 'dashboard',
    },
})

const REPORT_TYPES = [
    { id: 'dashboard', name: 'Dashboard', dataKey: null },
    { id: 'movements', name: 'Movimientos', dataKey: 'movements' },
    { id: 'expirations', name: 'Caducidades', dataKey: 'expirations' },
    { id: 'rotation', name: 'Rotación', dataKey: 'rotation' },
    { id: 'attention', name: 'Productos de atención', dataKey: 'attentionProducts' },
]

const filtersState = reactive({
    reportType: props.activeReport ?? 'dashboard',
    dateFrom: props.filters?.date_from ?? '',
    dateTo: props.filters?.date_to ?? '',
    search: props.filters?.search ?? '',
})


const pageTitle = computed(() => {
    return `Reportes de inventario - ${props.currentBranch?.name ?? 'Sucursal'}`
})

const currentReport = computed(() => {
    return REPORT_TYPES.find((report) => report.id === filtersState.reportType) ?? REPORT_TYPES[0]
})

const toolbarConfig = computed(() => ({
    title: 'Reportes de inventario',
    subtitle: 'Consulta caducidades, stock bajo, mermas y estado operativo del inventario.',
    backButton: true,
    backLabel: 'Regresar',

    showSearch: true,
    searchPlaceholder: 'Buscar producto, lote o categoría...',

    showRecordsPerPage: false,
    showCounter: false,

    filters: [
        {
            key: 'reportType',
            label: 'Tipo de reporte',
            placeholder: 'Tipo de reporte',
            value: filtersState.reportType,
            options: REPORT_TYPES,
            optionLabel: 'name',
            optionValue: 'id',
        },
        {
            key: 'dateFrom',
            label: 'Fecha inicio',
            type: 'date',
            value: filtersState.dateFrom,
        },
        {
            key: 'dateTo',
            label: 'Fecha fin',
            type: 'date',
            value: filtersState.dateTo,
        },
    ],

    actions: [
        {
            id: 'generate',
            label: 'Consultar',
            icon: 'search',
            variant: 'primary',
        },
        {
            id: 'excel',
            label: 'Excel',
            icon: 'table_view',
            variant: 'green',
        },
        {
            id: 'pdf',
            label: 'PDF',
            icon: 'picture_as_pdf',
            variant: 'red',
        },
    ],

    tabs: [],
}))

const summaryCards = computed(() => [
    {
        key: 'expired_batches',
        title: String(props.summary?.expired_batches ?? 0),
        subtitle: 'Caducados',
        description: 'Lotes vencidos detectados en inventario.',
        icon: 'warning',
        badge: 'Crítico',
        badgeVariant: 'danger',
    },
    {
        key: 'near_expiration_batches',
        title: String(props.summary?.near_expiration_batches ?? 0),
        subtitle: 'Por vencer',
        description: 'Lotes próximos a caducar dentro del periodo.',
        icon: 'event_busy',
        badge: 'Prevención',
        badgeVariant: 'warning',
    },
    {
        key: 'low_stock',
        title: String(props.summary?.low_stock ?? 0),
        subtitle: 'Stock bajo',
        description: 'Productos por debajo del mínimo configurado.',
        icon: 'inventory',
        badge: 'Atención',
        badgeVariant: 'warning',
    },
    {
        key: 'estimated_loss',
        title: formatCurrency(props.summary?.estimated_loss ?? 0),
        subtitle: 'Merma estimada',
        description: 'Costo estimado asociado a productos caducados.',
        icon: 'payments',
        badge: 'Impacto',
        badgeVariant: 'danger',
    },
])

const activeReportData = computed(() => {
    return props.reports?.[currentReport.value.dataKey] ?? null
})

const tableRows = computed(() => {
    return activeReportData.value?.data ?? activeReportData.value ?? []
})

const tablePagination = computed(() => {
    return activeReportData.value?.data ? activeReportData.value : null
})

function formatCurrency(value) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(Number(value || 0))
}

function backToReportsCenter() {
    router.get(route('inventory.reports'))
}

function updateSearch(value) {
    filtersState.search = value
}

function updateFilter({ key, value }) {
    filtersState[key] = value
}

function getRequestFilters() {
    return {
        report: filtersState.reportType || undefined,
        search: filtersState.search || undefined,
        date_from: filtersState.dateFrom || undefined,
        date_to: filtersState.dateTo || undefined,
    }
}

function reloadReport(pageUrl = null) {
    router.get(
        pageUrl ?? route('inventory.branches.reports', {
            branch: props.currentBranch.id,
        }),
        getRequestFilters(),
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    )
}

function handleToolbarAction(action) {
    const actionId = typeof action === 'string' ? action : action?.id

    if (actionId === 'generate') {
        reloadReport()
        return
    }

    if (actionId === 'excel') {
        console.log('Exportar Excel', getRequestFilters())
        return
    }

    if (actionId === 'pdf') {
        console.log('Exportar PDF', getRequestFilters())
    }
}
</script>

<template>

    <Head :title="pageTitle" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar v-bind="toolbarConfig" :search="filtersState.search" @back="backToReportsCenter"
                @update:search="updateSearch" @update:filter="updateFilter" @action="handleToolbarAction" />
        </template>

        <section class="space-y-5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <GlobalCard v-for="card in summaryCards" :key="card.key" :title="card.title" :subtitle="card.subtitle"
                    :description="card.description" :icon="card.icon" :badge="card.badge"
                    :badge-variant="card.badgeVariant" :clickable="false" />
            </div>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-5 flex flex-col gap-1">
                    <p class="text-xs font-black uppercase tracking-[0.22em] text-slate-400">
                        {{ currentBranch?.name ?? 'Sin sucursal' }}
                    </p>

                    <h2 class="text-lg font-black text-slate-900">
                        {{ currentReport.name }}
                    </h2>

                    <p class="text-sm text-slate-500">
                        Información filtrada según el tipo de reporte seleccionado.
                    </p>
                </div>

                <ReportTable :rows="tableRows" :report-type="filtersState.reportType" :pagination="tablePagination"
                    @page-change="reloadReport" />
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4">
                    <h2 class="text-lg font-black text-slate-900">
                        Historial de reportes generados
                    </h2>

                    <p class="text-sm text-slate-500">
                        Consulta los archivos generados anteriormente.
                    </p>
                </div>

                <div v-if="!reportHistory.length"
                    class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                    <p class="text-sm font-bold text-slate-500">
                        Aún no hay reportes generados.
                    </p>

                    <p class="mt-1 text-xs text-slate-400">
                        Cuando generes un PDF o Excel, aparecerá en este historial.
                    </p>
                </div>
            </section>
        </section>
    </PageLayout>
</template>