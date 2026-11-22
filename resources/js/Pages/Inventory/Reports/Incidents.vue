<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed, reactive, watch } from 'vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    currentBranch: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    incidents: { type: [Object, Array], default: () => [] },
})

const { handlePageChange } = useGlobalTablePagination()
const filtersState = reactive({
    search: props.filters?.search ?? '',
    perPage: Number(props.filters?.per_page ?? 25),
})
let refreshTimeout = null

const rows = computed(() => props.incidents?.data ?? props.incidents ?? [])
const pagination = computed(() => props.incidents?.data ? props.incidents : null)
const totalPending = computed(() => rows.value.reduce((total, row) => total + Math.abs(Number(row.pending_quantity || 0)), 0))

const toolbarConfig = computed(() => ({
    icon: 'warning',
    title: 'Incidencias de inventario',
    subtitle: `Ventas registradas sin existencia suficiente en ${props.currentBranch?.name ?? 'esta sucursal'}. El faltante queda pendiente de una carga posterior.`,
    backButton: true,
    showSearch: true,
    searchPlaceholder: 'Buscar producto o código...',
    showRecordsPerPage: true,
    showCounter: true,
    recordCount: pagination.value?.total ?? rows.value.length,
    filters: [],
    actions: [],
}))

const tableConfig = {
    columns: [
        { key: 'product', label: 'Producto', format: 'text', mobileLabel: 'Producto', mobileDisplay: true, width: '280px' },
        { key: 'department', label: 'Departamento', format: 'text', mobileLabel: 'Departamento', mobileDisplay: true, width: '190px' },
        { key: 'category', label: 'Categoría', format: 'text', mobileLabel: 'Categoría', mobileDisplay: true, width: '180px' },
        { key: 'pending_quantity', label: 'Pendiente sin lote', format: 'number', mobileLabel: 'Pendiente', mobileDisplay: true, width: '155px' },
        { key: 'first_incident_at', label: 'Primer faltante', format: 'datetime', mobileLabel: 'Primer faltante', mobileDisplay: true, width: '180px' },
        { key: 'first_incident_user', label: 'Usuario inicial', format: 'text', mobileLabel: 'Usuario', mobileDisplay: true, width: '190px' },
        { key: 'continued_sales_count', label: 'Ventas posteriores', format: 'number', mobileLabel: 'Ventas posteriores', mobileDisplay: true, width: '150px' },
        { key: 'last_incident_at', label: 'Última venta pendiente', format: 'datetime', mobileLabel: 'Última venta', mobileDisplay: true, width: '180px' },
        { key: 'status_label', label: 'Estado', format: 'badge', mobileLabel: 'Estado', mobileBadge: true, formatOptions: { statusMap: { 'Carga pendiente': 'red' } }, width: '145px' },
    ],
    actions: [],
    mobileCardHeaderField: 'product',
    noDataMessage: 'No hay incidencias pendientes de carga para esta sucursal.',
    rowKey: 'id',
    hoverEffect: true,
}

function reload(pageUrl = null) {
    router.get(pageUrl ?? route('inventory.branches.reports.incidents', { branch: props.currentBranch.id }), {
        search: filtersState.search || undefined,
        per_page: filtersState.perPage || 25,
    }, { preserveScroll: true, preserveState: true, replace: true })
}

function backToReports() {
    router.get(route('inventory.reports.select', { report: 'incidents' }))
}

watch(filtersState, () => {
    window.clearTimeout(refreshTimeout)
    refreshTimeout = window.setTimeout(() => reload(), 350)
}, { deep: true })
</script>

<template>
    <Head :title="`Incidencias de inventario - ${currentBranch.name}`" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
                :search="filtersState.search"
                :records-per-page="filtersState.perPage"
                @back="backToReports"
                @update:search="filtersState.search = $event"
                @update:records-per-page="filtersState.perPage = $event"
            />
        </template>

        <section class="space-y-5">
            <div class="grid gap-3 sm:grid-cols-2">
                <article class="rounded-2xl border border-primary/25 bg-primary/5 px-5 py-4">
                    <p class="text-xs font-bold uppercase tracking-wide text-primary/70">Productos con incidencia</p>
                    <p class="mt-1 text-3xl font-black text-primary">{{ pagination?.total ?? rows.length }}</p>
                </article>
                <article class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-red-800">
                    <p class="text-xs font-bold uppercase tracking-wide text-red-700/70">Piezas pendientes en esta página</p>
                    <p class="mt-1 text-3xl font-black">{{ totalPending }}</p>
                </article>
            </div>

            <GlobalTable
                :items="rows"
                :pagination="pagination"
                v-bind="tableConfig"
                @page-change="handlePageChange"
            />
        </section>
    </PageLayout>
</template>
