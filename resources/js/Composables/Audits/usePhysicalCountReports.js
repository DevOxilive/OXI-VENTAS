import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

import { getPhysicalCountReportsToolbarConfig } from '@/config/ToolbarConfigs/physicalCountReportsToolbarConfig'

export function usePhysicalCountReports(props) {
    const form = reactive({
        branch: props.filters.branch || props.branch?.slug || '',
        physical_count_id: props.filters.physical_count_id || '',
        user_id: props.filters.user_id || '',
        category_id: props.filters.category_id || '',
        report_date: props.filters.report_date || '',
        date_scope: props.filters.date_scope || 'day',
        status: props.filters.status || '',
        search: props.filters.search || '',
        report_type: props.filters.report_type || 'summary',
        per_page: Number(props.filters.per_page || 25),
    })

    const syncingFromServer = ref(false)
    let filterTimeout = null

    const toolbarConfig = computed(() =>
        getPhysicalCountReportsToolbarConfig({
            branch: props.branch,
            form,
            branches: props.branches,
            audits: props.audits,
            users: props.users,
            categories: props.categories,
        })
    )

    const summaryTableItems = computed(() => [
        { id: 'audits', indicator: 'Auditorias', value: props.summary.audits ?? 0 },
        { id: 'records', indicator: 'Registros', value: props.summary.records ?? 0 },
        { id: 'participants', indicator: 'Usuarios', value: props.summary.participants ?? 0 },
        { id: 'counted_products', indicator: 'Contados', value: props.summary.counted_products ?? 0 },
        { id: 'pending_products', indicator: 'No encontrados', value: props.summary.pending_products ?? 0 },
        { id: 'missing_products', indicator: 'Faltantes', value: props.summary.missing_products ?? 0 },
        { id: 'surplus_products', indicator: 'Sobrantes', value: props.summary.surplus_products ?? 0 },
        { id: 'matched_products', indicator: 'Correctos', value: props.summary.matched_products ?? 0 },
    ])

    const reportTableItems = computed(() =>
        props.reportRows.map((row) => ({
            ...row,
            participantsLabel: Array.isArray(row.participants) ? row.participants.join(', ') : 'Sin registros',
            differenceLabel: row.difference ?? '-',
        }))
    )

    const differencesTableItems = computed(() =>
        props.topDifferences.map((row) => ({
            ...row,
            differenceLabel: row.difference ?? '-',
        }))
    )

    function backToReportsCenter() {
        router.get(route('inventory.branches.reports', {
            branch: props.branch?.id,
        }))
    }

    function buildQuery(overrides = {}) {
        return {
            branch: form.branch || props.branch?.slug || '',
            physical_count_id: form.physical_count_id || '',
            user_id: form.user_id || '',
            category_id: form.category_id || '',
            report_date: form.report_date || '',
            date_scope: form.date_scope || 'day',
            status: form.status || '',
            search: form.search || '',
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
            ],
        })
    }

    function scheduleFilterReload(delay = 250) {
        if (syncingFromServer.value) return

        clearTimeout(filterTimeout)
        filterTimeout = setTimeout(() => applyFilters(), delay)
    }

    function updateSearch(value) {
        form.search = value
    }

    function updateFilter({ key, value }) {
        if (key === 'branch' && value !== form.branch && form.physical_count_id) {
            form.physical_count_id = ''
        }

        if (key === 'search') {
            form.search = value
            return
        }

        form[key] = value
    }

    function clearFilters() {
        syncingFromServer.value = true
        form.branch = props.branch?.slug || ''
        form.physical_count_id = ''
        form.user_id = ''
        form.category_id = ''
        form.report_date = ''
        form.date_scope = 'day'
        form.status = ''
        form.search = ''
        form.report_type = 'summary'
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
            ],
        })
    }

    function handleToolbarAction(action) {
        const actionId = typeof action === 'string' ? action : action?.id

        if (actionId === 'clear') {
            clearFilters()
            return
        }

        if (actionId === 'excel') {
            window.location.href = route('audits.physical-counts.reports.export-excel', buildQuery())
            return
        }

        if (actionId === 'pdf') {
            window.location.href = route('audits.physical-counts.reports.export-pdf', buildQuery())
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
            form.search,
            form.report_type,
        ],
        () => scheduleFilterReload(250)
    )

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
            form.search = filters.search || ''
            form.report_type = filters.report_type || 'summary'
            form.per_page = Number(filters.per_page || 25)
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

        if (!window.Echo) return
        window.Echo.leave('audits')
    })

    return {
        form,
        toolbarConfig,
        summaryTableItems,
        reportTableItems,
        differencesTableItems,
        backToReportsCenter,
        updateSearch,
        updateFilter,
        handleToolbarAction,
        handlePageChange,
    }
}
