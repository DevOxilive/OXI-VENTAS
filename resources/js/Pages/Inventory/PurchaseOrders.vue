<script setup>
import { computed, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import FormPanel from '@/Components/Cards/FormPanel.vue'
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue'
import GeneralPurchaseOrderModal from '@/Components/Inventory/PurchaseReports/GeneralPurchaseOrderModal.vue'
import { ErrorAlert } from '@/Components/Modales/UniversalActionModal'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales/useModalConfig'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { usePurchaseOrders } from '@/Composables/Inventory/usePurchaseOrders'
import { usePermissions } from '@/Composables/usePermissions'
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
    purchaseCycle: {
        type: Object,
        default: () => ({}),
    },
})

const orders = usePurchaseOrders(props)
const { can } = usePermissions()
const { handlePageChange } = useGlobalTablePagination()
const selectedOrder = ref(null)
const selectedBranchIds = ref(
    (props.purchaseCycle?.branches ?? [])
        .filter((branch) => branch.submitted)
        .map((branch) => Number(branch.id)),
)
const activeStatus = computed(() => orders.localFilters.value.status || 'PURCHASING')
const tableMode = computed(() => activeStatus.value === 'COMPLETED' ? 'history' : 'tracking')
const tableConfig = computed(() => getPurchaseOrdersTableConfig({ mode: tableMode.value }))
const progress = computed(() => {
    const total = Number(props.purchaseCycle?.total_branches || 0)
    const submitted = Number(props.purchaseCycle?.submitted_count || 0)
    return total > 0 ? Math.round((submitted / total) * 100) : 0
})
const selectedBranches = computed(() => {
    return (props.purchaseCycle?.branches ?? []).filter((branch) => isSelectedBranch(branch.id))
})
const canGenerateOrder = computed(() => {
    return activeStatus.value === 'PURCHASING'
        && selectedBranches.value.length > 0
        && can('inventory.purchase-orders.create')
})

const toolbarConfig = computed(() => getPurchaseOrdersToolbarConfig({
    filters: orders.localFilters.value,
    total: Number(orders.pagination.value?.total ?? 0),
    cycle: props.purchaseCycle,
    mode: 'view',
    canConsolidate: can('inventory.purchase-orders.create'),
    canGenerate: canGenerateOrder.value,
}))

async function openOrder(order) {
    if (!order?.id || orders.loadingOrder.value) return

    if (activeStatus.value === 'PURCHASING' && can('inventory.purchase-orders.update')) {
        router.get(route('inventory.branches.reports.purchase-orders.capture', {
            branch: props.currentBranch.id,
            generalPurchaseOrder: order.id,
        }))
        return
    }

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
    if (action === 'open') openOrder(row)
}

function updateStatus(status) {
    if (!status || status === orders.localFilters.value.status) return

    orders.localFilters.value.status = status
}

function isSelectedBranch(branchId) {
    return selectedBranchIds.value.some((selectedId) => Number(selectedId) === Number(branchId))
}

function canSelectBranch(branch) {
    return Boolean(branch?.submitted)
}

function toggleBranch(branch) {
    if (!canSelectBranch(branch)) return

    if (isSelectedBranch(branch.id)) {
        selectedBranchIds.value = selectedBranchIds.value.filter((branchId) => {
            return Number(branchId) !== Number(branch.id)
        })
        return
    }

    selectedBranchIds.value = [...selectedBranchIds.value, Number(branch.id)]
}

function branchDescription(branch) {
    if (!branch.submitted) return 'Pendiente, no entra en esta orden'
    if (branch.without_items) return 'Cerrada sin productos'
    return branch.order_folio || 'Lista enviada'
}

async function consolidateCycle() {
    if (!canGenerateOrder.value) return

    const confirmMessage = `Se consolidaran ${selectedBranches.value.length} sucursales seleccionadas del ciclo ${props.purchaseCycle.folio}. Las sucursales pendientes no bloquearan esta orden.`

    const result = await confirmModalAction({
        mode: 'create',
        entityName: 'orden general',
        title: 'Generar orden general',
        confirmText: 'Generar orden general',
        message: confirmMessage,
    })

    if (!result.isConfirmed) return

    router.post(
        route('inventory.branches.reports.purchase-orders.consolidate', {
            branch: props.currentBranch.id,
        }),
        {
            branch_ids: selectedBranchIds.value,
        },
        getModalRequestOptions({
            mode: 'create',
            entityName: 'Orden general',
            successTitle: 'Orden general generada correctamente',
            errorTitle: 'No se pudo generar la orden general',
            errorMessage: 'Verifica que las sucursales seleccionadas hayan enviado o cerrado su solicitud.',
        }),
    )
}

function handleToolbarAction(action) {
    if (action === 'consolidate') consolidateCycle()
}
</script>

<template>
    <Head title="Órdenes generales de compra" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
                @back="orders.backToReportsCenter"
                @update:search="orders.localFilters.value.search = $event"
                @update:filter="orders.updateFilter"
                @update:records-per-page="orders.localFilters.value.per_page = $event"
                @update:active-tab="updateStatus"
                @action="handleToolbarAction"
            />
        </template>

        <FormPanel
            v-if="activeStatus === 'PURCHASING'"
            :title="`Ciclo ${purchaseCycle.folio || 'actual'}`"
            description="Selecciona las sucursales que entraran en esta orden general. Las pendientes no bloquean la compra."
            panel-class="mb-5 bg-background shadow-sm"
        >
            <div class="mb-4 h-2 overflow-hidden rounded-full bg-secondary">
                <div
                    class="h-full rounded-full bg-primary transition-all duration-500"
                    :style="{ width: `${progress}%` }"
                />
            </div>

            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                <SelectionCheckboxCard
                    v-for="branch in purchaseCycle.branches"
                    :key="branch.id"
                    :checked="isSelectedBranch(branch.id)"
                    :disabled="!canSelectBranch(branch)"
                    :title="branch.name"
                    :description="branchDescription(branch)"
                    :badge="branch.submitted ? 'Lista' : 'Pendiente'"
                    compact
                    variant="solid"
                    @toggle="toggleBranch(branch)"
                />
            </div>
        </FormPanel>

        <div v-if="orders.loadingOrder.value" class="mb-3 rounded-xl border border-secondary bg-secondary px-4 py-3 text-sm text-text">
            Cargando detalle de la orden...
        </div>

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
