import { computed, reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'

import {
    INVENTORY_REPORT_STATUS_FILTERS,
    getInventoryReportType,
} from '@/config/ToolbarConfigs/inventoryReportToolbarConfig'

export function useInventoryReport(props) {
    const { handlePageChange } = useGlobalTablePagination()
    const filtersState = reactive({
        reportType: props.activeReport ?? 'dashboard',
        status: props.filters?.status ?? '',
        categoryId: props.filters?.category_id ?? '',
        productId: props.filters?.product_id ?? '',
        dateFrom: props.filters?.date_from ?? '',
        dateTo: props.filters?.date_to ?? '',
        search: props.filters?.search ?? '',
        perPage: Number(props.filters?.per_page ?? 25),
    })

    let refreshTimeout = null

    const pageTitle = computed(() => {
        return `Reportes de inventario - ${props.currentBranch?.name ?? 'Sucursal'}`
    })

    const categoryOptions = computed(() => props.catalogs?.categories ?? [])
    const productOptions = computed(() => props.catalogs?.products ?? [])

    const currentReport = computed(() => {
        return getInventoryReportType(filtersState.reportType)
    })

    const activeReportData = computed(() => {
        return props.reports?.[currentReport.value.dataKey] ?? null
    })

    const tableRows = computed(() => {
        return activeReportData.value?.data ?? activeReportData.value ?? []
    })

    const tablePagination = computed(() => {
        return activeReportData.value?.data ? activeReportData.value : null
    })

    function backToReportsCenter() {
        router.get(route('inventory.branches.reports', {
            branch: props.currentBranch.id,
        }))
    }

    function setStatusFilter(status) {
        filtersState.status = status.id
        filtersState.reportType = status.report
    }

    function resetFilters() {
        filtersState.reportType = 'dashboard'
        filtersState.status = ''
        filtersState.categoryId = ''
        filtersState.productId = ''
        filtersState.dateFrom = ''
        filtersState.dateTo = ''
        filtersState.search = ''
        filtersState.perPage = 25
    }

    function updateSearch(value) {
        filtersState.search = value
    }

    function updateFilter({ key, value }) {
        if (key === 'status') {
            const selectedStatus = INVENTORY_REPORT_STATUS_FILTERS.find((status) => status.id === value)
                ?? INVENTORY_REPORT_STATUS_FILTERS[0]

            setStatusFilter(selectedStatus)
            return
        }

        filtersState[key] = value
    }

    function getRequestFilters() {
        return {
            report: filtersState.reportType || undefined,
            status: filtersState.status || undefined,
            category_id: filtersState.categoryId || undefined,
            product_id: filtersState.productId || undefined,
            search: filtersState.search || undefined,
            date_from: filtersState.dateFrom || undefined,
            date_to: filtersState.dateTo || undefined,
            per_page: filtersState.perPage || 25,
        }
    }

    function reloadReport(pageUrl = null) {
        router.get(
            pageUrl ?? route('inventory.branches.reports.inventory', {
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

    function downloadReport(format) {
        const routeName = format === 'pdf'
            ? 'inventory.branches.reports.inventory.pdf'
            : 'inventory.branches.reports.inventory.excel'

        const url = route(routeName, {
            branch: props.currentBranch.id,
            ...getRequestFilters(),
        })

        window.location.href = url
    }

    function scheduleRefresh() {
        window.clearTimeout(refreshTimeout)

        refreshTimeout = window.setTimeout(() => {
            reloadReport()
        }, 350)
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
        filtersState,
        () => scheduleRefresh(),
        { deep: true },
    )

    return {
        filtersState,
        pageTitle,
        categoryOptions,
        productOptions,
        tableRows,
        tablePagination,
        backToReportsCenter,
        updateSearch,
        updateFilter,
        handlePageChange,
        reloadReport,
        handleToolbarAction,
    }
}
