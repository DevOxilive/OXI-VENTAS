import { computed, reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'

export function useInventoryMovementReport(props) {
    const { handlePageChange } = useGlobalTablePagination()
    const filtersState = reactive({
        categoryId: props.filters?.category_id ?? '',
        productId: props.filters?.product_id ?? '',
        userId: props.filters?.user_id ?? '',
        movementType: props.filters?.movement_type ?? '',
        movementReason: props.filters?.movement_reason ?? '',
        dateFrom: props.filters?.date_from ?? '',
        dateTo: props.filters?.date_to ?? '',
        search: props.filters?.search ?? '',
    })

    let refreshTimeout = null

    const pageTitle = computed(() => {
        return `Reportes de movimientos - ${props.currentBranch?.name ?? 'Sucursal'}`
    })

    const categoryOptions = computed(() => props.catalogs?.categories ?? [])
    const productOptions = computed(() => props.catalogs?.products ?? [])
    const userOptions = computed(() => props.catalogs?.users ?? [])
    const movementTypeOptions = computed(() => props.catalogs?.movementTypes ?? [])
    const movementReasonOptions = computed(() => props.catalogs?.movementReasons ?? [])

    const tableRows = computed(() => props.movements?.data ?? props.movements ?? [])
    const tablePagination = computed(() => props.movements?.data ? props.movements : null)

    function backToReportsCenter() {
        router.get(route('inventory.branches.reports', {
            branch: props.currentBranch.id,
        }))
    }

    function resetFilters() {
        filtersState.categoryId = ''
        filtersState.productId = ''
        filtersState.userId = ''
        filtersState.movementType = ''
        filtersState.movementReason = ''
        filtersState.dateFrom = ''
        filtersState.dateTo = ''
        filtersState.search = ''
    }

    function updateSearch(value) {
        filtersState.search = value
    }

    function updateFilter({ key, value }) {
        filtersState[key] = value
    }

    function getRequestFilters() {
        return {
            category_id: filtersState.categoryId || undefined,
            product_id: filtersState.productId || undefined,
            user_id: filtersState.userId || undefined,
            movement_type: filtersState.movementType || undefined,
            movement_reason: filtersState.movementReason || undefined,
            search: filtersState.search || undefined,
            date_from: filtersState.dateFrom || undefined,
            date_to: filtersState.dateTo || undefined,
        }
    }

    function reloadReport(pageUrl = null) {
        router.get(
            pageUrl ?? route('inventory.branches.reports.movements', {
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
            ? 'inventory.branches.reports.movements.pdf'
            : 'inventory.branches.reports.movements.excel'

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
        userOptions,
        movementTypeOptions,
        movementReasonOptions,
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
