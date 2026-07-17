<script setup>
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'

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

const routeName = 'inventory.branches.reports.purchase-orders.history'
const orders = usePurchaseOrders(props, routeName)
const { handlePageChange } = useGlobalTablePagination()
const selectedOrder = ref(null)
const tableConfig = getPurchaseOrdersTableConfig({ mode: 'history' })
const toolbarConfig = computed(() => getPurchaseOrdersToolbarConfig({
    filters: orders.localFilters.value,
    total: Number(orders.pagination.value?.total ?? 0),
    mode: 'history',
}))

async function openOrder(order) {
    if (!order?.id || orders.loadingOrder.value) return

    try {
        selectedOrder.value = await orders.fetchOrder(order.id)
    } catch {
        ErrorAlert({
            title: 'No se pudo abrir la compra',
            message: 'Actualiza la pagina y vuelve a intentarlo.',
        })
    }
}

function handleTableAction({ action, row }) {
    if (action === 'open') openOrder(row)
}
</script>

<template>
    <Head title="Compras completadas" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
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
            @row-click="openOrder"
            @page-change="handlePageChange"
        />

        <GeneralPurchaseOrderModal
            v-if="selectedOrder"
            :order="selectedOrder"
            @close="selectedOrder = null"
        />
    </PageLayout>
</template>
