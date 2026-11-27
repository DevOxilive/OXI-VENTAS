<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalCard from '@/Components/Cards/GlobalCard.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import BranchPurchaseOrderModal from '@/Components/Inventory/PurchaseReports/BranchPurchaseOrderModal.vue'
import FloatingNotificationModal from '@/Components/Notifications/FloatingNotificationModal.vue'
import { ErrorAlert } from '@/Components/Modales/UniversalActionModal'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { useNotificationSeenState } from '@/Composables/useNotificationSeenState'
import { getBranchPurchaseOrdersTableConfig } from '@/config/TableConfigs/branchPurchaseOrdersTableConfig'
import { getBranchPurchaseOrdersToolbarConfig } from '@/config/ToolbarConfigs/branchPurchaseOrdersToolbarConfig'
import { REALTIME_CHANNELS, REALTIME_EVENTS, refreshRealtimeProps, subscribePrivateRealtime, subscribeRealtime } from '@/realtime'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    selectorMode: {
        type: Boolean,
        default: false,
    },
    currentBranch: {
        type: Object,
        default: null,
    },
    branchesDB: {
        type: Array,
        default: () => [],
    },
    ordersDB: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    notificationSummary: {
        type: Object,
        default: () => ({ mode: 'sales', count: 0, items: [] }),
    },
})

const page = usePage()
const { handlePageChange } = useGlobalTablePagination()
const selectedOrder = ref(null)
const modalMode = ref('view')
const loadingOrder = ref(false)
const notificationPanelOpen = ref(false)
const localFilters = ref({
    status: props.filters?.status ?? 'ALL',
    search: props.filters?.search ?? '',
    date_from: props.filters?.date_from ?? '',
    date_to: props.filters?.date_to ?? '',
    per_page: Number(props.filters?.per_page ?? 25),
})
let unsubscribeOrderActivity = null
let unsubscribePurchaseWorkflow = null
let realtimeRefreshTimer = null

const rows = computed(() => props.ordersDB?.data ?? [])
const pagination = computed(() => props.ordersDB ?? {})
const notificationItems = computed(() => props.notificationSummary?.items ?? [])
const notificationSeenStorageKey = computed(() => `purchase-orders:sales:seen:${page.props.auth.user.id}:${props.currentBranch?.id ?? 'all'}`)
const notificationDismissedStorageKey = computed(() => `purchase-orders:sales:dismissed:${page.props.auth.user.id}:${props.currentBranch?.id ?? 'all'}`)
const {
    visibleItems: visibleNotificationItems,
    unreadCount: notificationCount,
    loadSeenNotifications,
    loadDismissedNotifications,
    markNotificationsSeen,
    dismissNotification,
} = useNotificationSeenState({
    items: notificationItems,
    storageKey: notificationSeenStorageKey,
    dismissedStorageKey: notificationDismissedStorageKey,
})
const tableConfig = computed(() => getBranchPurchaseOrdersTableConfig({
    status: localFilters.value.status,
}))
const toolbarConfig = computed(() => props.selectorMode
    ? {
        title: 'Solicitudes de compra',
        subtitle: 'Selecciona la sucursal que deseas consultar.',
        showSearch: false,
        showRecordsPerPage: false,
        showCounter: false,
        filters: [],
        actions: [
            {
                id: 'notifications',
                label: 'Avisos',
                icon: 'notifications',
                variant: notificationCount.value ? 'amber' : 'slate',
                badge: notificationCount.value || '',
            },
        ],
        tabs: [],
    }
    : getBranchPurchaseOrdersToolbarConfig({
        filters: localFilters.value,
        total: Number(pagination.value?.total ?? 0),
        branchName: props.currentBranch?.name,
        actions: [],
    }))

async function refreshOpenView(orderId = null) {
    if (modalMode.value !== 'view' || !selectedOrder.value?.id) return
    if (orderId && Number(orderId) !== Number(selectedOrder.value.id)) return

    try {
        const { data } = await window.axios.get(route('inventory.branches.reports.purchases.show', {
            branch: props.currentBranch.id,
            purchaseReport: selectedOrder.value.id,
        }))
        selectedOrder.value = data
    } catch {
        // La tabla ya se sincronizó; no se interrumpe a la usuaria por un detalle que pudo cerrarse.
    }
}

function refreshPurchaseOrdersRealtime(event = null) {
    clearTimeout(realtimeRefreshTimer)
    realtimeRefreshTimer = window.setTimeout(async () => {
        await refreshRealtimeProps(page, ['ordersDB', 'notificationSummary'])
        await refreshOpenView(event?.purchaseOrderId ?? event?.purchase_order_id)
    }, 120)
}

onMounted(() => {
    if (!page.props.auth?.user?.id || props.selectorMode) return
    loadSeenNotifications()
    loadDismissedNotifications()
    unsubscribeOrderActivity = subscribePrivateRealtime(
        REALTIME_CHANNELS.user(page.props.auth.user.id),
        REALTIME_EVENTS.activityLogged,
        (event) => {
            if (![
                'review_requested',
                'purchase_order_reviewed',
                'purchase_order_edited',
            ].includes(event?.action)) return

            refreshPurchaseOrdersRealtime(event)
        },
    )

    unsubscribePurchaseWorkflow = subscribeRealtime(
        REALTIME_CHANNELS.systems,
        REALTIME_EVENTS.purchaseWorkflowChanged,
        refreshPurchaseOrdersRealtime,
    )
})

onBeforeUnmount(() => {
    unsubscribeOrderActivity?.()
    unsubscribePurchaseWorkflow?.()
    clearTimeout(realtimeRefreshTimer)
})

let searchTimer = null

function applyFilters() {
    router.get(
        route('ventas.purchase-orders.index'),
        {
            branch: props.currentBranch.id,
            status: localFilters.value.status || 'ALL',
            search: localFilters.value.search,
            date_from: localFilters.value.date_from,
            date_to: localFilters.value.date_to,
            per_page: localFilters.value.per_page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    )
}

function updateFilter({ key, value }) {
    if (!['status', 'date_from', 'date_to'].includes(key)) return

    localFilters.value[key] = value
    applyFilters()
}

function updatePerPage(perPage) {
    localFilters.value.per_page = Number(perPage)
    applyFilters()
}

function updateSearch(search) {
    localFilters.value.search = search
    clearTimeout(searchTimer)
    searchTimer = setTimeout(applyFilters, 300)
}

function selectBranch(branchId) {
    if (!branchId) return

    router.get(
        route('ventas.purchase-orders.index'),
        { branch: branchId },
        { preserveScroll: true, replace: true },
    )
}

async function openOrder(order, mode = 'view') {
    if (!order?.id || loadingOrder.value) return

    loadingOrder.value = true

    try {
        const { data } = await window.axios.get(
            route('inventory.branches.reports.purchases.show', {
                branch: props.currentBranch.id,
                purchaseReport: order.id,
            }),
        )

        modalMode.value = mode
        selectedOrder.value = data
    } catch {
        ErrorAlert({
            title: 'No se pudo abrir la orden',
            message: 'Actualiza la página y vuelve a intentarlo.',
        })
    } finally {
        loadingOrder.value = false
    }
}

function handleTableAction({ action, row }) {
    if (action === 'review') {
        openOrder(row, 'edit')
        return
    }

    if (action === 'view') openOrder(row, 'view')
}

function handleToolbarAction(action) {
    if (action !== 'notifications') return
    notificationPanelOpen.value = true
    markNotificationsSeen()
}

function closeNotificationPanel() {
    notificationPanelOpen.value = false
}

function openNotificationOrder(item) {
    closeNotificationPanel()
    openOrder({ id: item.id }, 'view')
}

function handleCompleted() {
    selectedOrder.value = null
    applyFilters()
}
</script>

<template>
    <Head title="Solicitudes de compra" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
                @update:search="updateSearch"
                @update:filter="updateFilter"
                @update:records-per-page="updatePerPage"
                @action="handleToolbarAction"
            />
        </template>

        <FloatingNotificationModal
            :open="notificationPanelOpen"
            eyebrow="Solicitudes de compra"
            title="Avisos de seguimiento"
            subtitle="Cambios de Inventario y solicitudes listas para revisar."
            :items="visibleNotificationItems"
            empty-description="No hay cambios pendientes por revisar."
            @close="closeNotificationPanel"
            @select="openNotificationOrder"
            @dismiss="dismissNotification"
        />

        <div
            v-if="selectorMode"
            class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
        >
            <GlobalCard
                v-for="branch in branchesDB"
                :key="branch.id"
                :title="branch.name"
                subtitle="Solicitudes de compra"
                description="Consulta las solicitudes de esta sucursal."
                icon="shopping_bag"
                badge="Seleccionar"
                @click="selectBranch(branch.id)"
            />
        </div>

        <template v-else>
            <div v-if="loadingOrder" class="mb-3 rounded-xl border border-secondary bg-secondary px-4 py-3 text-sm text-text">
                Cargando detalle de la orden...
            </div>

            <GlobalTable
                :items="rows"
                :pagination="pagination"
                :loading="false"
                v-bind="tableConfig"
                @action="handleTableAction"
                @page-change="handlePageChange"
            />
        </template>

        <BranchPurchaseOrderModal
            v-if="selectedOrder"
            :order="selectedOrder"
            :mode="modalMode"
            :branch-id="currentBranch.id"
            @close="selectedOrder = null"
            @completed="handleCompleted"
        />

    </PageLayout>
</template>
