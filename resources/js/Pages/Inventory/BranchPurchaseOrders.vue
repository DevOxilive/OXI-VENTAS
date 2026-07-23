<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalCard from '@/Components/Cards/GlobalCard.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import BranchPurchaseOrderModal from '@/Components/Inventory/PurchaseReports/BranchPurchaseOrderModal.vue'
import PendingPurchaseOrderEditModal from '@/Components/Inventory/PurchaseReports/PendingPurchaseOrderEditModal.vue'
import { ErrorAlert } from '@/Components/Modales/UniversalActionModal'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { getBranchPurchaseOrdersTableConfig } from '@/config/TableConfigs/branchPurchaseOrdersTableConfig'
import { getBranchPurchaseOrdersToolbarConfig } from '@/config/ToolbarConfigs/branchPurchaseOrdersToolbarConfig'
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribePrivateRealtime } from '@/realtime'

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
})

const page = usePage()
const { handlePageChange } = useGlobalTablePagination()
const selectedOrder = ref(null)
const modalMode = ref('view')
const editingPendingOrder = ref(null)
const loadingOrder = ref(false)
const localFilters = ref({
    status: props.filters?.status ?? 'GENERATED',
    per_page: Number(props.filters?.per_page ?? 25),
})
let unsubscribeOrderActivity = null

const rows = computed(() => props.ordersDB?.data ?? [])
const pagination = computed(() => props.ordersDB ?? {})
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
    }))

onMounted(() => {
    if (!page.props.auth?.user?.id || props.selectorMode) return

    unsubscribeOrderActivity = subscribePrivateRealtime(
        REALTIME_CHANNELS.user(page.props.auth.user.id),
        REALTIME_EVENTS.activityLogged,
        (event) => {
            if (event?.action !== 'review_requested') return

            router.reload({ only: ['ordersDB'], preserveScroll: true })
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
    if (action === 'edit') {
        if (row.status === 'GENERATED') {
            openPendingOrderEditor(row)
            return
        }

        openOrder(row, 'edit')
        return
    }

    if (action === 'view') openOrder(row, 'view')
}

async function openPendingOrderEditor(order) {
    if (!order?.id || loadingOrder.value) return

    loadingOrder.value = true

    try {
        const { data } = await window.axios.get(
            route('inventory.branches.reports.purchases.show', {
                branch: props.currentBranch.id,
                purchaseReport: order.id,
            }),
        )
        editingPendingOrder.value = data
    } catch {
        ErrorAlert({
            title: 'No se pudo abrir la orden',
            message: 'Actualiza la página y vuelve a intentarlo.',
        })
    } finally {
        loadingOrder.value = false
    }
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
            />
        </template>

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

        <PendingPurchaseOrderEditModal
            v-if="editingPendingOrder"
            :order="editingPendingOrder"
            :branch-id="currentBranch.id"
            :update-url="route('inventory.branches.purchase-reports.update', { branch: currentBranch.id, purchaseReport: editingPendingOrder.id })"
            @close="editingPendingOrder = null"
            @updated="editingPendingOrder = null"
        />
    </PageLayout>
</template>
