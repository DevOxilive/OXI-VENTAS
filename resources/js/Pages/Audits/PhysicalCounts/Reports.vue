<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    branch: { type: Object, default: null },
    branches: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    audits: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    reportRows: { type: Array, default: () => [] },
    reportPagination: { type: Object, default: null },
    userSummary: { type: Array, default: () => [] },
    categorySummary: { type: Array, default: () => [] },
    topDifferences: { type: Array, default: () => [] },
    filterLabels: { type: Object, default: () => ({}) },
})

const form = reactive({
    branch: props.filters.branch || props.branch?.slug || '',
    physical_count_id: props.filters.physical_count_id || '',
    user_id: props.filters.user_id || '',
    category_id: props.filters.category_id || '',
    report_date: props.filters.report_date || '',
    date_scope: props.filters.date_scope || 'day',
    status: props.filters.status || '',
    report_type: props.filters.report_type || 'summary',
    per_page: Number(props.filters.per_page || 25),
})

const searchDraft = ref(props.filters.search || '')
const syncingFromServer = ref(false)
let filterTimeout = null
let searchTimeout = null

const toolbarConfig = computed(() => ({
    title: 'Reportes de auditoria',
    subtitle: `Sucursal: ${props.branch?.name ?? 'Sin sucursal'}`,
    actions: [
        { id: 'back', label: 'Volver', icon: 'arrow_back', variant: 'slate' },
        { id: 'clear', label: 'Limpiar', icon: 'ink_eraser', variant: 'slate' },
        { id: 'excel', label: 'Excel', icon: 'table_view', variant: 'blue' },
        { id: 'pdf', label: 'PDF', icon: 'picture_as_pdf', variant: 'red' },
    ],
    tabs: [
        { key: 'summary', label: 'Resumen', icon: 'dashboard' },
        { key: 'detail', label: 'Detalle', icon: 'list_alt' },
        { key: 'users', label: 'Usuarios', icon: 'group' },
        { key: 'categories', label: 'Categorias', icon: 'category' },
        { key: 'differences', label: 'Diferencias', icon: 'analytics' },
    ],
}))

const currentViewMeta = computed(() => ({
    summary: {
        title: 'Resumen general',
        description: 'Te muestra el estado general de la auditoria: cuantos productos ya se contaron, cuantos faltan y como va el resultado.',
        panelTitle: 'Vista ejecutiva',
        panelDescription: 'Sirve para saber en segundos si la auditoria va avanzando bien o si ya hay diferencias que requieren revision.',
    },
    detail: {
        title: 'Detalle de productos',
        description: 'Te muestra producto por producto lo encontrado en auditoria para revisar codigos escaneados, conteos y diferencias.',
        panelTitle: 'Revision operativa',
        panelDescription: 'Sirve para revisar el resultado real de cada producto, que codigo se escaneo y quienes participaron en ese conteo.',
    },
    users: {
        title: 'Rendimiento por usuario',
        description: 'Te muestra cuanto ha capturado cada usuario para saber quien esta trabajando y cuanta informacion ha registrado.',
        panelTitle: 'Seguimiento del equipo',
        panelDescription: 'Sirve para validar participacion, carga de trabajo y productividad de cada usuario dentro de la auditoria.',
    },
    categories: {
        title: 'Resultado por categoria',
        description: 'Te muestra en que categorias hay mas productos pendientes, faltantes o correctos.',
        panelTitle: 'Lectura por categoria',
        panelDescription: 'Sirve para detectar rapido que lineas de producto necesitan mas atencion o una segunda revision.',
    },
    differences: {
        title: 'Ranking de diferencias',
        description: 'Te muestra primero los productos con mayor diferencia contra inventario para atacar los errores mas fuertes.',
        panelTitle: 'Prioridad de correccion',
        panelDescription: 'Sirve para empezar por los productos con mayor impacto y corregir antes lo que mas mueve el inventario.',
    },
}[form.report_type]))

const scopeHelp = computed(() => ({
    day: 'Toma la fecha exacta que selecciones.',
    month: 'Toma todo el mes de la fecha seleccionada.',
    year: 'Toma todo el ano de la fecha seleccionada.',
}[form.date_scope]))

const resultCards = computed(() => [
    { label: 'Auditorias', value: props.summary.audits ?? 0, tone: 'text-slate-900' },
    { label: 'Registros', value: props.summary.records ?? 0, tone: 'text-slate-900' },
    { label: 'Contados', value: props.summary.counted_products ?? 0, tone: 'text-blue-700' },
    { label: 'No encontrados', value: props.summary.pending_products ?? 0, tone: 'text-slate-700' },
    { label: 'Faltantes', value: props.summary.missing_products ?? 0, tone: 'text-red-600' },
    { label: 'Sobrantes', value: props.summary.surplus_products ?? 0, tone: 'text-amber-600' },
    { label: 'Correctos', value: props.summary.matched_products ?? 0, tone: 'text-green-600' },
    { label: 'Usuarios', value: props.summary.participants ?? 0, tone: 'text-slate-900' },
])

const reportTableItems = computed(() =>
    props.reportRows.map((row) => ({
        ...row,
        participantsLabel: Array.isArray(row.participants) ? row.participants.join(', ') : 'Sin registros',
        differenceLabel: row.difference ?? '-',
    }))
)

const detailTableConfig = {
    mobileCardHeaderField: 'product_name',
    noDataMessage: 'No hay resultados para los filtros seleccionados.',
    columns: [
        { key: 'product_name', label: 'Producto' },
        { key: 'scanned_code', label: 'Codigo' },
        { key: 'audit_name', label: 'Auditoria' },
        { key: 'status_label', label: 'Resultado' },
        { key: 'system_stock', label: 'Stock sistema', format: 'number' },
        { key: 'counted_stock', label: 'Conteo fisico', format: 'number' },
        { key: 'differenceLabel', label: 'Diferencia' },
        { key: 'participantsLabel', label: 'Usuarios' },
        { key: 'audit_date', label: 'Fecha', format: 'date' },
    ],
    actions: [],
}

const usersTableConfig = {
    mobileCardHeaderField: 'user_name',
    noDataMessage: 'Sin resumen por usuario.',
    columns: [
        { key: 'user_name', label: 'Usuario' },
        { key: 'records', label: 'Registros', format: 'number' },
        { key: 'products', label: 'Productos', format: 'number' },
        { key: 'counted_stock', label: 'Contado', format: 'number' },
        { key: 'damaged_stock', label: 'Danado', format: 'number' },
        { key: 'expired_stock', label: 'Caducado', format: 'number' },
    ],
    actions: [],
}

const categoriesTableConfig = {
    mobileCardHeaderField: 'category_name',
    noDataMessage: 'Sin resumen por categoria.',
    columns: [
        { key: 'category_name', label: 'Categoria' },
        { key: 'products', label: 'Productos', format: 'number' },
        { key: 'counted_products', label: 'Contados', format: 'number' },
        { key: 'pending_products', label: 'Pendientes', format: 'number' },
        { key: 'missing_products', label: 'Faltantes', format: 'number' },
        { key: 'surplus_products', label: 'Sobrantes', format: 'number' },
        { key: 'matched_products', label: 'Correctos', format: 'number' },
    ],
    actions: [],
}

const differencesTableConfig = {
    mobileCardHeaderField: 'product_name',
    noDataMessage: 'Sin diferencias relevantes.',
    columns: [
        { key: 'product_name', label: 'Producto' },
        { key: 'category_name', label: 'Categoria' },
        { key: 'scanned_code', label: 'Codigo' },
        { key: 'system_stock', label: 'Sistema', format: 'number' },
        { key: 'counted_stock', label: 'Conteo', format: 'number' },
        { key: 'differenceLabel', label: 'Diferencia' },
        { key: 'status_label', label: 'Resultado' },
    ],
    actions: [],
}

const exportExcelUrl = computed(() => route('audits.physical-counts.reports.export-excel', buildQuery()))
const exportPdfUrl = computed(() => route('audits.physical-counts.reports.export-pdf', buildQuery()))

const activeFilterSummary = computed(() => ({
    audit: props.filterLabels.audit || 'Todas',
    user: props.filterLabels.user || 'Todos',
    category: props.filterLabels.category || 'Todas',
    status: props.filterLabels.status || 'Todos',
    report_date: props.filterLabels.report_date || 'Sin fecha',
    date_scope: props.filterLabels.date_scope || 'Por dia',
    search: props.filterLabels.search || 'Sin filtro',
}))

function buildQuery(overrides = {}) {
    return {
        branch: form.branch || props.branch?.slug || '',
        physical_count_id: form.physical_count_id || '',
        user_id: form.user_id || '',
        category_id: form.category_id || '',
        report_date: form.report_date || '',
        date_scope: form.date_scope || 'day',
        status: form.status || '',
        search: searchDraft.value || '',
        report_type: form.report_type || 'summary',
        per_page: form.per_page || 25,
        ...overrides,
    }
}

function applyFilters(overrides = {}) {
    router.get(route('audits.physical-counts.reports'), buildQuery({ page: 1, ...overrides }), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        only: [
            'summary',
            'reportRows',
            'reportPagination',
            'audits',
            'filters',
            'userSummary',
            'categorySummary',
            'topDifferences',
            'filterLabels',
        ],
    })
}

function scheduleFilterReload(delay = 250) {
    if (syncingFromServer.value) return

    clearTimeout(filterTimeout)
    filterTimeout = setTimeout(() => {
        applyFilters()
    }, delay)
}

function clearFilters() {
    syncingFromServer.value = true
    form.physical_count_id = ''
    form.user_id = ''
    form.category_id = ''
    form.report_date = ''
    form.date_scope = 'day'
    form.status = ''
    form.report_type = 'summary'
    form.per_page = 25
    form.branch = props.branch?.slug || ''
    searchDraft.value = ''
    syncingFromServer.value = false

    router.get(route('audits.physical-counts.reports'), { branch: form.branch }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        only: [
            'summary',
            'reportRows',
            'reportPagination',
            'audits',
            'filters',
            'userSummary',
            'categorySummary',
            'topDifferences',
            'filterLabels',
        ],
    })
}

function handleToolbarAction(action) {
    if (action === 'back') {
        router.visit(route('audits.physical-counts.index', { branch: props.branch?.slug }))
        return
    }

    if (action === 'clear') {
        clearFilters()
        return
    }

    if (action === 'excel') {
        window.location.href = exportExcelUrl.value
        return
    }

    if (action === 'pdf') {
        window.location.href = exportPdfUrl.value
    }
}

function handlePageChange(url) {
    router.visit(url, {
        preserveScroll: true,
        preserveState: true,
        only: [
            'summary',
            'reportRows',
            'reportPagination',
            'audits',
            'filters',
            'userSummary',
            'categorySummary',
            'topDifferences',
            'filterLabels',
        ],
    })
}

function reloadReports() {
    router.reload({
        only: [
            'summary',
            'reportRows',
            'reportPagination',
            'audits',
            'filters',
            'userSummary',
            'categorySummary',
            'topDifferences',
            'filterLabels',
        ],
        preserveScroll: true,
        preserveState: true,
    })
}

watch(
    () => [
        form.branch,
        form.physical_count_id,
        form.user_id,
        form.category_id,
        form.report_date,
        form.date_scope,
        form.status,
        form.report_type,
        form.per_page,
    ],
    ([branch]) => {
        if (syncingFromServer.value) return

        if (branch !== props.filters.branch && form.physical_count_id) {
            form.physical_count_id = ''
        }

        scheduleFilterReload(250)
    }
)

watch(searchDraft, () => {
    clearTimeout(searchTimeout)
    if (syncingFromServer.value) return

    searchTimeout = setTimeout(() => {
        applyFilters()
    }, 450)
})

watch(
    () => props.filters,
    (filters) => {
        syncingFromServer.value = true
        form.branch = filters.branch || props.branch?.slug || ''
        form.physical_count_id = filters.physical_count_id || ''
        form.user_id = filters.user_id || ''
        form.category_id = filters.category_id || ''
        form.report_date = filters.report_date || ''
        form.date_scope = filters.date_scope || 'day'
        form.status = filters.status || ''
        form.report_type = filters.report_type || 'summary'
        form.per_page = Number(filters.per_page || 25)
        searchDraft.value = filters.search || ''
        syncingFromServer.value = false
    },
    { deep: true }
)

onMounted(() => {
    if (!window.Echo) return

    window.Echo.channel('audits')
        .listen('.PhysicalCountChanged', () => {
            reloadReports()
        })
})

onBeforeUnmount(() => {
    clearTimeout(filterTimeout)
    clearTimeout(searchTimeout)

    if (!window.Echo) return
    window.Echo.leave('audits')
})
</script>

<template>
    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
                :active-tab="form.report_type"
                :show-search="false"
                :show-records-per-page="false"
                :show-counter="false"
                @update:active-tab="form.report_type = $event"
                @action="handleToolbarAction"
            />
        </template>

        <div class="space-y-6">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="grid grid-cols-1 gap-4 xl:grid-cols-6">
                    <select v-model="form.branch" class="rounded-xl border-slate-300 text-sm">
                        <option value="">Sucursal</option>
                        <option v-for="item in branches" :key="item.id" :value="item.slug">
                            {{ item.name }}
                        </option>
                    </select>

                    <select v-model="form.physical_count_id" class="rounded-xl border-slate-300 text-sm">
                        <option value="">Auditoria</option>
                        <option v-for="audit in audits" :key="audit.id" :value="audit.id">
                            {{ audit.name }} - {{ audit.folio }}
                        </option>
                    </select>

                    <select v-model="form.user_id" class="rounded-xl border-slate-300 text-sm">
                        <option value="">Usuario</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>

                    <select v-model="form.category_id" class="rounded-xl border-slate-300 text-sm">
                        <option value="">Categoria</option>
                        <option v-for="category in categories" :key="category.id" :value="category.id">
                            {{ category.name }}
                        </option>
                    </select>

                    <select v-model="form.date_scope" class="rounded-xl border-slate-300 text-sm">
                        <option value="day">Por dia</option>
                        <option value="month">Por mes</option>
                        <option value="year">Por ano</option>
                    </select>

                    <input
                        v-model="form.report_date"
                        type="date"
                        class="rounded-xl border-slate-300 text-sm"
                    >

                    <select v-model="form.status" class="rounded-xl border-slate-300 text-sm">
                        <option value="">Resultado</option>
                        <option value="found">Encontrados</option>
                        <option value="not_found">No encontrados</option>
                        <option value="counted">Contados</option>
                        <option value="missing">Faltantes</option>
                        <option value="not_missing">No faltantes</option>
                        <option value="surplus">Sobrantes</option>
                        <option value="matched">Correctos</option>
                    </select>

                    <select v-model="form.per_page" class="rounded-xl border-slate-300 text-sm">
                        <option :value="25">25 filas</option>
                        <option :value="50">50 filas</option>
                        <option :value="100">100 filas</option>
                    </select>

                    <input
                        v-model="searchDraft"
                        type="search"
                        class="rounded-xl border-slate-300 text-sm xl:col-span-4"
                        placeholder="Buscar por codigo escaneado, nombre del producto, folio o usuario"
                    >
                </div>

                <p class="mt-3 text-xs text-slate-500">
                    {{ scopeHelp }}
                </p>
            </section>

            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">
                        {{ currentViewMeta.title }}
                    </h2>
                    <p class="mt-2 text-sm text-slate-600">
                        {{ currentViewMeta.description }}
                    </p>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">
                        Que estas filtrando
                    </h2>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm text-slate-600">
                        <div class="rounded-xl bg-slate-50 p-3">Auditoria: {{ activeFilterSummary.audit }}</div>
                        <div class="rounded-xl bg-slate-50 p-3">Usuario: {{ activeFilterSummary.user }}</div>
                        <div class="rounded-xl bg-slate-50 p-3">Categoria: {{ activeFilterSummary.category }}</div>
                        <div class="rounded-xl bg-slate-50 p-3">Resultado: {{ activeFilterSummary.status }}</div>
                        <div class="rounded-xl bg-slate-50 p-3">Fecha: {{ activeFilterSummary.report_date }}</div>
                        <div class="rounded-xl bg-slate-50 p-3">Lectura: {{ activeFilterSummary.date_scope }}</div>
                        <div class="col-span-2 rounded-xl bg-slate-50 p-3">Busqueda: {{ activeFilterSummary.search }}</div>
                    </div>
                </div>
            </section>

            <section v-if="form.report_type === 'summary'" class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <div
                    v-for="card in resultCards"
                    :key="card.label"
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p class="text-xs text-slate-500">{{ card.label }}</p>
                    <p class="mt-2 text-2xl font-black" :class="card.tone">{{ card.value }}</p>
                </div>
            </section>

            <section v-if="form.report_type === 'detail'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 rounded-2xl bg-slate-50 p-4">
                    <h3 class="text-sm font-black text-slate-900">{{ currentViewMeta.panelTitle }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ currentViewMeta.panelDescription }}</p>
                </div>
                <GlobalTable
                    :items="reportTableItems"
                    v-bind="detailTableConfig"
                    :pagination="reportPagination"
                    row-key="id"
                    @page-change="handlePageChange"
                />
            </section>

            <section v-if="form.report_type === 'users'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 rounded-2xl bg-slate-50 p-4">
                    <h3 class="text-sm font-black text-slate-900">{{ currentViewMeta.panelTitle }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ currentViewMeta.panelDescription }}</p>
                </div>
                <GlobalTable
                    :items="userSummary"
                    v-bind="usersTableConfig"
                    row-key="user_name"
                    :show-pagination="false"
                />
            </section>

            <section v-if="form.report_type === 'categories'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 rounded-2xl bg-slate-50 p-4">
                    <h3 class="text-sm font-black text-slate-900">{{ currentViewMeta.panelTitle }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ currentViewMeta.panelDescription }}</p>
                </div>
                <GlobalTable
                    :items="categorySummary"
                    v-bind="categoriesTableConfig"
                    row-key="category_name"
                    :show-pagination="false"
                />
            </section>

            <section v-if="form.report_type === 'differences'" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="mb-4 rounded-2xl bg-slate-50 p-4">
                    <h3 class="text-sm font-black text-slate-900">{{ currentViewMeta.panelTitle }}</h3>
                    <p class="mt-1 text-sm text-slate-600">{{ currentViewMeta.panelDescription }}</p>
                </div>
                <GlobalTable
                    :items="topDifferences.map((row) => ({ ...row, differenceLabel: row.difference ?? '-' }))"
                    v-bind="differencesTableConfig"
                    row-key="id"
                    :show-pagination="false"
                />
            </section>
        </div>
    </PageLayout>
</template>
