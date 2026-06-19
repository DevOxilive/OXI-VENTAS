import { computed, reactive } from 'vue'
import { router } from '@inertiajs/vue3'

import {
    INVENTORY_REPORT_STATUS_FILTERS,
    getInventoryReportType,
} from '@/config/ToolbarConfigs/inventoryReportToolbarConfig'

export function useInventoryReport(props) {
    const filtersState = reactive({
        reportType: props.activeReport ?? 'dashboard',
        status: props.filters?.status ?? '',
        categoryId: props.filters?.category_id ?? '',
        dateFrom: props.filters?.date_from ?? '',
        dateTo: props.filters?.date_to ?? '',
        search: props.filters?.search ?? '',
    })

    const pageTitle = computed(() => {
        return `Reportes de inventario - ${props.currentBranch?.name ?? 'Sucursal'}`
    })

    const categoryOptions = computed(() => props.catalogs?.categories ?? [])

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
        filtersState.dateFrom = ''
        filtersState.dateTo = ''
        filtersState.search = ''
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
            search: filtersState.search || undefined,
            date_from: filtersState.dateFrom || undefined,
            date_to: filtersState.dateTo || undefined,
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

    function handleToolbarAction(action) {
        const actionId = typeof action === 'string' ? action : action?.id

        if (actionId === 'clear') {
            resetFilters()
            return
        }

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

    return {
        filtersState,
        pageTitle,
        categoryOptions,
        tableRows,
        tablePagination,
        backToReportsCenter,
        updateSearch,
        updateFilter,
        reloadReport,
        handleToolbarAction,
    }
}
