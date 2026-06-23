<script setup>
import { computed, onBeforeUnmount, onMounted, reactive } from 'vue'
import { router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    branch: Object,
    branches: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    audits: { type: Array, default: () => [] },
    users: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    reportRows: { type: Array, default: () => [] },
})

const form = reactive({
    branch: props.filters.branch || props.branch?.slug || '',
    physical_count_id: props.filters.physical_count_id || '',
    user_id: props.filters.user_id || '',
    category_id: props.filters.category_id || '',
    year: props.filters.year || '',
    month: props.filters.month || '',
    day: props.filters.day || '',
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
    holiday_start: props.filters.holiday_start || '',
    holiday_end: props.filters.holiday_end || '',
    status: props.filters.status || '',
    search: props.filters.search || '',
})

const toolbarConfig = computed(() => ({
    title: 'Reportes de auditoria',
    subtitle: `Sucursal: ${props.branch?.name ?? 'Sin sucursal'}`,
    actions: [
        { id: 'back', label: 'Volver', icon: 'arrow_back', variant: 'slate' },
        { id: 'apply', label: 'Aplicar filtros', icon: 'filter_alt', variant: 'green' },
        { id: 'clear', label: 'Limpiar', icon: 'ink_eraser', variant: 'slate' },
        { id: 'excel', label: 'Excel', icon: 'table_view', variant: 'blue' },
        { id: 'pdf', label: 'PDF', icon: 'picture_as_pdf', variant: 'red' },
    ],
}))

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

const reportTableConfig = {
    mobileCardHeaderField: 'product_name',
    noDataMessage: 'No hay resultados para los filtros seleccionados.',
    columns: [
        { key: 'audit_name', label: 'Auditoria' },
        { key: 'folio', label: 'Folio' },
        { key: 'audit_date', label: 'Fecha', format: 'date' },
        { key: 'row_type_label', label: 'Tipo' },
        { key: 'status_label', label: 'Resultado' },
        { key: 'product_name', label: 'Producto' },
        { key: 'category_name', label: 'Categoria' },
        { key: 'subcategory_name', label: 'Subcategoria' },
        { key: 'scanned_code', label: 'Codigo' },
        { key: 'system_stock', label: 'Stock sistema', format: 'number' },
        { key: 'counted_stock', label: 'Conteo fisico', format: 'number' },
        { key: 'damaged_stock', label: 'Danado', format: 'number' },
        { key: 'expired_stock', label: 'Caducado', format: 'number' },
        { key: 'differenceLabel', label: 'Diferencia' },
        { key: 'participantsLabel', label: 'Usuarios' },
    ],
    actions: [],
}

const exportExcelUrl = computed(() => route('audits.physical-counts.reports.export-excel', { ...form }))
const exportPdfUrl = computed(() => route('audits.physical-counts.reports.export-pdf', { ...form }))

function applyFilters() {
    router.get(route('audits.physical-counts.reports'), { ...form }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    })
}

function clearFilters() {
    form.physical_count_id = ''
    form.user_id = ''
    form.category_id = ''
    form.year = ''
    form.month = ''
    form.day = ''
    form.start_date = ''
    form.end_date = ''
    form.holiday_start = ''
    form.holiday_end = ''
    form.status = ''
    form.search = ''
    form.branch = props.branch?.slug || ''
    applyFilters()
}

function handleToolbarAction(action) {
    if (action === 'back') {
        router.visit(route('audits.physical-counts.index', { branch: props.branch?.slug }))
        return
    }

    if (action === 'apply') {
        applyFilters()
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

function reloadReports() {
    router.reload({
        only: ['summary', 'reportRows', 'audits'],
        preserveScroll: true,
        preserveState: true,
    })
}

onMounted(() => {
    if (!window.Echo) return

    window.Echo.channel('audits')
        .listen('.PhysicalCountChanged', () => {
            reloadReports()
        })
})

onBeforeUnmount(() => {
    if (!window.Echo) return
    window.Echo.leave('audits')
})
</script>

<template>
    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
                :show-search="false"
                :show-records-per-page="false"
                :show-counter="false"
                @action="handleToolbarAction"
            />
        </template>

        <div class="space-y-6">
            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.35fr_0.65fr]">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-black text-slate-900">
                                Filtros del reporte
                            </h2>
                            <p class="mt-1 text-sm text-slate-500">
                                Define exactamente que quieres ver y descargar.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 space-y-5">
                        <div>
                            <p class="mb-3 text-sm font-bold text-slate-800">Contexto</p>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
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
                            </div>
                        </div>

                        <div>
                            <p class="mb-3 text-sm font-bold text-slate-800">Periodo</p>
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <input v-model="form.year" type="number" class="rounded-xl border-slate-300 text-sm" placeholder="Ano" />
                                <input v-model="form.month" type="number" min="1" max="12" class="rounded-xl border-slate-300 text-sm" placeholder="Mes" />
                                <input v-model="form.day" type="number" min="1" max="31" class="rounded-xl border-slate-300 text-sm" placeholder="Dia" />
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
                                <input v-model="form.start_date" type="date" class="rounded-xl border-slate-300 text-sm" />
                                <input v-model="form.end_date" type="date" class="rounded-xl border-slate-300 text-sm" />
                                <input v-model="form.holiday_start" type="date" class="rounded-xl border-slate-300 text-sm" />
                                <input v-model="form.holiday_end" type="date" class="rounded-xl border-slate-300 text-sm" />
                            </div>
                        </div>

                        <div>
                            <p class="mb-3 text-sm font-bold text-slate-800">Busqueda</p>
                            <input
                                v-model="form.search"
                                type="search"
                                class="w-full rounded-xl border-slate-300 text-sm"
                                placeholder="Producto, codigo, categoria, folio o usuario"
                            >
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-black text-slate-900">
                        Como se va a generar
                    </h2>
                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        <div class="rounded-xl bg-slate-50 p-3">
                            El reporte siempre respeta la sucursal, fechas, usuarios, categorias y resultado que selecciones.
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            Excel y PDF descargan exactamente la misma vista filtrada que ves en pantalla.
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            Los productos no encontrados aparecen como pendientes para que puedas detectar huecos de captura.
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                <div
                    v-for="card in resultCards"
                    :key="card.label"
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <p class="text-xs text-slate-500">{{ card.label }}</p>
                    <p class="mt-2 text-2xl font-black" :class="card.tone">{{ card.value }}</p>
                </div>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h3 class="text-lg font-black text-slate-900">
                            Resultados del reporte
                        </h3>
                        <p class="mt-1 text-sm text-slate-500">
                            Vista detallada de auditorias, diferencias y productos no encontrados.
                        </p>
                    </div>

                    <div class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                        {{ reportTableItems.length }} resultados visibles con el filtro actual
                    </div>
                </div>

                <div class="mt-5">
                    <GlobalTable
                        :items="reportTableItems"
                        v-bind="reportTableConfig"
                        row-key="id"
                        :show-pagination="false"
                    />
                </div>
            </section>
        </div>
    </PageLayout>
</template>
