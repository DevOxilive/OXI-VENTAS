import { computed, reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'

export function useSalesReport(props) {
    const { handlePageChange } = useGlobalTablePagination()

    const filtersState = reactive({
        tab: props.activeTab ?? 'pedido',
        search: props.filters?.search ?? '',
        folio: props.filters?.folio ?? '',
        dateFrom: props.filters?.date_from ?? '',
        dateTo: props.filters?.date_to ?? '',
        coverageMonths: Number(props.filters?.coverage_months ?? 2),
        branchId: props.filters?.branch_id ? Number(props.filters.branch_id) : null,
        branchIds: normalizeArray(props.filters?.branch_ids),
        departmentIds: normalizeArray(props.filters?.department_ids),
        categoryIds: normalizeArray(props.filters?.category_ids),
        productIds: normalizeArray(props.filters?.product_ids),
        sectionPeriods: props.filters?.section_periods ?? {},
        perPage: Number(props.filters?.per_page ?? 25),
    })

    let refreshTimeout = null
    let syncingFilters = false

    const activeTab = computed(() => filtersState.tab || 'pedido')
    const isSalesTab = computed(() => activeTab.value === 'sales')
    const isReplenishmentTab = computed(() => activeTab.value !== 'sales')
    const isGlobalReport = computed(() => (props.reportScope ?? props.filters?.scope) === 'global')
    const branchOptions = computed(() => props.branchesDB ?? [])
    const showBranchFilter = computed(() => isGlobalReport.value)
    const tableRows = computed(() => (
        isSalesTab.value
            ? (props.registeredSales?.data ?? [])
            : []
    ))
    const tablePagination = computed(() => (
        isSalesTab.value
            ? props.registeredSales
            : props.productsSold
    ))

    const salesActions = [
        {
            id: 'view',
            label: 'Ver',
            icon: 'visibility',
            variant: 'blue',
            permission: 'reports.sales.view',
        },
        {
            id: 'pdf',
            label: 'Imprimir PDF',
            icon: 'picture_as_pdf',
            variant: 'red',
            permission: 'reports.sales.export.pdf',
        },
        {
            id: 'excel',
            label: 'Descargar Excel',
            icon: 'table_view',
            variant: 'green',
            permission: 'reports.sales.export.excel',
        },
    ]

    function backToReportsCenter() {
        router.get(route('inventory.reports.sales'))
    }

    function resetFilters() {
        filtersState.search = ''
        filtersState.folio = ''
        filtersState.dateFrom = ''
        filtersState.dateTo = ''
        filtersState.coverageMonths = 2
        filtersState.branchId = props.currentBranch?.id ? Number(props.currentBranch.id) : null
        filtersState.branchIds = []
        filtersState.departmentIds = []
        filtersState.categoryIds = []
        filtersState.productIds = []
        filtersState.sectionPeriods = {}
        filtersState.perPage = 25
    }

    function updateSearch(value) {
        filtersState.search = value
    }

    function updateTab(tab) {
        filtersState.tab = tab
    }

    function updateFilter({ key, value }) {
        filtersState[key] = ['branchId', 'coverageMonths'].includes(key) ? Number(value) : value
    }

    function updateSectionPeriod(sectionKey, dateFrom) {
        filtersState.sectionPeriods = {
            ...filtersState.sectionPeriods,
            [sectionKey]: dateFrom,
        }
    }

    function getRequestFilters(overrides = {}) {
        return {
            tab: filtersState.tab,
            search: filtersState.search || undefined,
            folio: isSalesTab.value ? (filtersState.folio || undefined) : undefined,
            date_from: filtersState.dateFrom || undefined,
            date_to: filtersState.dateTo || undefined,
            coverage_months: isReplenishmentTab.value ? filtersState.coverageMonths : undefined,
            branch_ids: isReplenishmentTab.value ? filtersState.branchIds : undefined,
            department_ids: isReplenishmentTab.value ? filtersState.departmentIds : undefined,
            category_ids: isReplenishmentTab.value ? filtersState.categoryIds : undefined,
            product_ids: isReplenishmentTab.value ? filtersState.productIds : undefined,
            section_periods: isReplenishmentTab.value ? filtersState.sectionPeriods : undefined,
            per_page: filtersState.perPage || 25,
            ...overrides,
        }
    }

    function reportRoute() {
        if (isGlobalReport.value) {
            return route('inventory.reports.sales')
        }

        return route('inventory.branches.reports.sales', {
            branch: filtersState.branchId || props.currentBranch?.id,
        })
    }

    function reloadReport(pageUrl = null) {
        router.get(pageUrl ?? reportRoute(), getRequestFilters(), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        })
    }

    function downloadReport(format) {
        const routeName = isGlobalReport.value
            ? (activeTab.value === 'sales'
                ? (format === 'pdf'
                    ? 'inventory.reports.sales.registered.pdf'
                    : 'inventory.reports.sales.registered.excel')
                : 'inventory.reports.sales.products.excel')
            : (activeTab.value === 'sales'
                ? (format === 'pdf'
                    ? 'inventory.branches.reports.sales.registered.pdf'
                    : 'inventory.branches.reports.sales.registered.excel')
                : 'inventory.branches.reports.sales.products.excel')

        const params = {
            ...(!isGlobalReport.value ? { branch: filtersState.branchId || props.currentBranch?.id } : {}),
            ...getRequestFilters(),
        }

        window.location.href = route(routeName, params)
    }

    function downloadSale(row, format) {
        const routeName = format === 'pdf'
            ? 'inventory.reports.sales.sale.pdf'
            : 'inventory.reports.sales.sale.excel'

        window.location.href = route(routeName, {
            sale: row.id,
        })
    }

    function scheduleRefresh() {
        window.clearTimeout(refreshTimeout)
        refreshTimeout = window.setTimeout(() => reloadReport(), 350)
    }

    function handleToolbarAction(action) {
        const actionId = typeof action === 'string' ? action : action?.id

        if (actionId === 'clear') {
            resetFilters()
            return
        }

        if (actionId === 'excel') {
            downloadReport('excel')
            return
        }

        if (actionId === 'pdf') {
            downloadReport('pdf')
        }
    }

    watch(
        () => props.filters,
        (filters) => {
            syncingFilters = true
            filtersState.tab = props.activeTab ?? filters?.tab ?? 'pedido'
            filtersState.search = filters?.search ?? ''
            filtersState.folio = filters?.folio ?? ''
            filtersState.dateFrom = filters?.date_from ?? ''
            filtersState.dateTo = filters?.date_to ?? ''
            filtersState.coverageMonths = Number(filters?.coverage_months ?? 2)
            filtersState.branchId = filters?.branch_id ? Number(filters.branch_id) : (props.currentBranch?.id ? Number(props.currentBranch.id) : null)
            filtersState.branchIds = normalizeArray(filters?.branch_ids)
            filtersState.departmentIds = normalizeArray(filters?.department_ids)
            filtersState.categoryIds = normalizeArray(filters?.category_ids)
            filtersState.productIds = normalizeArray(filters?.product_ids)
            filtersState.sectionPeriods = filters?.section_periods ?? {}
            filtersState.perPage = Number(filters?.per_page ?? 25)

            setTimeout(() => {
                syncingFilters = false
            }, 0)
        },
        { deep: true },
    )

    watch(
        filtersState,
        () => {
            if (syncingFilters) return
            scheduleRefresh()
        },
        { deep: true },
    )

    return {
        filtersState,
        activeTab,
        isSalesTab,
        isReplenishmentTab,
        isGlobalReport,
        branchOptions,
        showBranchFilter,
        tableRows,
        tablePagination,
        salesActions,
        backToReportsCenter,
        updateSearch,
        updateTab,
        updateFilter,
        handlePageChange,
        handleToolbarAction,
        downloadSale,
        updateSectionPeriod,
    }
}

function normalizeArray(value) {
    if (!Array.isArray(value)) return []

    return value.map((item) => String(item))
}
