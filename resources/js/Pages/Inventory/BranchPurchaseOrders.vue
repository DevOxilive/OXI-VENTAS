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
import { REALTIME_CHANNELS, REALTIME_EVENTS, refreshRealtimeProps, subscribePrivateRealtime } from '@/realtime'

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
    status: props.filters?.status ?? 'GENERATED',
    per_page: Number(props.filters?.per_page ?? 25),
})
let unsubscribeOrderActivity = null

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
const toolbarActions = computed(() => props.selectorMode ? [] : [
    {
        id: 'notifications',
        label: 'Avisos',
        icon: 'notifications',
        variant: notificationCount.value ? 'amber' : 'slate',
        badge: notificationCount.value || '',
    },
])
const tableConfig = computed(() => getBranchPurchaseOrdersTableConfig({
    status: localFilters.value.status,
}))
const toolbarConfig = computed(() => props.selectorMode
    ? {
        title: 'Órdenes de compra',
        subtitle: 'Selecciona la sucursal cuyas órdenes deseas consultar.',
        showSearch: false,
        showRecordsPerPage: false,
        showCounter: false,
        filters: [],
        actions: [],
        tabs: [],
    }
    : getBranchPurchaseOrdersToolbarConfig({
        filters: localFilters.value,
        total: Number(pagination.value?.total ?? 0),
        branchName: props.currentBranch?.name,
        actions: toolbarActions.value,
    }))

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

            refreshRealtimeProps(page, ['ordersDB', 'notificationSummary'])
        },
    )
})

onBeforeUnmount(() => unsubscribeOrderActivity?.())

function applyStatus(status) {
    router.get(
        route('ventas.purchase-orders.index'),
        {
            branch: props.currentBranch.id,
            status: status || 'GENERATED',
            per_page: localFilters.value.per_page,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    )
}

function updateStatus(status) {
    if (!status || status === localFilters.value.status) return

    localFilters.value.status = status
    applyStatus(status)
}

function updatePerPage(perPage) {
    localFilters.value.per_page = Number(perPage)
    applyStatus(localFilters.value.status)
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
    if (action === 'receive') {
        openOrder(row, 'edit')
        return
    }

    if (action === 'view') openOrder(row, 'view')
}

function handleToolbarAction(action) {
    if (action === 'notifications') {
        notificationPanelOpen.value = true
        markNotificationsSeen()
    }
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
    applyStatus('REVIEW')
}
</script>

<template>
    <Head title="Órdenes de compra" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
                @update:active-tab="updateStatus"
                @update:records-per-page="updatePerPage"
                @action="handleToolbarAction"
            />
        </template>

        <FloatingNotificationModal
            :open="notificationPanelOpen"
            eyebrow="Órdenes de compra"
            title="Avisos de seguimiento"
            subtitle="Cambios de Inventario y órdenes listas para revisar."
            :items="visibleNotificationItems"
            empty-description="No hay órdenes de compra con avisos pendientes."
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
                subtitle="Órdenes de compra"
                description="Consulta las órdenes pendientes, por revisar y completadas de esta sucursal."
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
