import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

import { getPhysicalCountReportsToolbarConfig } from '@/config/ToolbarConfigs/physicalCountReportsToolbarConfig'
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribeRealtime } from '@/realtime'

export function usePhysicalCountReports(props) {
    let unsubscribePhysicalCountChanged = null
    let filterReloadTimeout = null
    let syncingFromServer = false

    const form = reactive({
        branch: props.filters.branch || '',
        search: props.filters.search || '',
        per_page: Number(props.filters.per_page || 25),
    })
    const auditConfigurations = ref(props.filters.audit_filters || {})

    const toolbarConfig = computed(() =>
        getPhysicalCountReportsToolbarConfig({
            branch: props.branch,
            form,
            pagination: props.auditPagination,
        })
    )

    function backToReportsCenter() {
        router.get(route('inventory.branches.reports', {
            branch: props.branch?.id,
        }))
    }

    function buildQuery(overrides = {}) {
        return {
            branch: form.branch || '',
            search: form.search || '',
            per_page: form.per_page,
            audit_filters: JSON.stringify(auditConfigurations.value),
            ...overrides,
        }
    }

    function applyFilters(overrides = {}) {
        router.get(route('audits.physical-counts.reports'), buildQuery(overrides), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['audits', 'auditPagination', 'filters', 'summary'],
        })
    }

    function updateSearch(value) {
        form.search = value
    }

    function updateRecordsPerPage(value) {
        form.per_page = Number(value)
    }

    function changePage(url) {
        if (!url) return
        const page = Number(new URL(url, window.location.origin).searchParams.get('page') || 1)
        applyFilters({ page })
    }

    function exportReport(type, auditId = null) {
        const routeName = type === 'pdf'
            ? 'audits.physical-counts.reports.export-pdf'
            : 'audits.physical-counts.reports.export-excel'
        const configuration = auditId === null
            ? auditConfigurations.value
            : {
                [String(auditId)]: auditConfigurations.value[String(auditId)] || {
                    user_ids: [],
                    category_ids: [],
                    results: [],
                    include_lots: false,
                },
            }

        window.location.href = route(routeName, buildQuery({
            audit_ids: auditId === null ? [] : [auditId],
            audit_filters: JSON.stringify(configuration),
        }))
    }

    function reloadReports() {
        router.reload({
            only: ['audits', 'auditPagination', 'filters', 'summary'],
            preserveScroll: true,
            preserveState: true,
        })
    }

    watch(
        () => props.filters,
        (filters) => {
            syncingFromServer = true
            form.branch = filters.branch || ''
            form.search = filters.search || ''
            form.per_page = Number(filters.per_page || 25)
            setTimeout(() => {
                syncingFromServer = false
            }, 0)
        },
        { deep: true },
    )

    watch(
        () => ({ search: form.search, per_page: form.per_page }),
        () => {
            if (syncingFromServer) return
            clearTimeout(filterReloadTimeout)
            filterReloadTimeout = setTimeout(applyFilters, 450)
        },
        { deep: true },
    )

    onMounted(() => {
        unsubscribePhysicalCountChanged = subscribeRealtime(
            REALTIME_CHANNELS.audits,
            REALTIME_EVENTS.physicalCountChanged,
            reloadReports,
        )
    })

    onBeforeUnmount(() => {
        clearTimeout(filterReloadTimeout)
        unsubscribePhysicalCountChanged?.()
    })

    return {
        form,
        toolbarConfig,
        auditConfigurations,
        backToReportsCenter,
        updateSearch,
        updateRecordsPerPage,
        changePage,
        exportReport,
    }
}
