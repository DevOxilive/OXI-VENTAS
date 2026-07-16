<script setup>
import { computed, ref } from 'vue'
import { Head } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import PurchaseReportDraftModal from '@/Components/Inventory/PurchaseReports/PurchaseReportDraftModal.vue'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { usePurchaseOrders } from '@/Composables/Inventory/usePurchaseOrders'
import { getPurchaseOrdersTableConfig } from '@/config/TableConfigs/purchaseOrdersTableConfig'
import { getPurchaseOrdersToolbarConfig } from '@/config/ToolbarConfigs/purchaseOrdersToolbarConfig'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    currentBranch: {
        type: Object,
        required: true,
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

const orders = usePurchaseOrders(props)
const { handlePageChange } = useGlobalTablePagination()
const selectedOrder = ref(null)
const tableConfig = getPurchaseOrdersTableConfig()

const toolbarConfig = computed(() => getPurchaseOrdersToolbarConfig({
    filters: orders.localFilters.value,
    total: Number(orders.pagination.value?.total ?? 0),
}))

function openOrder(order) {
    selectedOrder.value = order
}

function handleTableAction({ action, row }) {
    if (action === 'open') openOrder(row)
}

function handleToolbarAction(action) {
    if (action === 'new-list') orders.createPurchaseList()
}
</script>

<template>
    <Head title="Órdenes de compra" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
                @back="orders.backToReportsCenter"
                @update:search="orders.localFilters.value.search = $event"
                @update:filter="orders.updateFilter"
                @update:records-per-page="orders.localFilters.value.per_page = $event"
                @action="handleToolbarAction"
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

        <PurchaseReportDraftModal
            v-if="selectedOrder"
            :report="selectedOrder"
            :branch-name="currentBranch.name"
            @close="selectedOrder = null"
        />
    </PageLayout>
</template>
