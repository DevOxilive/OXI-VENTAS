import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

export function usePurchaseOrders(props) {
    const localFilters = ref({
        search: props.filters?.search ?? '',
        status: props.filters?.status ?? '',
        per_page: Number(props.filters?.per_page ?? 25),
    })
    let filterTimer = null

    const rows = computed(() => props.ordersDB?.data ?? [])
    const pagination = computed(() => props.ordersDB ?? {})

    watch(localFilters, () => {
        clearTimeout(filterTimer)
        filterTimer = setTimeout(() => applyFilters({ page: 1 }), 300)
    }, { deep: true })

    onBeforeUnmount(() => clearTimeout(filterTimer))

    function applyFilters(overrides = {}) {
        router.get(
            route('inventory.branches.reports.purchase-orders', {
                branch: props.currentBranch.id,
            }),
            {
                search: localFilters.value.search || undefined,
                status: localFilters.value.status || undefined,
                per_page: localFilters.value.per_page,
                ...overrides,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        )
    }

    function updateFilter({ key, value }) {
        if (!(key in localFilters.value)) return

        localFilters.value[key] = value
    }

    function backToReportsCenter() {
        router.get(route('inventory.branches.reports', {
            branch: props.currentBranch.id,
        }))
    }

    function createPurchaseList() {
        router.get(route('inventory.branches.purchase-reports.index', {
            branch: props.currentBranch.id,
        }))
    }

    return {
        localFilters,
        rows,
        pagination,
        updateFilter,
        backToReportsCenter,
        createPurchaseList,
    }
}
