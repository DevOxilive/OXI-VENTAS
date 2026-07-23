<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import GeneralPurchaseOrderModal from '@/Components/Inventory/PurchaseReports/GeneralPurchaseOrderModal.vue'
import { ErrorAlert } from '@/Components/Modales/UniversalActionModal'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { usePurchaseOrders } from '@/Composables/Inventory/usePurchaseOrders'
import { getPurchaseOrdersTableConfig } from '@/config/TableConfigs/purchaseOrdersTableConfig'
import { getPurchaseOrdersToolbarConfig } from '@/config/ToolbarConfigs/purchaseOrdersToolbarConfig'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    currentBranch: { type: Object, required: true },
    ordersDB: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
})

const routeName = 'inventory.branches.reports.purchase-orders.tracking'
const orders = usePurchaseOrders(props, routeName)
const { handlePageChange } = useGlobalTablePagination()
const tableConfig = getPurchaseOrdersTableConfig({
    mode: 'tracking',
    viewPermission: 'inventory.purchase-orders.purchasing.view',
})
const selectedOrder = ref(null)
const toolbarConfig = computed(() => getPurchaseOrdersToolbarConfig({
    filters: orders.localFilters.value,
    total: Number(orders.pagination.value?.total ?? 0),
    mode: 'tracking',
}))

function openCapture(order) {
    if (!order?.id) return

    router.get(route('inventory.branches.reports.purchase-orders.capture', {
        branch: props.currentBranch.id,
        generalPurchaseOrder: order.id,
    }))
}

async function openOrder(order) {
    if (!order?.id || orders.loadingOrder.value) return

    try {
        selectedOrder.value = await orders.fetchOrder(order.id)
    } catch {
        ErrorAlert({
            title: 'No se pudo abrir la orden general',
            message: 'Actualiza la página y vuelve a intentarlo.',
        })
    }
}

function handleTableAction({ action, row }) {
    if (action === 'view') openOrder(row)
    if (action === 'edit') openCapture(row)
}
</script>

<template>
    <Head title="Seguimiento de compras" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
                @back="orders.backToReportsCenter"
                @update:search="orders.localFilters.value.search = $event"
                @update:records-per-page="orders.localFilters.value.per_page = $event"
            />
        </template>

        <GlobalTable
            :items="orders.rows.value"
            :pagination="orders.pagination.value"
            :loading="false"
            v-bind="tableConfig"
            @action="handleTableAction"
            @page-change="handlePageChange"
        />

        <GeneralPurchaseOrderModal
            v-if="selectedOrder"
            :order="selectedOrder"
            @close="selectedOrder = null"
        />
    </PageLayout>
</template>
