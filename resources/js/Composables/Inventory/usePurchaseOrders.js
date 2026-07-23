import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'

export function usePurchaseOrders(props, routeName = 'inventory.branches.reports.purchase-orders') {
    const localFilters = ref({
        search: props.filters?.search ?? '',
        status: props.filters?.status ?? 'GENERATE',
        per_page: Number(props.filters?.per_page ?? 25),
    })
    let filterTimer = null

    const rows = computed(() => props.ordersDB?.data ?? [])
    const pagination = computed(() => props.ordersDB ?? {})
    const loadingOrder = ref(false)

    watch(localFilters, () => {
        clearTimeout(filterTimer)
        filterTimer = setTimeout(() => applyFilters({ page: 1 }), 300)
    }, { deep: true })

    onBeforeUnmount(() => clearTimeout(filterTimer))

    function applyFilters(overrides = {}) {
        router.get(
            route(routeName, {
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

    async function fetchOrder(orderId) {
        loadingOrder.value = true

        try {
            const { data } = await window.axios.get(
                route('inventory.branches.reports.purchase-orders.show', {
                    branch: props.currentBranch.id,
                    generalPurchaseOrder: orderId,
                }),
            )

            return data
        } finally {
            loadingOrder.value = false
        }
    }

    return {
        localFilters,
        rows,
        pagination,
        updateFilter,
        backToReportsCenter,
        createPurchaseList,
        fetchOrder,
        loadingOrder,
    }
}
