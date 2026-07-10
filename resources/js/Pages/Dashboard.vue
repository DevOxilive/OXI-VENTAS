<script setup>
import { computed, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import VueApexCharts from 'vue3-apexcharts'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    filters: {
        type: Object,
        default: () => ({
            period: 'month',
            branch_id: null,
            date_from: '',
            date_to: '',
            max_date: '',
            label: '',
            branches: [],
        }),
    },
    dashboardWidgets: {
        type: Object,
        default: () => ({}),
    },
    summary: {
        type: Object,
        default: () => ({}),
    },
    branchPerformance: {
        type: Array,
        default: () => [],
    },
    topProducts: {
        type: Array,
        default: () => [],
    },
    shrinkageSummary: {
        type: Object,
        default: () => ({
            cost_loss: 0,
            revenue_loss: 0,
            units: 0,
            by_branch: [],
        }),
    },
})

const storageKey = 'dashboard.executive.grid.v2'
const selectedBranchId = ref(props.filters.branch_id ?? '')
const dateFrom = ref(props.filters.date_from ?? '')
const dateTo = ref(props.filters.date_to ?? '')

const periodOptions = [
    { value: 'day', label: 'Dia' },
    { value: 'week', label: 'Semana' },
    { value: 'month', label: 'Mes' },
    { value: 'year', label: 'Anio' },
]

const emptyWidgetData = {
    label: '',
    branchPerformance: [],
    categoryPerformance: [],
    categoryTimeline: [],
    paymentBreakdown: [],
    movementBreakdown: [],
    movementTimeline: [],
    productWeekdayRadar: {
        product: null,
        weekdays: [],
        branches: [],
    },
    shrinkageByBranch: [],
    shrinkageByCategory: [],
    shrinkageTimeline: [],
    shrinkageProducts: [],
    shrinkageSummary: {
        cost_loss: 0,
        revenue_loss: 0,
        units: 0,
    },
    topProducts: [],
    productTimeline: [],
}

const widgetCatalog = [
    {
        id: 'branchMix',
        title: 'Tendencia comercial',
        kind: 'branchMix',
        wide: true,
    },
    {
        id: 'categoryRadar',
        title: 'Categorias',
        kind: 'categoryRadar',
    },
    {
        id: 'weekdayRadar',
        title: 'Producto por dia y sucursal',
        kind: 'weekdayRadar',
    },
    {
        id: 'paymentMethods',
        title: 'Metodos de pago',
        kind: 'paymentMethods',
    },
    {
        id: 'movementMix',
        title: 'Movimientos',
        kind: 'movementMix',
    },
    {
        id: 'revenue',
        title: 'Ventas',
        kind: 'branchMetric',
        metric: 'revenue',
        tone: '#7f1d1d',
        valueLabel: 'Ventas',
        wide: true,
    },
    {
        id: 'investment',
        title: 'Inversion',
        kind: 'branchMetric',
        metric: 'investment',
        tone: '#111827',
        valueLabel: 'Inversion',
        wide: true,
    },
    {
        id: 'expectedProfit',
        title: 'Ganancias',
        kind: 'branchMetric',
        metric: 'expected_profit',
        tone: '#f59e0b',
        valueLabel: 'Ganancia esperada',
        wide: true,
    },
    {
        id: 'profit',
        title: 'Utilidad generada',
        kind: 'branchMetric',
        metric: 'profit',
        tone: '#0f766e',
        valueLabel: 'Utilidad',
        wide: true,
    },
    {
        id: 'shrinkage',
        title: 'Mermas por categoria',
        kind: 'shrinkage',
        tone: '#e11d48',
        wide: true,
    },
    {
        id: 'topProducts',
        title: 'Productos con mayor movimiento',
        kind: 'products',
        tone: '#334155',
        wide: true,
    },
]

function defaultLayout() {
    return {
        hidden: ['revenue', 'investment', 'expectedProfit', 'profit'],
        pinned: ['branchMix', 'weekdayRadar', 'categoryRadar', 'paymentMethods', 'movementMix', 'shrinkage', 'topProducts'],
        periods: {
            branchMix: 'month',
            weekdayRadar: 'week',
            categoryRadar: 'month',
            paymentMethods: 'month',
            movementMix: 'week',
            revenue: 'month',
            investment: 'month',
            expectedProfit: 'month',
            profit: 'month',
            shrinkage: 'month',
            topProducts: 'month',
        },
    }
}

function loadLayout() {
    if (typeof window === 'undefined') return defaultLayout()

    try {
        const saved = JSON.parse(window.localStorage.getItem(storageKey) ?? '{}')
        const defaults = defaultLayout()

        return {
            ...defaults,
            ...saved,
            periods: {
                ...defaults.periods,
                ...(saved.periods ?? {}),
            },
        }
    } catch {
        return defaultLayout()
    }
}

const layout = ref(loadLayout())

watch(
    () => props.filters,
    (value) => {
        selectedBranchId.value = value?.branch_id ?? ''
        dateFrom.value = value?.date_from ?? ''
        dateTo.value = value?.date_to ?? ''
    },
    { deep: true },
)

watch(
    layout,
    (value) => {
        if (typeof window === 'undefined') return

        window.localStorage.setItem(storageKey, JSON.stringify(value))
    },
    { deep: true },
)

watch([selectedBranchId, dateFrom, dateTo], () => {
    if (!dateFrom.value || !dateTo.value) return

    router.get(
        route('dashboard'),
        {
            period: props.filters.period ?? 'month',
            branch_id: selectedBranchId.value || undefined,
            date_from: dateFrom.value,
            date_to: dateTo.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    )
})

const currencyFormatter = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 0,
})

function formatCurrency(value) {
    const amount = Number(value ?? 0)

    return currencyFormatter.format(Number.isFinite(amount) ? amount : 0)
}

function formatNumber(value, digits = 0) {
    const amount = Number(value ?? 0)

    return new Intl.NumberFormat('es-MX', {
        maximumFractionDigits: digits,
    }).format(Number.isFinite(amount) ? amount : 0)
}

function widgetPeriod(id) {
    return layout.value.periods[id] ?? 'month'
}

function setWidgetPeriod(id, period) {
    layout.value.periods = {
        ...layout.value.periods,
        [id]: period,
    }
}

function dataFor(id) {
    return props.dashboardWidgets?.[widgetPeriod(id)] ?? emptyWidgetData
}

function branchRows(id) {
    return dataFor(id).branchPerformance ?? []
}

function trendRows(id) {
    return dataFor(id).salesTrend ?? []
}

function categoryRows(id) {
    return dataFor(id).categoryPerformance ?? []
}

function categoryTimelineRows(id) {
    return dataFor(id).categoryTimeline ?? []
}

function paymentRows(id) {
    return dataFor(id).paymentBreakdown ?? []
}

function movementRows(id) {
    return dataFor(id).movementBreakdown ?? []
}

function movementTimelineRows(id) {
    return dataFor(id).movementTimeline ?? []
}

function weekdayRadarData(id) {
    return dataFor(id).productWeekdayRadar ?? emptyWidgetData.productWeekdayRadar
}

function shrinkageRows(id) {
    return dataFor(id).shrinkageByCategory ?? []
}

function shrinkageTimelineRows(id) {
    return dataFor(id).shrinkageTimeline ?? []
}

function shrinkageProducts(id) {
    return dataFor(id).shrinkageProducts ?? []
}

function productRows(id) {
    return dataFor(id).topProducts ?? []
}

function productTimelineRows(id) {
    return dataFor(id).productTimeline ?? []
}

function metricTotal(id, metric) {
    return branchRows(id).reduce((total, row) => total + Number(row[metric] ?? 0), 0)
}

function compactRows(rows, limit = 5) {
    return rows.slice(0, limit)
}

function timelineLabels(rows) {
    return [...new Map(rows.map((row) => [row.period_key, row.label])).values()]
}

function topNames(rows, nameKey, valueKey, limit = 5) {
    const totals = rows.reduce((carry, row) => {
        const name = row[nameKey]
        carry[name] = (carry[name] ?? 0) + Number(row[valueKey] ?? 0)

        return carry
    }, {})

    return Object.entries(totals)
        .sort((a, b) => b[1] - a[1])
        .slice(0, limit)
        .map(([name]) => name)
}

function timelineSeries(rows, nameKey, valueKey, limit = 5) {
    const labels = timelineLabels(rows)
    const periodKeys = [...new Set(rows.map((row) => row.period_key))]

    return topNames(rows, nameKey, valueKey, limit).map((name) => ({
        name,
        data: periodKeys.map((key) => {
            const row = rows.find((item) => item.period_key === key && item[nameKey] === name)

            return Number(row?.[valueKey] ?? 0)
        }),
    }))
}

function heatmapSeries(rows, nameKey, valueKey, limit = 7) {
    const labels = timelineLabels(rows)
    const periodKeys = [...new Set(rows.map((row) => row.period_key))]

    return topNames(rows, nameKey, valueKey, limit).map((name) => ({
        name,
        data: periodKeys.map((key, index) => {
            const row = rows.find((item) => item.period_key === key && item[nameKey] === name)

            return {
                x: labels[index] ?? key,
                y: Number(row?.[valueKey] ?? 0),
            }
        }),
    }))
}

function isHidden(id) {
    return layout.value.hidden.includes(id)
}

function isPinned(id) {
    return layout.value.pinned.includes(id)
}

function hideWidget(id) {
    layout.value.hidden = [...new Set([...layout.value.hidden, id])]
}

function showWidget(id) {
    layout.value.hidden = layout.value.hidden.filter((item) => item !== id)
}

const visibleWidgets = computed(() => {
    const visible = widgetCatalog.filter((widget) => !isHidden(widget.id))

    return [
        ...visible.filter((widget) => isPinned(widget.id)),
        ...visible.filter((widget) => !isPinned(widget.id)),
    ]
})

const hiddenWidgets = computed(() => widgetCatalog.filter((widget) => isHidden(widget.id)))

const summaryCards = computed(() => [
    {
        label: 'Ventas',
        value: formatCurrency(props.summary.revenue),
        accent: 'from-rose-50 to-white',
    },
    {
        label: 'Inversion',
        value: formatCurrency(props.summary.investment),
        accent: 'from-slate-100 to-white',
    },
    {
        label: 'Utilidad',
        value: formatCurrency(props.summary.profit),
        accent: 'from-emerald-50 to-white',
    },
    {
        label: 'Merma',
        value: formatCurrency(props.shrinkageSummary.cost_loss),
        accent: 'from-amber-50 to-white',
    },
])

const selectedBranchName = computed(() => {
    const branch = props.filters.branches?.find(
        (item) => Number(item.id) === Number(selectedBranchId.value),
    )

    return branch?.name ?? 'Todas las sucursales'
})

function widgetTitle(widget) {
    return widget.title
}

function branchMetricSeries(id, metric, label) {
    return [
        {
            name: label,
            data: branchRows(id).map((row) => Number(row[metric] ?? 0)),
        },
    ]
}

function branchMetricOptions(id, color) {
    return {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            foreColor: '#475569',
        },
        plotOptions: {
            bar: {
                horizontal: false,
                borderRadius: 7,
                columnWidth: '42%',
            },
        },
        colors: [color],
        dataLabels: { enabled: false },
        grid: {
            borderColor: '#e2e8f0',
            strokeDashArray: 4,
        },
        xaxis: {
            categories: branchRows(id).map((row) => row.name),
        },
        yaxis: {
            labels: {
                formatter: (value) => formatCurrency(value),
            },
        },
        tooltip: {
            y: {
                formatter: (value) => formatCurrency(value),
            },
        },
    }
}

function branchMixSeries(id) {
    return [
        {
            name: 'Ventas',
            type: 'column',
            data: trendRows(id).map((row) => Number(row.revenue ?? 0)),
        },
        {
            name: 'Inversion',
            type: 'column',
            data: trendRows(id).map((row) => Number(row.investment ?? 0)),
        },
        {
            name: 'Utilidad',
            type: 'area',
            data: trendRows(id).map((row) => Number(row.profit ?? 0)),
        },
    ]
}

function branchMixOptions(id) {
    return {
        chart: {
            type: 'area',
            toolbar: {
                show: true,
                tools: {
                    download: false,
                    selection: true,
                    zoom: true,
                    zoomin: true,
                    zoomout: true,
                    pan: true,
                    reset: true,
                },
            },
            zoom: { enabled: true },
            foreColor: '#475569',
            sparkline: { enabled: false },
        },
        colors: ['#7f1d1d', '#111827', '#0f766e'],
        stroke: {
            width: [0, 0, 4],
            curve: 'smooth',
        },
        fill: {
            type: ['solid', 'solid', 'gradient'],
            gradient: {
                opacityFrom: 0.35,
                opacityTo: 0.05,
            },
        },
        plotOptions: {
            bar: {
                borderRadius: 6,
                columnWidth: '38%',
            },
        },
        dataLabels: { enabled: false },
        grid: {
            borderColor: '#e2e8f0',
            strokeDashArray: 4,
        },
        legend: { position: 'top', horizontalAlign: 'left', fontSize: '11px' },
        xaxis: {
            categories: trendRows(id).map((row) => row.label),
            tickAmount: Math.min(8, Math.max(2, trendRows(id).length)),
            labels: {
                rotate: -35,
                trim: true,
            },
        },
        yaxis: {
            labels: {
                formatter: (value) => formatCurrency(value),
            },
        },
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter: (value) => formatCurrency(value),
            },
        },
    }
}

function categoryBarSeries(id) {
    return timelineSeries(categoryTimelineRows(id), 'category_name', 'revenue', 5)
}

function categoryBarOptions(id) {
    const labels = timelineLabels(categoryTimelineRows(id))

    return {
        chart: {
            type: 'area',
            toolbar: { show: false },
            foreColor: '#475569',
            stacked: false,
        },
        colors: ['#7f1d1d', '#0f766e', '#f97316', '#334155', '#e11d48'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        fill: {
            type: 'gradient',
            gradient: { opacityFrom: 0.35, opacityTo: 0.04 },
        },
        grid: {
            borderColor: '#e2e8f0',
            strokeDashArray: 4,
        },
        legend: {
            position: 'top',
            horizontalAlign: 'left',
            fontSize: '11px',
        },
        xaxis: {
            categories: labels,
            tickAmount: Math.min(8, Math.max(2, labels.length)),
        },
        yaxis: { labels: { formatter: (value) => formatCurrency(value) } },
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter: (value) => formatCurrency(value),
            },
        },
    }
}

function paymentSeries(id) {
    return [
        {
            name: 'Total',
            data: paymentRows(id).map((row) => Number(row.total ?? 0)),
        },
    ]
}

function paymentOptions(id) {
    return {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            foreColor: '#475569',
        },
        plotOptions: {
            bar: {
                horizontal: true,
                distributed: true,
                borderRadius: 10,
                barHeight: '58%',
            },
        },
        labels: paymentRows(id).map((row) => row.name),
        colors: ['#7f1d1d', '#0f766e', '#f59e0b', '#334155', '#e11d48'],
        dataLabels: {
            enabled: true,
            formatter: (value) => formatCurrency(value),
            style: {
                fontSize: '11px',
                fontWeight: 800,
            },
        },
        grid: {
            borderColor: '#e2e8f0',
            strokeDashArray: 4,
        },
        legend: { show: false },
        xaxis: {
            categories: paymentRows(id).map((row) => row.name),
            labels: {
                formatter: (value) => formatCurrency(value),
            },
        },
        tooltip: {
            y: {
                formatter: (value) => formatCurrency(value),
            },
        },
    }
}

function movementSeries(id) {
    return heatmapSeries(movementTimelineRows(id), 'reason', 'movements', 6).map((serie) => ({
        ...serie,
        name: movementLabel(serie.name),
    }))
}

function movementOptions(id) {
    return {
        chart: {
            type: 'heatmap',
            toolbar: { show: false },
            foreColor: '#475569',
        },
        colors: ['#0f766e'],
        plotOptions: {
            heatmap: {
                radius: 9,
                shadeIntensity: 0.22,
                useFillColorAsStroke: false,
                colorScale: {
                    ranges: [
                        { from: 0, to: 0, color: '#f8fafc', name: 'Sin movimiento' },
                        { from: 1, to: 25, color: '#dbeafe', name: 'Bajo' },
                        { from: 26, to: 100, color: '#99f6e4', name: 'Medio' },
                        { from: 101, to: 10000, color: '#0f766e', name: 'Alto' },
                    ],
                },
            },
        },
        dataLabels: { enabled: false },
        xaxis: { labels: { rotate: -20 } },
        tooltip: {
            y: {
                formatter: (value) => `${formatNumber(value)} movimientos`,
            },
        },
    }
}

function movementLabel(value) {
    return {
        PURCHASE: 'Compras',
        SALE: 'Ventas',
        DAMAGED: 'Danado',
        EXPIRED: 'Caducado',
        INVENTORY_DIFFERENCE: 'Ajuste',
        OTHER: 'Otros',
    }[value] ?? value
}

function shrinkageSeries(id) {
    return timelineSeries(shrinkageTimelineRows(id), 'category_name', 'cost_loss', 5)
}

function shrinkageOptions(id) {
    const labels = timelineLabels(shrinkageTimelineRows(id))

    return {
        chart: {
            type: 'area',
            toolbar: { show: false },
            foreColor: '#475569',
        },
        colors: ['#7f1d1d', '#e11d48', '#f97316', '#f59e0b', '#334155', '#0f766e'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        fill: {
            type: 'gradient',
            gradient: { opacityFrom: 0.3, opacityTo: 0.03 },
        },
        legend: {
            position: 'bottom',
        },
        xaxis: { categories: labels, tickAmount: Math.min(8, Math.max(2, labels.length)) },
        yaxis: { labels: { formatter: (value) => formatCurrency(value) } },
        tooltip: {
            shared: true,
            intersect: false,
            y: {
                formatter: (value) => formatCurrency(value),
            },
        },
    }
}

function productTreeSeries(id) {
    return heatmapSeries(productTimelineRows(id), 'product_name', 'revenue', 8)
}

function productTreeOptions(id) {
    return {
        chart: {
            type: 'heatmap',
            toolbar: { show: false },
            foreColor: '#475569',
        },
        colors: ['#0f766e'],
        plotOptions: {
            heatmap: {
                radius: 8,
                shadeIntensity: 0.45,
                colorScale: {
                    ranges: [
                        { from: 0, to: 0, color: '#f8fafc', name: 'Sin venta' },
                        { from: 1, to: 250, color: '#ccfbf1', name: 'Bajo' },
                        { from: 251, to: 1000, color: '#14b8a6', name: 'Medio' },
                        { from: 1001, to: 100000, color: '#0f766e', name: 'Alto' },
                    ],
                },
            },
        },
        dataLabels: { enabled: false },
        xaxis: { labels: { rotate: -20 } },
        tooltip: {
            y: {
                formatter: (value) => formatCurrency(value),
            },
        },
    }
}

function weekdayRadarSeries(id) {
    return weekdayRadarData(id).branches.map((branch) => ({
        name: branch.branch_name,
        data: branch.units.map((value) => Number(value ?? 0)),
    }))
}

function weekdayRadarOptions(id) {
    const data = weekdayRadarData(id)

    return {
        chart: {
            type: 'radar',
            toolbar: { show: false },
            foreColor: '#475569',
        },
        colors: ['#7f1d1d', '#0f766e', '#f97316', '#334155', '#e11d48'],
        dataLabels: {
            enabled: true,
            background: {
                enabled: true,
                borderRadius: 4,
                opacity: 0.9,
            },
            formatter: (value) => formatNumber(value, 0),
        },
        markers: {
            size: 4,
            hover: { size: 6 },
        },
        stroke: {
            width: 2,
        },
        fill: {
            opacity: 0.16,
        },
        plotOptions: {
            radar: {
                polygons: {
                    strokeColors: '#e2e8f0',
                    fill: {
                        colors: ['#f8fafc', '#ffffff'],
                    },
                },
            },
        },
        legend: {
            position: 'bottom',
            fontSize: '11px',
        },
        xaxis: {
            categories: data.weekdays,
        },
        yaxis: {
            labels: {
                formatter: (value) => formatNumber(value),
            },
        },
        tooltip: {
            y: {
                formatter: (value) => `${formatNumber(value)} unidades`,
            },
        },
    }
}
</script>

<template>
    <Head title="Dashboard" />

    <PageLayout>
        <div class="grid gap-4 xl:grid-cols-[260px_minmax(0,1fr)]">
            <aside class="space-y-3 xl:sticky xl:top-5 xl:self-start">
                <section class="rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">
                        Dashboard
                    </p>
                    <h1 class="mt-2 text-xl font-black tracking-tight text-slate-950">
                        {{ selectedBranchName }}
                    </h1>
                    <p class="mt-1 text-xs font-semibold text-slate-500">
                        {{ filters.label }}
                    </p>
                </section>

                <section class="space-y-3 rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm">
                    <label class="block text-xs font-bold uppercase tracking-[0.18em] text-slate-500">
                        Sucursal
                        <select
                            v-model="selectedBranchId"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold normal-case tracking-normal text-slate-900 outline-none transition focus:border-slate-400 focus:bg-white"
                        >
                            <option value="">Todas</option>
                            <option
                                v-for="branch in filters.branches"
                                :key="branch.id"
                                :value="branch.id"
                            >
                                {{ branch.name }}
                            </option>
                        </select>
                    </label>

                    <label class="block text-xs font-bold uppercase tracking-[0.18em] text-slate-500">
                        Desde
                        <input
                            v-model="dateFrom"
                            type="date"
                            :max="filters.max_date"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold normal-case tracking-normal text-slate-900 outline-none transition focus:border-slate-400 focus:bg-white"
                        >
                    </label>

                    <label class="block text-xs font-bold uppercase tracking-[0.18em] text-slate-500">
                        Hasta
                        <input
                            v-model="dateTo"
                            type="date"
                            :max="filters.max_date"
                            class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold normal-case tracking-normal text-slate-900 outline-none transition focus:border-slate-400 focus:bg-white"
                        >
                    </label>
                </section>

                <section class="grid grid-cols-2 gap-2 xl:grid-cols-1">
                    <article
                        v-for="card in summaryCards"
                        :key="card.label"
                        class="rounded-2xl border border-slate-100 bg-gradient-to-br p-3 shadow-sm"
                        :class="card.accent"
                    >
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">
                            {{ card.label }}
                        </p>
                        <p class="mt-1 text-lg font-black text-slate-950">
                            {{ card.value }}
                        </p>
                    </article>
                </section>
            </aside>

            <main class="space-y-4">

            <section
                v-if="hiddenWidgets.length"
                class="flex flex-wrap items-center gap-2 rounded-3xl border border-dashed border-slate-300 bg-white p-4"
            >
                <span class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">
                    Ocultos
                </span>
                <button
                    v-for="widget in hiddenWidgets"
                    :key="widget.id"
                    type="button"
                    class="rounded-full border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:border-slate-400"
                    @click="showWidget(widget.id)"
                >
                    Mostrar {{ widget.title }}
                </button>
            </section>

            <section class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <article
                    v-for="widget in visibleWidgets"
                    :key="widget.id"
                    class="rounded-[22px] border border-slate-200 bg-white p-4 shadow-sm"
                    :class="{
                        'xl:col-span-2': ['branchMix', 'weekdayRadar', 'categoryRadar', 'movementMix', 'products'].includes(widget.kind),
                    }"
                >
                    <header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-base font-black text-slate-950">
                                    {{ widgetTitle(widget) }}
                                </h2>
                            </div>
                            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">
                                {{ dataFor(widget.id).label }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <select
                                :value="widgetPeriod(widget.id)"
                                class="rounded-2xl border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-[11px] font-bold uppercase tracking-[0.16em] text-slate-700 outline-none transition focus:border-slate-400 focus:bg-white"
                                @change="setWidgetPeriod(widget.id, $event.target.value)"
                            >
                                <option
                                    v-for="option in periodOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>

                            <button
                                type="button"
                                class="rounded-2xl border border-slate-200 px-2.5 py-1.5 text-[11px] font-bold text-slate-700 transition hover:border-rose-300 hover:text-rose-700"
                                @click="hideWidget(widget.id)"
                            >
                                Ocultar
                            </button>
                        </div>
                    </header>

                    <div v-if="widget.kind === 'branchMix'" class="mt-3">
                        <VueApexCharts
                            height="310"
                            type="line"
                            :options="branchMixOptions(widget.id)"
                            :series="branchMixSeries(widget.id)"
                        />

                        <div class="mt-1.5 grid gap-1.5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                            <button
                                v-for="row in compactRows(branchRows(widget.id), 4)"
                                :key="row.id"
                                type="button"
                                class="rounded-2xl border border-slate-100 bg-slate-50 px-3 py-2 text-left transition hover:border-slate-300"
                            >
                                <span class="block text-xs font-bold text-slate-500">{{ row.name }}</span>
                                <span class="mt-1 block text-sm font-black text-slate-950">
                                    {{ formatCurrency(row.revenue) }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <div v-else-if="widget.kind === 'weekdayRadar'" class="mt-3">
                        <div class="mb-3 rounded-3xl bg-gradient-to-r from-rose-50 via-white to-emerald-50 px-4 py-3">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">
                                Producto analizado
                            </p>
                            <p class="mt-1 text-lg font-black text-slate-950">
                                {{ weekdayRadarData(widget.id).product?.name ?? 'Sin ventas en el periodo' }}
                            </p>
                            <p class="text-xs font-bold text-slate-500">
                                {{ formatNumber(weekdayRadarData(widget.id).product?.units ?? 0) }} unidades vendidas
                            </p>
                        </div>

                        <VueApexCharts
                            height="380"
                            type="radar"
                            :options="weekdayRadarOptions(widget.id)"
                            :series="weekdayRadarSeries(widget.id)"
                        />
                    </div>

                    <div v-else-if="widget.kind === 'categoryRadar'" class="mt-3">
                        <VueApexCharts
                            height="330"
                            type="area"
                            :options="categoryBarOptions(widget.id)"
                            :series="categoryBarSeries(widget.id)"
                        />

                        <div class="mt-3 grid grid-cols-2 gap-2 md:grid-cols-4">
                            <div
                                v-for="row in compactRows(categoryRows(widget.id), 4)"
                                :key="row.category_name"
                                class="rounded-2xl bg-slate-50 px-3 py-2"
                            >
                                <p class="truncate text-xs font-bold text-slate-500">{{ row.category_name }}</p>
                                <p class="text-sm font-black text-slate-950">{{ formatCurrency(row.revenue) }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="widget.kind === 'paymentMethods'" class="mt-3">
                        <VueApexCharts
                            height="220"
                            type="bar"
                            :options="paymentOptions(widget.id)"
                            :series="paymentSeries(widget.id)"
                        />

                        <div class="mt-3 max-h-32 overflow-auto rounded-2xl border border-slate-100">
                            <table class="min-w-full divide-y divide-slate-100 text-xs">
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="row in compactRows(paymentRows(widget.id), 5)"
                                        :key="row.id"
                                    >
                                        <td class="px-3 py-2 font-bold text-slate-900">{{ row.name }}</td>
                                        <td class="px-3 py-2 text-right font-black text-slate-950">{{ formatCurrency(row.total) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-else-if="widget.kind === 'movementMix'" class="mt-3">
                        <VueApexCharts
                            height="285"
                            type="heatmap"
                            :options="movementOptions(widget.id)"
                            :series="movementSeries(widget.id)"
                        />

                        <div class="mt-3 grid grid-cols-2 gap-2 md:grid-cols-5">
                            <div
                                v-for="row in compactRows(movementRows(widget.id), 4)"
                                :key="`${row.reason}-${row.type}`"
                                class="rounded-2xl bg-slate-50 px-3 py-2"
                            >
                                <p class="truncate text-xs font-bold text-slate-500">{{ movementLabel(row.reason) }}</p>
                                <p class="text-sm font-black text-slate-950">{{ formatNumber(row.movements) }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="widget.kind === 'branchMetric'" class="mt-3">
                        <div class="mb-1.5 rounded-2xl bg-slate-50 px-3 py-2">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-slate-400">
                                Total visible
                            </p>
                            <p class="mt-1 text-lg font-black text-slate-950">
                                {{ formatCurrency(metricTotal(widget.id, widget.metric)) }}
                            </p>
                        </div>

                        <VueApexCharts
                            height="230"
                            type="bar"
                            :options="branchMetricOptions(widget.id, widget.tone)"
                            :series="branchMetricSeries(widget.id, widget.metric, widget.valueLabel)"
                        />

                        <div class="mt-1.5 max-h-28 overflow-auto rounded-2xl border border-slate-100">
                            <table class="min-w-full divide-y divide-slate-100 text-xs">
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="row in compactRows(branchRows(widget.id), 6)"
                                        :key="row.id"
                                        class="bg-white"
                                    >
                                        <td class="px-3 py-2 font-bold text-slate-900">
                                            {{ row.name }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-black text-slate-950">
                                            {{ formatCurrency(row[widget.metric]) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-else-if="widget.kind === 'shrinkage'" class="mt-3">
                        <div class="mb-1.5 grid gap-1.5 sm:grid-cols-3">
                            <div class="rounded-2xl bg-rose-50 px-3 py-2">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-rose-400">
                                    Costo
                                </p>
                                <p class="mt-1 text-lg font-black text-rose-950">
                                    {{ formatCurrency(dataFor(widget.id).shrinkageSummary.cost_loss) }}
                                </p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-3 py-2">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">
                                    Venta perdida
                                </p>
                                <p class="mt-1 text-lg font-black text-slate-950">
                                    {{ formatCurrency(dataFor(widget.id).shrinkageSummary.revenue_loss) }}
                                </p>
                            </div>
                            <div class="rounded-2xl bg-slate-50 px-3 py-2">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">
                                    Unidades
                                </p>
                                <p class="mt-1 text-lg font-black text-slate-950">
                                    {{ formatNumber(dataFor(widget.id).shrinkageSummary.units, 1) }}
                                </p>
                            </div>
                        </div>

                        <VueApexCharts
                            height="260"
                            type="area"
                            :options="shrinkageOptions(widget.id)"
                            :series="shrinkageSeries(widget.id)"
                        />

                        <div class="mt-1.5 max-h-28 overflow-auto rounded-2xl border border-slate-100">
                            <table class="min-w-full divide-y divide-slate-100 text-xs">
                                <tbody class="divide-y divide-slate-100">
                                    <tr
                                        v-for="row in compactRows(shrinkageRows(widget.id), 6)"
                                        :key="row.category_name"
                                        class="bg-white transition hover:bg-rose-50"
                                    >
                                        <td class="px-3 py-2 font-bold text-slate-900">
                                            {{ row.category_name }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-black text-rose-700">
                                            {{ formatCurrency(row.cost_loss) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-else-if="widget.kind === 'products'" class="mt-3">
                        <VueApexCharts
                            height="360"
                            type="heatmap"
                            :options="productTreeOptions(widget.id)"
                            :series="productTreeSeries(widget.id)"
                        />

                        <div class="mt-3 grid gap-2 md:grid-cols-4">
                            <div
                                v-for="row in compactRows(productRows(widget.id), 4)"
                                :key="row.id"
                                class="rounded-2xl bg-slate-50 px-3 py-2"
                            >
                                <p class="truncate text-xs font-bold text-slate-500">{{ row.name }}</p>
                                <p class="text-sm font-black text-slate-950">{{ formatCurrency(row.revenue) }}</p>
                                <p class="text-xs font-bold text-emerald-700">{{ formatCurrency(row.profit) }} utilidad</p>
                            </div>
                        </div>
                    </div>
                </article>
            </section>
            </main>
        </div>
    </PageLayout>
</template>
