<script setup>
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import InputField from '@/Components/Forms/InputField.vue'
import { REALTIME_BROWSER_EVENTS, refreshRealtimeProps } from '@/realtime'

defineOptions({ layout: AdminLayout })

const VueApexCharts = defineAsyncComponent(async () => {
    await Promise.all([import('apexcharts/bar'), import('apexcharts/column'), import('apexcharts/line'), import('apexcharts/radar'), import('apexcharts/features/legend')])
    return (await import('vue3-apexcharts/core')).default
})

const props = defineProps({
    branches: { type: Array, default: () => [] },
    chartWidget: { type: Object, default: () => ({ filters: {}, summary: {}, series: [], limitations: {} }) },
    rankingWidget: { type: Object, default: () => ({ filters: {}, rows: [] }) },
    radarWidget: { type: Object, default: () => ({ filters: {}, product_id: null, product_sales: null }) },
    categoryWidget: { type: Object, default: () => ({ filters: {}, selected_ids: [], rows: [] }) },
    categories: { type: Array, default: () => [] },
})

const page = usePage()
const currency = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', maximumFractionDigits: 2 })
const number = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 3 })
const metricColors = ['#e0000f', '#171717', '#a06b00', '#dc2626']
const groupingOptions = [{ label: 'Por día', value: 'day' }, { label: 'Por semana', value: 'week' }, { label: 'Por mes', value: 'month' }]
const branchOptions = computed(() => props.branches.map((branch) => ({ label: branch.name, value: branch.id })))
const productSearch = ref('')
const productOptions = ref([])
const isSearchingProducts = ref(false)
const categorySearch = ref('')
let productSearchTimer
let dashboardRefreshTimer

function refreshDashboardRealtime() {
    clearTimeout(dashboardRefreshTimer)
    dashboardRefreshTimer = setTimeout(() => {
        refreshRealtimeProps(page, [
            'branches',
            'chartWidget',
            'rankingWidget',
            'radarWidget',
            'categoryWidget',
            'categories',
        ])
    }, 250)
}

const chartBranch = computed(() => props.chartWidget.series?.[0] ?? null)
const chartFilters = computed(() => props.chartWidget.filters ?? {})
const rankingFilters = computed(() => props.rankingWidget.filters ?? {})
const radarFilters = computed(() => props.radarWidget.filters ?? {})
const categoryFilters = computed(() => props.categoryWidget.filters ?? {})
const rankingRows = computed(() => props.rankingWidget.rows ?? [])
const radarRows = computed(() => props.radarWidget.product_sales?.rows ?? [])
const categoryRows = computed(() => props.categoryWidget.rows ?? [])
const selectedCategoryIds = computed(() => props.categoryWidget.selected_ids ?? [])
const selectedCategories = computed(() => props.categories.filter((category) => selectedCategoryIds.value.includes(category.id)))
const categoryOptionsList = computed(() => {
    const search = categorySearch.value.trim().toLocaleLowerCase('es-MX')
    if (!search) return []

    return props.categories
        .filter((category) => !selectedCategoryIds.value.includes(category.id))
        .filter((category) => category.name.toLocaleLowerCase('es-MX').includes(search))
        .slice(0, 8)
})
const hasChartData = computed(() => chartBranch.value?.series?.some((row) => Number(row.sales) || Number(row.investment) || Number(row.profit) || Number(row.shrinkage)))
const hasRadarData = computed(() => radarRows.value.some((row) => Number(row.units) > 0))
const hasRankingData = computed(() => rankingRows.value.some((row) => Number(row.sales) > 0))
const hasSelectedCategories = computed(() => selectedCategories.value.length > 0)
const summaryCards = computed(() => [
    { key: 'sales', title: 'Ventas', description: 'Ventas completadas y ya incluidas en un corte de caja.' },
    { key: 'investment', title: 'Inversión', description: 'Total final de órdenes de compra completadas de esta sucursal.' },
    { key: 'profit', title: 'Utilidad estimada', description: 'Ventas menos el costo actual de los productos vendidos.' },
    { key: 'shrinkage', title: 'Merma estimada', description: 'Daños o caducidades valuadas al costo actual.' },
])
const chartOptions = computed(() => ({
    chart: { type: 'line', toolbar: { show: false }, animations: { enabled: false } }, colors: metricColors,
    stroke: { curve: 'smooth', width: 3, dashArray: [0, 7, 9, 4] }, dataLabels: { enabled: false }, markers: { size: 2, hover: { size: 5 } },
    xaxis: { categories: chartBranch.value?.series?.map((row) => row.label) ?? [], labels: { trim: true } },
    yaxis: { labels: { formatter: (value) => currency.format(value) } }, legend: { position: 'top', fontSize: '11px' },
    tooltip: { shared: true, y: { formatter: (value) => currency.format(value) } }, grid: { borderColor: 'rgba(128, 128, 128, 0.18)' },
}))
const chartSeries = computed(() => {
    const rows = chartBranch.value?.series ?? []
    return [{ name: 'Ventas', data: rows.map((row) => Number(row.sales)) }, { name: 'Inversión', data: rows.map((row) => Number(row.investment)) }, { name: 'Utilidad', data: rows.map((row) => Number(row.profit)) }, { name: 'Merma', data: rows.map((row) => Number(row.shrinkage)) }]
})
const rankingOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, animations: { enabled: false } }, colors: ['#e0000f', '#171717', '#a06b00', '#dc2626', '#6b7280'],
    plotOptions: { bar: { horizontal: false, columnWidth: '48%', borderRadius: 4, distributed: true } }, dataLabels: { enabled: false },
    xaxis: { categories: rankingRows.value.map((row) => row.branch_name), labels: { trim: true, rotate: -20 } }, yaxis: { labels: { formatter: (value) => currency.format(value) } },
    legend: { show: false }, tooltip: { y: { formatter: (value) => currency.format(value) } }, grid: { borderColor: 'rgba(128, 128, 128, 0.18)' },
}))
const radarOptions = computed(() => ({
    chart: { type: 'radar', toolbar: { show: false }, animations: { enabled: false } }, colors: ['#e0000f'], xaxis: { categories: radarRows.value.map((row) => row.label) }, yaxis: { show: false },
    stroke: { width: 2.5 }, fill: { opacity: 0.3 }, markers: { size: 4 }, tooltip: { y: { formatter: (value) => `${number.format(value)} piezas` } },
    plotOptions: { radar: { polygons: { strokeColors: 'rgba(128, 128, 128, 0.25)', connectorColors: 'rgba(128, 128, 128, 0.25)' } } },
}))
const categoryRadarOptions = computed(() => ({
    chart: { type: 'radar', toolbar: { show: false }, animations: { enabled: false } }, colors: ['#a06b00'],
    xaxis: { categories: categoryRows.value.map((row) => row.label) }, yaxis: { show: false },
    stroke: { width: 2.5 }, fill: { opacity: 0.3 }, markers: { size: 4 },
    tooltip: { y: { formatter: (value) => currency.format(value) } },
    plotOptions: { radar: { polygons: { strokeColors: 'rgba(128, 128, 128, 0.25)', connectorColors: 'rgba(128, 128, 128, 0.25)' } } },
}))

watch(productSearch, (term) => {
    clearTimeout(productSearchTimer); productOptions.value = []
    if (term.trim().length < 2) return
    productSearchTimer = setTimeout(async () => {
        isSearchingProducts.value = true
        try {
            const { data } = await window.axios.get(route('dashboard.products.search'), { params: { search: term, radar_date_from: radarFilters.value.date_from, radar_date_to: radarFilters.value.date_to } })
            productOptions.value = data.products ?? []
        } finally { isSearchingProducts.value = false }
    }, 250)
})
onMounted(() => {
    window.addEventListener(REALTIME_BROWSER_EVENTS.dataChanged, refreshDashboardRealtime)
})

onBeforeUnmount(() => {
    clearTimeout(productSearchTimer)
    clearTimeout(dashboardRefreshTimer)
    window.removeEventListener(REALTIME_BROWSER_EVENTS.dataChanged, refreshDashboardRealtime)
})

function currentParams() {
    return {
        chart_branch_id: chartFilters.value.branch_id, chart_date_from: chartFilters.value.date_from, chart_date_to: chartFilters.value.date_to, chart_grouping: chartFilters.value.grouping,
        ranking_date_from: rankingFilters.value.date_from, ranking_date_to: rankingFilters.value.date_to,
        radar_date_from: radarFilters.value.date_from, radar_date_to: radarFilters.value.date_to, radar_product_id: props.radarWidget.product_id || undefined,
        category_branch_id: categoryFilters.value.branch_id, category_date_from: categoryFilters.value.date_from, category_date_to: categoryFilters.value.date_to,
        category_ids: selectedCategoryIds.value.join(',') || undefined,
    }
}
function updateWidget(values) { router.get(route('dashboard'), { ...currentParams(), ...values }, { preserveScroll: true, preserveState: true, replace: true }) }
function chooseProduct(product) { productSearch.value = product.name; productOptions.value = []; updateWidget({ radar_product_id: product.id }) }
function clearProduct() { productSearch.value = ''; productOptions.value = []; updateWidget({ radar_product_id: undefined }) }
function selectCategory(category) {
    categorySearch.value = ''
    updateWidget({ category_ids: [...selectedCategoryIds.value, category.id].join(',') })
}
function removeCategory(categoryId) {
    updateWidget({ category_ids: selectedCategoryIds.value.filter((id) => id !== categoryId).join(',') || undefined })
}
</script>

<template>
    <Head title="Dashboard" />
    <PageLayout>
        <template #toolbar>
            <GlobalToolbar icon="dashboard" title="Dashboard" subtitle="Consulta el desempeño de sucursales, ventas, inversión, utilidad y merma." :show-search="false" :show-records-per-page="false" :show-counter="false" />
        </template>

        <div class="space-y-5">
            <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-zinc-900">
                <div class="mb-5">
                    <h1 class="text-lg font-semibold">Indicadores por sucursal</h1>
                    <p class="text-sm text-black/60 dark:text-white/60">Selecciona la sucursal y la forma en que deseas resumir el historial.</p>
                </div>
                <div class="mb-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div><SelectField label="Sucursal" field="chart-branch" :model-value="chartFilters.branch_id" :options="branchOptions" @update:model-value="updateWidget({ chart_branch_id: $event })" /><p class="mt-1 text-xs text-black/60 dark:text-white/60">Sucursal de la gráfica y sus indicadores.</p></div>
                    <div><SelectField label="Agrupar datos" field="chart-grouping" :model-value="chartFilters.grouping" :options="groupingOptions" @update:model-value="updateWidget({ chart_grouping: $event })" /><p class="mt-1 text-xs text-black/60 dark:text-white/60">Resume el rango por días, semanas o meses.</p></div>
                    <div><InputField label="Desde" field="chart-date-from" type="date" :model-value="chartFilters.date_from" @update:model-value="updateWidget({ chart_date_from: $event })" /><p class="mt-1 text-xs text-black/60 dark:text-white/60">Primer día incluido en el historial.</p></div>
                    <div><InputField label="Hasta" field="chart-date-to" type="date" :model-value="chartFilters.date_to" @update:model-value="updateWidget({ chart_date_to: $event })" /><p class="mt-1 text-xs text-black/60 dark:text-white/60">Último día incluido en el historial.</p></div>
                </div>
                <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article v-for="card in summaryCards" :key="card.key" class="rounded-xl bg-black/[0.03] p-4 dark:bg-white/[0.06]"><p class="text-sm font-medium">{{ card.title }}</p><p class="mt-1 text-xl font-bold">{{ currency.format(chartWidget.summary?.[card.key]?.value ?? 0) }}</p><p class="mt-2 text-xs text-black/60 dark:text-white/60">{{ card.description }}</p></article>
                </div>
                <VueApexCharts v-if="hasChartData" height="360" :options="chartOptions" :series="chartSeries" />
                <p v-else class="py-24 text-center text-sm text-black/60 dark:text-white/60">No se registraron datos confirmados para esta sucursal en el rango elegido.</p>
            </section>

            <section class="grid gap-5 xl:grid-cols-2">
                <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-zinc-900">
                    <h2 class="font-semibold">Ranking de ventas</h2><p class="mb-4 text-sm text-black/60 dark:text-white/60">Compara ventas confirmadas de todas las sucursales.</p>
                    <div class="mb-4 grid gap-4 sm:grid-cols-2">
                        <div><InputField label="Desde" field="ranking-date-from" type="date" :model-value="rankingFilters.date_from" @update:model-value="updateWidget({ ranking_date_from: $event })" /><p class="mt-1 text-xs text-black/60 dark:text-white/60">Inicio del periodo que se compara.</p></div>
                        <div><InputField label="Hasta" field="ranking-date-to" type="date" :model-value="rankingFilters.date_to" @update:model-value="updateWidget({ ranking_date_to: $event })" /><p class="mt-1 text-xs text-black/60 dark:text-white/60">Fin del periodo que se compara.</p></div>
                    </div>
                    <p class="mb-3 text-xs text-black/60 dark:text-white/60">Se ordena por ventas completadas que ya pertenecen a un corte de caja.</p>
                    <VueApexCharts v-if="hasRankingData" height="290" :options="rankingOptions" :series="[{ name: 'Ventas', data: rankingRows.map((row) => Number(row.sales)) }]" />
                    <p v-else class="py-20 text-center text-sm text-black/60 dark:text-white/60">Aún no hay ventas confirmadas para comparar.</p>
                </article>

                <article class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-zinc-900">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="font-semibold">Radar de venta por producto</h2><p class="text-sm text-black/60 dark:text-white/60">Compara piezas vendidas del producto entre sucursales.</p></div><span v-if="radarWidget.product_sales?.product" class="rounded-full bg-red-50 px-3 py-1 text-sm font-medium text-red-800 dark:bg-red-950/40 dark:text-red-100">{{ radarWidget.product_sales.product.name }}</span></div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div class="relative sm:col-span-2"><InputField label="Producto" field="dashboard-product-search" validation-field="toolbar_search" type="search" :model-value="productSearch" placeholder="Busca el producto que quieres comparar" autocomplete="off" :show-counter="false" @update:model-value="productSearch = $event" /><button v-if="radarWidget.product_id" type="button" class="absolute right-2 top-9 text-sm font-medium text-primary" @click="clearProduct">Limpiar</button><span v-else-if="isSearchingProducts" class="absolute right-3 top-9 text-xs text-text/50">Buscando...</span><p class="mt-1 text-xs text-black/60 dark:text-white/60">Busca un producto y el radar mostrará sus piezas vendidas por sucursal.</p><div v-if="productOptions.length" class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-secondary bg-background p-1 shadow-xl"><button v-for="product in productOptions" :key="product.id" type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm hover:bg-secondary" @click="chooseProduct(product)"><span>{{ product.name }}</span><span v-if="product.code" class="text-xs text-text/50">{{ product.code }}</span></button></div></div>
                        <div><InputField label="Desde" field="radar-date-from" type="date" :model-value="radarFilters.date_from" @update:model-value="updateWidget({ radar_date_from: $event })" /><p class="mt-1 text-xs text-black/60 dark:text-white/60">Inicio de las ventas del producto.</p></div>
                        <div><InputField label="Hasta" field="radar-date-to" type="date" :model-value="radarFilters.date_to" @update:model-value="updateWidget({ radar_date_to: $event })" /><p class="mt-1 text-xs text-black/60 dark:text-white/60">Fin de las ventas del producto.</p></div>
                    </div>
                    <p class="my-3 text-xs text-black/60 dark:text-white/60">Suma las cantidades vendidas en ventas completadas y con corte de caja dentro de este rango.</p>
                    <VueApexCharts v-if="radarWidget.product_sales?.product && hasRadarData" height="290" :options="radarOptions" :series="[{ name: 'Piezas vendidas', data: radarRows.map((row) => Number(row.units)) }]" />
                    <p v-else-if="radarWidget.product_sales?.product" class="py-16 text-center text-sm text-black/60 dark:text-white/60">Este producto no tiene ventas confirmadas en el rango elegido.</p><p v-else class="py-16 text-center text-sm text-black/60 dark:text-white/60">Busca y selecciona un producto para comparar dónde se vende más.</p>
                </article>
            </section>

            <section class="rounded-2xl border border-black/10 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-zinc-900">
                <h2 class="font-semibold">Comparador de categorías</h2><p class="mb-4 text-sm text-black/60 dark:text-white/60">Elige las categorías que quieres contrastar dentro de una sucursal.</p>
                <div class="mb-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div><SelectField label="Sucursal" field="category-branch" :model-value="categoryFilters.branch_id" :options="branchOptions" @update:model-value="updateWidget({ category_branch_id: $event })" /><p class="mt-1 text-xs text-black/60 dark:text-white/60">Sucursal en la que se comparan las categorías.</p></div>
                    <div><InputField label="Desde" field="category-date-from" type="date" :model-value="categoryFilters.date_from" @update:model-value="updateWidget({ category_date_from: $event })" /><p class="mt-1 text-xs text-black/60 dark:text-white/60">Primer día de ventas incluido.</p></div>
                    <div><InputField label="Hasta" field="category-date-to" type="date" :model-value="categoryFilters.date_to" @update:model-value="updateWidget({ category_date_to: $event })" /><p class="mt-1 text-xs text-black/60 dark:text-white/60">Último día de ventas incluido.</p></div>
                    <div class="relative"><InputField label="Buscar categorías" field="category-search" type="search" :model-value="categorySearch" placeholder="Escribe una categoría" autocomplete="off" :show-counter="false" @update:model-value="categorySearch = $event" /><p class="mt-1 text-xs text-black/60 dark:text-white/60">Añade sólo las categorías que deseas ver.</p><div v-if="categoryOptionsList.length" class="absolute z-20 mt-1 max-h-56 w-full overflow-y-auto rounded-xl border border-secondary bg-background p-1 shadow-xl"><button v-for="category in categoryOptionsList" :key="category.id" type="button" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm hover:bg-secondary" @click="selectCategory(category)"><span>{{ category.name }}</span><span class="text-xs font-medium text-primary">Agregar</span></button></div></div>
                </div>
                <div v-if="selectedCategories.length" class="mb-4 rounded-xl bg-black/[0.03] p-3 dark:bg-white/[0.06]"><p class="mb-2 text-xs font-medium text-black/60 dark:text-white/60">Categorías seleccionadas</p><div class="flex flex-wrap gap-2"><button v-for="category in selectedCategories" :key="category.id" type="button" class="inline-flex items-center gap-2 rounded-full bg-amber-100 px-3 py-1.5 text-sm font-medium text-amber-950 transition hover:bg-amber-200 dark:bg-amber-950/40 dark:text-amber-100" @click="removeCategory(category.id)"><span>{{ category.name }}</span><span aria-hidden="true">×</span></button></div></div>
                <p class="mb-3 text-xs text-black/60 dark:text-white/60">Cada esquina del radar representa una categoría seleccionada. El valor es el importe de ventas completadas que ya pertenecen a un corte de caja dentro del periodo elegido.</p>
                <VueApexCharts v-if="hasSelectedCategories" height="330" :options="categoryRadarOptions" :series="[{ name: 'Ventas', data: categoryRows.map((row) => Number(row.revenue)) }]" />
                <p v-else class="py-20 text-center text-sm text-black/60 dark:text-white/60">Busca y selecciona al menos una categoría para iniciar la comparación.</p>
            </section>
        </div>
    </PageLayout>
</template>
