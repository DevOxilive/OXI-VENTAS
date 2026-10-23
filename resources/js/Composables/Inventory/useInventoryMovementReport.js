import { computed, reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'

function normalizeMultiValue(value) {
    return (Array.isArray(value) ? value : value ? [value] : [])
        .map((item) => String(item))
        .filter(Boolean)
}

export function useInventoryMovementReport(props) {
    const { handlePageChange } = useGlobalTablePagination()
    const filtersState = reactive({
        departmentIds: normalizeMultiValue(props.filters?.department_ids ?? props.filters?.department_id),
        categoryIds: normalizeMultiValue(props.filters?.category_ids ?? props.filters?.category_id),
        userIds: normalizeMultiValue(props.filters?.user_ids ?? props.filters?.user_id),
        movementTypes: normalizeMultiValue(props.filters?.movement_types ?? props.filters?.movement_type),
        movementReasons: normalizeMultiValue(props.filters?.movement_reasons ?? props.filters?.movement_reason),
        dateFrom: props.filters?.date_from ?? '',
        dateTo: props.filters?.date_to ?? '',
        search: props.filters?.search ?? '',
        perPage: Number(props.filters?.per_page ?? 25),
    })

    let refreshTimeout = null

    const pageTitle = computed(() => {
        return `Reportes de movimientos - ${props.currentBranch?.name ?? 'Sucursal'}`
    })

    const departmentOptions = computed(() => props.catalogs?.productDepartments ?? [])
    const categoryOptions = computed(() => props.catalogs?.categories ?? [])
    const filteredCategoryOptions = computed(() => {
        if (!filtersState.departmentIds.length) {
            return categoryOptions.value
        }

        const selectedDepartments = new Set(filtersState.departmentIds.map(String))

        return categoryOptions.value.filter((category) =>
            selectedDepartments.has(String(category.product_department_id)),
        )
    })
    const userOptions = computed(() => props.catalogs?.users ?? [])
    const movementTypeOptions = computed(() => props.catalogs?.movementTypes ?? [])
    const movementReasonOptions = computed(() => props.catalogs?.movementReasons ?? [])

    const tableRows = computed(() => props.movements?.data ?? props.movements ?? [])
    const tablePagination = computed(() => props.movements?.data ? props.movements : null)

    function backToReportsCenter() {
        router.get(route('inventory.reports.select', {
            report: 'movements',
        }))
    }

    function updateSearch(value) {
        filtersState.search = value
    }

    function updateFilter({ key, value }) {
        if (key === 'departmentIds') {
            filtersState.departmentIds = normalizeMultiValue(value)

            const validCategories = new Set(
                filteredCategoryOptions.value.map((category) => String(category.id)),
            )

            filtersState.categoryIds = filtersState.categoryIds.filter((categoryId) =>
                validCategories.has(String(categoryId)),
            )

            return
        }

        filtersState[key] = value
    }

    function getRequestFilters() {
        return {
            department_ids: filtersState.departmentIds.length ? filtersState.departmentIds : undefined,
            category_ids: filtersState.categoryIds.length ? filtersState.categoryIds : undefined,
            user_ids: filtersState.userIds.length ? filtersState.userIds : undefined,
            movement_types: filtersState.movementTypes.length ? filtersState.movementTypes : undefined,
            movement_reasons: filtersState.movementReasons.length ? filtersState.movementReasons : undefined,
            search: filtersState.search || undefined,
            date_from: filtersState.dateFrom || undefined,
            date_to: filtersState.dateTo || undefined,
            per_page: filtersState.perPage || 25,
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
        departmentOptions,
        categoryOptions,
        filteredCategoryOptions,
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
