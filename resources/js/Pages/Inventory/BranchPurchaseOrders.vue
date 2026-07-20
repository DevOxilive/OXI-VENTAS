<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import BranchPurchaseOrderModal from '@/Components/Inventory/PurchaseReports/BranchPurchaseOrderModal.vue'
import { ErrorAlert } from '@/Components/Modales/UniversalActionModal'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { getBranchPurchaseOrdersTableConfig } from '@/config/TableConfigs/branchPurchaseOrdersTableConfig'
import { getBranchPurchaseOrdersToolbarConfig } from '@/config/ToolbarConfigs/branchPurchaseOrdersToolbarConfig'

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

const { handlePageChange } = useGlobalTablePagination()
const selectedOrder = ref(null)
const loadingOrder = ref(false)
const filterTimer = ref(null)
const localFilters = ref({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? 'DRAFT',
    per_page: Number(props.filters?.per_page ?? 25),
})

const rows = computed(() => props.ordersDB?.data ?? [])
const pagination = computed(() => props.ordersDB ?? {})
const tableConfig = computed(() => getBranchPurchaseOrdersTableConfig({
    status: localFilters.value.status,
}))
const toolbarConfig = computed(() => getBranchPurchaseOrdersToolbarConfig({
    filters: localFilters.value,
    total: Number(pagination.value?.total ?? 0),
    branchName: props.currentBranch?.name,
}))

watch(localFilters, () => {
    clearTimeout(filterTimer.value)
    filterTimer.value = setTimeout(() => applyFilters({ page: 1 }), 300)
}, { deep: true })

onBeforeUnmount(() => clearTimeout(filterTimer.value))

function applyFilters(overrides = {}) {
    router.get(
        route('inventory.branches.reports.purchases', {
            branch: props.currentBranch.id,
        }),
        {
            search: localFilters.value.search || undefined,
            status: localFilters.value.status || 'DRAFT',
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

function updateStatus(status) {
    if (!status || status === localFilters.value.status) return

    localFilters.value.status = status
}

async function openOrder(order) {
    if (!order?.id || loadingOrder.value) return

    loadingOrder.value = true

    try {
        const { data } = await window.axios.get(
            route('inventory.branches.reports.purchases.show', {
                branch: props.currentBranch.id,
                purchaseReport: order.id,
            }),
        )

        selectedOrder.value = data
    } catch {
        ErrorAlert({
            title: 'No se pudo abrir la orden',
            message: 'Actualiza la pagina y vuelve a intentarlo.',
        })
    } finally {
        loadingOrder.value = false
    }
}

function handleTableAction({ action, row }) {
    if (action === 'open') openOrder(row)
}
</script>

<template>
    <Head title="Ordenes por sucursal" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
                @back="router.get(route('inventory.branches.reports', { branch: currentBranch.id }))"
                @update:search="localFilters.search = $event"
                @update:records-per-page="localFilters.per_page = $event"
                @update:active-tab="updateStatus"
            />
        </template>

        <div v-if="loadingOrder" class="mb-3 rounded-xl border border-secondary bg-secondary px-4 py-3 text-sm text-text">
            Cargando detalle de la orden...
        </div>

        <GlobalTable
            :items="rows"
            :pagination="pagination"
            :loading="false"
            v-bind="tableConfig"
            @action="handleTableAction"
            @row-click="openOrder"
            @page-change="handlePageChange"
        />

        <BranchPurchaseOrderModal
            v-if="selectedOrder"
            :order="selectedOrder"
            @close="selectedOrder = null"
        />
    </PageLayout>
</template>
