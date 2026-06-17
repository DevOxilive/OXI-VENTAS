<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'

import ReportsSummaryCards from '@/Components/Inventory/Reports/ReportsSummaryCards.vue'
import MovementsReportTable from '@/Components/Inventory/Reports/MovementsReportTable.vue'
import ExpirationsReportTable from '@/Components/Inventory/Reports/ExpirationsReportTable.vue'
import RotationReportTable from '@/Components/Inventory/Reports/RotationReportTable.vue'
import AttentionProductsReportTable from '@/Components/Inventory/Reports/AttentionProductsReportTable.vue'

import { getReportsToolbarConfig } from '@/config/ToolbarConfigs/reportsToolbarConfig'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    currentBranch: {
        type: Object,
        required: true,
    },
    activeReport: {
        type: String,
        default: 'dashboard',
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    catalogs: {
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
})

const activeTab = ref(props.activeReport ?? 'dashboard')

const tabs = [
    {
        key: 'dashboard',
        label: 'Dashboard',
        icon: 'dashboard',
        description: 'Resumen general',
    },
    {
        key: 'movements',
        label: 'Movimientos',
        icon: 'sync_alt',
        description: 'Entradas, salidas y ajustes',
    },
    {
        key: 'expirations',
        label: 'Caducidades',
        icon: 'event_busy',
        description: 'Lotes vencidos o por vencer',
    },
    {
        key: 'rotation',
        label: 'Rotación',
        icon: 'trending_up',
        description: 'Consumo y movimiento',
    },
    {
        key: 'attention',
        label: 'Atención',
        icon: 'priority_high',
        description: 'Productos con alertas',
    },
]

const reportsToolbarConfig = computed(() =>
    getReportsToolbarConfig({
        tabs,
        activeTab: activeTab.value,
    })
)

const pageTitle = computed(() => {
    return `Reportes de inventario - ${props.currentBranch?.name ?? 'Sucursal'}`
})

const activeReportTitle = computed(() => {
    return tabs.find((tab) => tab.key === activeTab.value)?.label ?? 'Dashboard'
})

const activeReportDescription = computed(() => {
    return tabs.find((tab) => tab.key === activeTab.value)?.description ?? ''
})

function changeReport(tab) {
    activeTab.value = tab

    router.get(
        route('inventory.branches.reports', {
            branch: props.currentBranch.id,
        }),
        {
            ...props.filters,
            report: tab,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    )
}
</script>

<template>

    <Head :title="pageTitle" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar v-bind="reportsToolbarConfig" @update:active-tab="changeReport" />
        </template>

        <ReportsSummaryCards :summary="summary" class="mb-5" />

        <section class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5">
            <div class="mb-5 flex flex-col gap-1">
                <p class="text-xs font-black uppercase tracking-[0.22em] text-slate-400">
                    {{ currentBranch?.name ?? 'Sin sucursal' }}
                </p>

                <h2 class="text-lg font-black text-slate-900">
                    {{ activeReportTitle }}
                </h2>

                <p class="text-sm text-slate-500">
                    {{ activeReportDescription }}
                </p>
            </div>

            <div v-if="activeTab === 'dashboard'" class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <article class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <p class="text-xs font-black uppercase tracking-wide text-slate-400">
                        Inventario
                    </p>

                    <p class="mt-2 text-3xl font-black text-slate-900">
                        {{ summary?.total_products ?? 0 }}
                    </p>

                    <p class="mt-1 text-sm text-slate-500">
                        Productos registrados en esta sucursal.
                    </p>
                </article>

                <article class="rounded-2xl border border-amber-200 bg-amber-50 p-5">
                    <p class="text-xs font-black uppercase tracking-wide text-amber-600">
                        Atención
                    </p>

                    <p class="mt-2 text-3xl font-black text-amber-700">
                        {{ summary?.attention_products ?? 0 }}
                    </p>

                    <p class="mt-1 text-sm text-amber-700/80">
                        Productos con stock bajo, sin movimiento o riesgo de caducidad.
                    </p>
                </article>

                <article class="rounded-2xl border border-red-200 bg-red-50 p-5">
                    <p class="text-xs font-black uppercase tracking-wide text-red-600">
                        Caducidades
                    </p>

                    <p class="mt-2 text-3xl font-black text-red-700">
                        {{ summary?.expired_batches ?? 0 }}
                    </p>

                    <p class="mt-1 text-sm text-red-700/80">
                        Lotes caducados detectados en esta sucursal.
                    </p>
                </article>
            </div>

            <MovementsReportTable v-if="activeTab === 'movements'" :rows="reports?.movements ?? []" />

            <ExpirationsReportTable v-if="activeTab === 'expirations'" :rows="reports?.expirations ?? []" />

            <RotationReportTable v-if="activeTab === 'rotation'" :rows="reports?.rotation ?? []" />

            <AttentionProductsReportTable v-if="activeTab === 'attention'" :rows="reports?.attentionProducts ?? []" />
        </section>
    </PageLayout>
</template>