<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import VueApexCharts from 'vue3-apexcharts'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import { getDashboardToolbarConfig } from '@/config/ToolbarConfigs/dashboardToolbarConfig'

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
const themeTick = ref(0)
const isLayoutEditing = ref(false)
const draggedWidgetId = ref(null)
const dragOverWidgetId = ref(null)

function cssVar(name, fallback) {
    if (typeof window === 'undefined') return fallback

    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim()
    return value || fallback
}

function hexToRgb(hex) {
    const normalized = String(hex || '').replace('#', '').trim()
    const value = normalized.length === 3
        ? normalized.split('').map((char) => `${char}${char}`).join('')
        : normalized

    if (!/^[0-9a-fA-F]{6}$/.test(value)) {
        return null
    }

    return {
        r: parseInt(value.slice(0, 2), 16),
        g: parseInt(value.slice(2, 4), 16),
        b: parseInt(value.slice(4, 6), 16),
    }
}

function withAlpha(hex, alpha) {
    const rgb = hexToRgb(hex)

    if (!rgb) return hex

    return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha})`
}

function dashboardPalette() {
    themeTick.value

    const text = cssVar('--text', '#0f0001')
    const background = cssVar('--background', '#fcf7f8')
    const primary = cssVar('--primary', '#e0000f')
    const secondary = cssVar('--secondary', '#f9e7e9')
    const accent = cssVar('--accent', '#996b00')

    return {
        text,
        background,
        primary,
        secondary,
        accent,
        muted: withAlpha(text, 0.7),
        soft: withAlpha(text, 0.5),
        grid: withAlpha(text, 0.14),
        primarySoft: withAlpha(primary, 0.18),
        accentSoft: withAlpha(accent, 0.18),
        textSoft: withAlpha(text, 0.16),
    }
}

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
        tone: 'primary',
        valueLabel: 'Ventas',
        wide: true,
    },
    {
        id: 'investment',
        title: 'Inversion',
        kind: 'branchMetric',
        metric: 'investment',
        tone: 'text',
        valueLabel: 'Inversion',
        wide: true,
    },
    {
        id: 'expectedProfit',
        title: 'Ganancias',
        kind: 'branchMetric',
        metric: 'expected_profit',
        tone: 'accent',
        valueLabel: 'Ganancia esperada',
        wide: true,
    },
    {
        id: 'profit',
        title: 'Utilidad generada',
        kind: 'branchMetric',
        metric: 'profit',
        tone: 'accent',
        valueLabel: 'Utilidad',
        wide: true,
    },
    {
        id: 'shrinkage',
        title: 'Mermas por categoria',
        kind: 'shrinkage',
        tone: 'primary',
        wide: true,
    },
    {
        id: 'topProducts',
        title: 'Productos con mayor movimiento',
        kind: 'products',
        tone: 'text',
        wide: true,
    },
]

function defaultLayout() {
    const order = widgetCatalog.map((widget) => widget.id)

    return {
        order,
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

function normalizeWidgetOrder(order = []) {
    const catalogIds = widgetCatalog.map((widget) => widget.id)
    const savedIds = Array.isArray(order) ? order : []
    const validSavedIds = savedIds.filter((id, index) =>
        catalogIds.includes(id) && savedIds.indexOf(id) === index
    )
    const missingIds = catalogIds.filter((id) => !validSavedIds.includes(id))

    return [...validSavedIds, ...missingIds]
}

function loadLayout() {
    if (typeof window === 'undefined') return defaultLayout()

    try {
        const saved = JSON.parse(window.localStorage.getItem(storageKey) ?? '{}')
        const defaults = defaultLayout()
        const legacyOrder = saved.order ?? [
            ...(saved.pinned ?? []),
            ...defaults.order.filter((id) => !(saved.pinned ?? []).includes(id)),
        ]

        return {
            order: normalizeWidgetOrder(legacyOrder),
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
    applyDashboardFilters()
})

function applyDashboardFilters(overrides = {}) {
    const nextDateFrom = overrides.date_from ?? dateFrom.value
    const nextDateTo = overrides.date_to ?? dateTo.value

    if (!nextDateFrom || !nextDateTo) return

    router.get(
        route('dashboard'),
        {
            period: overrides.period ?? props.filters.period ?? 'month',
            branch_id: (overrides.branch_id ?? selectedBranchId.value) || undefined,
            date_from: nextDateFrom,
            date_to: nextDateTo,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    )
}

function refreshDashboardTheme() {
    themeTick.value += 1
}

onMounted(() => {
    if (typeof window === 'undefined') return

    window.addEventListener('oxi-theme-change', refreshDashboardTheme)
})

onBeforeUnmount(() => {
    if (typeof window === 'undefined') return

    window.removeEventListener('oxi-theme-change', refreshDashboardTheme)
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

function setAllWidgetPeriods(period) {
    layout.value.periods = widgetCatalog.reduce((periods, widget) => ({
        ...periods,
        [widget.id]: period,
    }), {})
}

function resetDashboardLayout() {
    layout.value = {
        ...defaultLayout(),
        periods: {
            ...defaultLayout().periods,
        },
    }
    draggedWidgetId.value = null
    dragOverWidgetId.value = null
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

const visibleWidgets = computed(() => {
    const order = normalizeWidgetOrder(layout.value.order)
    const widgetsById = new Map(widgetCatalog.map((widget) => [widget.id, widget]))

    return order
        .map((id) => widgetsById.get(id))
        .filter(Boolean)
})

const chartPeriodValue = computed(() => {
    const periods = [...new Set(visibleWidgets.value.map((widget) => widgetPeriod(widget.id)))]

    return periods.length === 1 ? periods[0] : ''
})

const summaryCards = computed(() => [
    {
        label: 'Ventas',
        value: formatCurrency(props.summary.revenue),
        surface: 'bg-secondary',
        labelClass: 'text-primary',
        valueClass: 'text-text',
        formula: 'Ventas netas: suma de sales.total con status completed en el rango filtrado.',
    },
    {
        label: 'Inversion',
        value: formatCurrency(props.summary.investment),
        surface: 'bg-background',
        labelClass: 'text-text opacity-70',
        valueClass: 'text-text',
        formula: 'Costo vendido estimado: suma de sale_details.quantity por products.cost. No es costo historico exacto por lote.',
    },
    {
        label: 'Utilidad',
        value: formatCurrency(props.summary.profit),
        surface: 'bg-secondary',
        labelClass: 'text-accent',
        valueClass: 'text-text',
        formula: 'Utilidad bruta estimada: ventas netas menos costo vendido estimado.',
    },
    {
        label: 'Merma',
        value: formatCurrency(props.shrinkageSummary.cost_loss),
        surface: 'bg-secondary',
        labelClass: 'text-primary',
        valueClass: 'text-text',
        formula: 'Valor de merma estimado: movimientos DAMAGED, EXPIRED y INVENTORY_DIFFERENCE por products.cost.',
    },
])

const selectedBranchName = computed(() => {
    const branch = props.filters.branches?.find(
        (item) => Number(item.id) === Number(selectedBranchId.value),
    )

    return branch?.name ?? 'Todas las sucursales'
})

const dashboardToolbarConfig = computed(() =>
    getDashboardToolbarConfig({
        branchName: selectedBranchName.value,
        rangeLabel: props.filters.label,
        branches: props.filters.branches ?? [],
        selectedBranchId: selectedBranchId.value,
        period: props.filters.period ?? 'month',
        chartPeriod: chartPeriodValue.value,
        dateFrom: dateFrom.value,
        dateTo: dateTo.value,
        maxDate: props.filters.max_date,
        isLayoutEditing: isLayoutEditing.value,
    }),
)

function handleDashboardToolbarFilter({ key, value }) {
    if (key === 'chart_period') {
        if (value) {
            setAllWidgetPeriods(value)
        }

        return
    }

    if (key === 'branch_id') {
        selectedBranchId.value = value
        return
    }

    if (key === 'date_from') {
        dateFrom.value = value
        return
    }

    if (key === 'date_to') {
        dateTo.value = value
        return
    }

    if (key === 'period') {
        applyDashboardFilters({ period: value })
    }
}

function handleDashboardToolbarAction(action) {
    if (action === 'toggle-layout-edit') {
        isLayoutEditing.value = !isLayoutEditing.value
        draggedWidgetId.value = null
        dragOverWidgetId.value = null
        return
    }

    if (action === 'reset-layout') {
        resetDashboardLayout()
        return
    }

    if (action === 'reset-chart-periods') {
        layout.value.periods = defaultLayout().periods
    }
}

function handleWidgetDragStart(event, widget) {
    if (!isLayoutEditing.value) {
        event.preventDefault()
        return
    }

    draggedWidgetId.value = widget.id
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', widget.id)
}

function handleWidgetDragEnter(widget) {
    if (!isLayoutEditing.value || !draggedWidgetId.value || draggedWidgetId.value === widget.id) return

    dragOverWidgetId.value = widget.id
}

function handleWidgetDrop(widget) {
    if (!isLayoutEditing.value || !draggedWidgetId.value || draggedWidgetId.value === widget.id) {
        draggedWidgetId.value = null
        dragOverWidgetId.value = null
        return
    }

    const order = normalizeWidgetOrder(layout.value.order)
    const fromIndex = order.indexOf(draggedWidgetId.value)
    const toIndex = order.indexOf(widget.id)

    if (fromIndex === -1 || toIndex === -1) {
        draggedWidgetId.value = null
        dragOverWidgetId.value = null
        return
    }

    const nextOrder = [...order]
    const [movedWidgetId] = nextOrder.splice(fromIndex, 1)
    nextOrder.splice(toIndex, 0, movedWidgetId)

    layout.value.order = nextOrder
    draggedWidgetId.value = null
    dragOverWidgetId.value = null
}

function handleWidgetDragEnd() {
    draggedWidgetId.value = null
    dragOverWidgetId.value = null
}

function bentoWidgetClasses(widget) {
    const classes = {
        branchMix: 'lg:col-span-6 xl:col-span-12',
        weekdayRadar: 'lg:col-span-3 xl:col-span-5',
        categoryRadar: 'lg:col-span-3 xl:col-span-7',
        paymentMethods: 'lg:col-span-3 xl:col-span-4',
        movementMix: 'lg:col-span-3 xl:col-span-8',
        branchMetric: 'lg:col-span-3 xl:col-span-3',
        shrinkage: 'lg:col-span-3 xl:col-span-7',
        products: 'lg:col-span-3 xl:col-span-5',
    }

    return classes[widget.kind] ?? 'lg:col-span-3 xl:col-span-6'
}

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
    const palette = dashboardPalette()
    const tone = {
        primary: palette.primary,
        accent: palette.accent,
        text: palette.text,
    }[color] ?? palette.primary

    return {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            foreColor: palette.muted,
        },
        plotOptions: {
            bar: {
                horizontal: false,
                borderRadius: 7,
                columnWidth: '42%',
            },
        },
        colors: [tone],
        dataLabels: { enabled: false },
        grid: {
            borderColor: palette.grid,
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
    const palette = dashboardPalette()

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
            foreColor: palette.muted,
            sparkline: { enabled: false },
        },
        colors: [palette.primary, palette.text, palette.accent],
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
            borderColor: palette.grid,
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
    const palette = dashboardPalette()

    return {
        chart: {
            type: 'area',
            toolbar: { show: false },
            foreColor: palette.muted,
            stacked: false,
        },
        colors: [palette.primary, palette.accent, palette.text, palette.primary, palette.accent],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 3 },
        fill: {
            type: 'gradient',
            gradient: { opacityFrom: 0.35, opacityTo: 0.04 },
        },
        grid: {
            borderColor: palette.grid,
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
    const palette = dashboardPalette()

    return {
        chart: {
            type: 'bar',
            toolbar: { show: false },
            foreColor: palette.muted,
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
        colors: [palette.primary, palette.accent, palette.text, palette.primary, palette.accent],
        dataLabels: {
            enabled: true,
            formatter: (value) => formatCurrency(value),
            style: {
                fontSize: '11px',
                fontWeight: 800,
            },
        },
        grid: {
            borderColor: palette.grid,
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
    const palette = dashboardPalette()

    return {
        chart: {
            type: 'heatmap',
            toolbar: { show: false },
            foreColor: palette.muted,
        },
        colors: [palette.accent],
        plotOptions: {
            heatmap: {
                radius: 9,
                shadeIntensity: 0.22,
                useFillColorAsStroke: false,
                colorScale: {
                    ranges: [
                        { from: 0, to: 0, color: palette.background, name: 'Sin movimiento' },
                        { from: 1, to: 25, color: palette.secondary, name: 'Bajo' },
                        { from: 26, to: 100, color: palette.accentSoft, name: 'Medio' },
                        { from: 101, to: 10000, color: palette.accent, name: 'Alto' },
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
    const palette = dashboardPalette()

    return {
        chart: {
            type: 'area',
            toolbar: { show: false },
            foreColor: palette.muted,
        },
        colors: [palette.primary, palette.accent, palette.text, palette.primary, palette.accent],
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
    const palette = dashboardPalette()

    return {
        chart: {
            type: 'heatmap',
            toolbar: { show: false },
            foreColor: palette.muted,
        },
        colors: [palette.accent],
        plotOptions: {
            heatmap: {
                radius: 8,
                shadeIntensity: 0.45,
                colorScale: {
                    ranges: [
                        { from: 0, to: 0, color: palette.background, name: 'Sin venta' },
                        { from: 1, to: 250, color: palette.secondary, name: 'Bajo' },
                        { from: 251, to: 1000, color: palette.accentSoft, name: 'Medio' },
                        { from: 1001, to: 100000, color: palette.accent, name: 'Alto' },
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
    const palette = dashboardPalette()

    return {
        chart: {
            type: 'radar',
            toolbar: { show: false },
            foreColor: palette.muted,
        },
        colors: [palette.primary, palette.accent, palette.text, palette.primary, palette.accent],
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
                    strokeColors: palette.grid,
                    fill: {
                        colors: [palette.secondary, palette.background],
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
        <template #toolbar>
            <div class="space-y-4">
                <GlobalToolbar
                    v-bind="dashboardToolbarConfig"
                    @update:filter="handleDashboardToolbarFilter"
                    @action="handleDashboardToolbarAction"
                />

                <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <article
                        v-for="card in summaryCards"
                        :key="card.label"
                        :title="card.formula"
                        class="rounded-[22px] border border-secondary p-4 shadow-sm"
                        :class="card.surface"
                    >
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em]" :class="card.labelClass">
                            {{ card.label }}
                        </p>
                        <p class="mt-1 text-2xl font-black" :class="card.valueClass">
                            {{ card.value }}
                        </p>
                    </article>
                </section>
            </div>
        </template>

        <main class="space-y-4">
            <section class="grid grid-cols-1 gap-4 lg:grid-cols-6 xl:grid-cols-12">
                <article
                    v-for="widget in visibleWidgets"
                    :key="widget.id"
                    :draggable="isLayoutEditing"
                    class="overflow-hidden rounded-[26px] border border-secondary bg-background/95 p-4 shadow-sm transition"
                    :class="[
                        bentoWidgetClasses(widget),
                        isLayoutEditing ? 'cursor-grab ring-1 ring-primary/20 active:cursor-grabbing' : '',
                        draggedWidgetId === widget.id ? 'scale-[0.99] opacity-50' : '',
                        dragOverWidgetId === widget.id && draggedWidgetId !== widget.id ? 'border-primary bg-secondary' : '',
                    ]"
                    @dragstart="handleWidgetDragStart($event, widget)"
                    @dragenter.prevent="handleWidgetDragEnter(widget)"
                    @dragover.prevent
                    @drop.prevent="handleWidgetDrop(widget)"
                    @dragend="handleWidgetDragEnd"
                >
                    <header class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-2">
                            <span
                                v-if="isLayoutEditing"
                                class="material-symbols-outlined mt-0.5 shrink-0 text-[20px] text-primary"
                                title="Arrastra para reordenar"
                            >
                                drag_indicator
                            </span>

                            <div class="min-w-0">
                                <h2 class="truncate text-base font-black text-text">
                                    {{ widgetTitle(widget) }}
                                </h2>
                                <p class="mt-1 text-xs font-semibold uppercase tracking-[0.18em] text-text opacity-50">
                                    {{ dataFor(widget.id).label }}
                                </p>
                            </div>
                        </div>

                        <span
                            v-if="isLayoutEditing"
                            class="rounded-2xl border border-primary bg-secondary px-2.5 py-1.5 text-[11px] font-bold text-primary"
                        >
                            Movible
                        </span>
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
                                class="rounded-2xl border border-secondary bg-secondary px-3 py-2 text-left transition hover:border-primary"
                            >
                                <span class="block text-xs font-bold text-text opacity-70">{{ row.name }}</span>
                                <span class="mt-1 block text-sm font-black text-text">
                                    {{ formatCurrency(row.revenue) }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <div v-else-if="widget.kind === 'weekdayRadar'" class="mt-3">
                        <div class="mb-3 rounded-3xl border border-secondary bg-secondary px-4 py-3">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-text opacity-50">
                                Producto analizado
                            </p>
                            <p class="mt-1 text-lg font-black text-text">
                                {{ weekdayRadarData(widget.id).product?.name ?? 'Sin ventas en el periodo' }}
                            </p>
                            <p class="text-xs font-bold text-text opacity-70">
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
                                class="rounded-2xl bg-secondary px-3 py-2"
                            >
                                <p class="truncate text-xs font-bold text-text opacity-70">{{ row.category_name }}</p>
                                <p class="text-sm font-black text-text">{{ formatCurrency(row.revenue) }}</p>
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

                        <div class="mt-3 max-h-32 overflow-auto rounded-2xl border border-secondary">
                            <table class="min-w-full divide-y divide-secondary text-xs">
                                <tbody class="divide-y divide-secondary">
                                    <tr
                                        v-for="row in compactRows(paymentRows(widget.id), 5)"
                                        :key="row.id"
                                    >
                                        <td class="px-3 py-2 font-bold text-text">{{ row.name }}</td>
                                        <td class="px-3 py-2 text-right font-black text-text">{{ formatCurrency(row.total) }}</td>
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
                                class="rounded-2xl bg-secondary px-3 py-2"
                            >
                                <p class="truncate text-xs font-bold text-text opacity-70">{{ movementLabel(row.reason) }}</p>
                                <p class="text-sm font-black text-text">{{ formatNumber(row.movements) }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="widget.kind === 'branchMetric'" class="mt-3">
                        <div class="mb-1.5 rounded-2xl bg-secondary px-3 py-2">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-text opacity-50">
                                Total visible
                            </p>
                            <p class="mt-1 text-lg font-black text-text">
                                {{ formatCurrency(metricTotal(widget.id, widget.metric)) }}
                            </p>
                        </div>

                        <VueApexCharts
                            height="230"
                            type="bar"
                            :options="branchMetricOptions(widget.id, widget.tone)"
                            :series="branchMetricSeries(widget.id, widget.metric, widget.valueLabel)"
                        />

                        <div class="mt-1.5 max-h-28 overflow-auto rounded-2xl border border-secondary">
                            <table class="min-w-full divide-y divide-secondary text-xs">
                                <tbody class="divide-y divide-secondary">
                                    <tr
                                        v-for="row in compactRows(branchRows(widget.id), 6)"
                                        :key="row.id"
                                        class="bg-background"
                                    >
                                        <td class="px-3 py-2 font-bold text-text">
                                            {{ row.name }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-black text-text">
                                            {{ formatCurrency(row[widget.metric]) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div v-else-if="widget.kind === 'shrinkage'" class="mt-3">
                        <div class="mb-1.5 grid gap-1.5 sm:grid-cols-3">
                            <div class="rounded-2xl bg-secondary px-3 py-2">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-primary">
                                    Costo
                                </p>
                                <p class="mt-1 text-lg font-black text-text">
                                    {{ formatCurrency(dataFor(widget.id).shrinkageSummary.cost_loss) }}
                                </p>
                            </div>
                            <div class="rounded-2xl bg-secondary px-3 py-2">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-text opacity-50">
                                    Venta perdida
                                </p>
                                <p class="mt-1 text-lg font-black text-text">
                                    {{ formatCurrency(dataFor(widget.id).shrinkageSummary.revenue_loss) }}
                                </p>
                            </div>
                            <div class="rounded-2xl bg-secondary px-3 py-2">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-text opacity-50">
                                    Unidades
                                </p>
                                <p class="mt-1 text-lg font-black text-text">
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

                        <div class="mt-1.5 max-h-28 overflow-auto rounded-2xl border border-secondary">
                            <table class="min-w-full divide-y divide-secondary text-xs">
                                <tbody class="divide-y divide-secondary">
                                    <tr
                                        v-for="row in compactRows(shrinkageRows(widget.id), 6)"
                                        :key="row.category_name"
                                        class="bg-background transition hover:bg-secondary"
                                    >
                                        <td class="px-3 py-2 font-bold text-text">
                                            {{ row.category_name }}
                                        </td>
                                        <td class="px-3 py-2 text-right font-black text-primary">
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
                                class="rounded-2xl bg-secondary px-3 py-2"
                            >
                                <p class="truncate text-xs font-bold text-text opacity-70">{{ row.name }}</p>
                                <p class="text-sm font-black text-text">{{ formatCurrency(row.revenue) }}</p>
                                <p class="text-xs font-bold text-accent">{{ formatCurrency(row.profit) }} utilidad</p>
                            </div>
                        </div>
                    </div>
                </article>
            </section>
        </main>
    </PageLayout>
</template>
