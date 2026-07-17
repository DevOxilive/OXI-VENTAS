<script setup>
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
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
const tableConfig = getPurchaseOrdersTableConfig({ mode: 'tracking' })
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

function handleTableAction({ action, row }) {
    if (action === 'open') openCapture(row)
}
</script>

<template>
    <Head title="Seguimiento de compras" />

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
            @row-click="openCapture"
            @page-change="handlePageChange"
        />
    </PageLayout>
</template>
