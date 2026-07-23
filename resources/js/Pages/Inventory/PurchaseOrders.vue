<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import FormPanel from '@/Components/Cards/FormPanel.vue'
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import GeneralPurchaseOrderModal from '@/Components/Inventory/PurchaseReports/GeneralPurchaseOrderModal.vue'
import BranchPurchaseOrderModal from '@/Components/Inventory/PurchaseReports/BranchPurchaseOrderModal.vue'
import PurchaseOrderTransferModal from '@/Components/Inventory/PurchaseReports/PurchaseOrderTransferModal.vue'
import PendingPurchaseOrderEditModal from '@/Components/Inventory/PurchaseReports/PendingPurchaseOrderEditModal.vue'
import { ErrorAlert } from '@/Components/Modales/UniversalActionModal'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales/useModalConfig'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { usePurchaseOrders } from '@/Composables/Inventory/usePurchaseOrders'
import { usePermissions } from '@/Composables/usePermissions'
import { getPurchaseOrdersTableConfig } from '@/config/TableConfigs/purchaseOrdersTableConfig'
import { getGeneralPurchaseSourceOrdersTableConfig } from '@/config/TableConfigs/generalPurchaseSourceOrdersTableConfig'
import { getPurchaseOrdersToolbarConfig } from '@/config/ToolbarConfigs/purchaseOrdersToolbarConfig'
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribePrivateRealtime } from '@/realtime'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    currentBranch: { type: Object, required: true },
    ordersDB: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    purchaseCycle: { type: Object, default: () => ({}) },
    generation: {
        type: Object,
        default: () => ({ branches: [], orders: [], inventory_users: [], draft: null }),
    },
})

const page = usePage()
const orders = usePurchaseOrders(props)
const { can } = usePermissions()
const { handlePageChange } = useGlobalTablePagination()
const selectedOrder = ref(null)
const selectedSourceOrder = ref(null)
const transferOrder = ref(null)
const editingSourceOrder = ref(null)
const loadingSourceOrder = ref(false)
const selectedOrderIds = ref((props.generation?.draft?.order_ids ?? []).map(Number))
const initialSelectedOrder = (props.generation?.orders ?? []).find((order) => selectedOrderIds.value.includes(Number(order.id)))
const initialBranch = initialSelectedOrder?.branch_id
    ?? (props.generation?.branches ?? []).find((branch) => Number(branch.orders_count) > 0)?.id
    ?? props.generation?.branches?.[0]?.id
    ?? null
const selectedBranchId = ref(initialBranch ? Number(initialBranch) : null)
let unsubscribeOrderActivity = null

const activeStatus = computed(() => orders.localFilters.value.status || 'GENERATE')
const tableMode = computed(() => activeStatus.value === 'COMPLETED' ? 'history' : 'purchasing')
const activeViewPermission = computed(() => activeStatus.value === 'COMPLETED'
    ? 'inventory.purchase-orders.completed.view'
    : 'inventory.purchase-orders.purchasing.view')
const tableConfig = computed(() => getPurchaseOrdersTableConfig({
    mode: tableMode.value,
    viewPermission: activeViewPermission.value,
}))
const sourceTableConfig = getGeneralPurchaseSourceOrdersTableConfig()
const sourceRows = computed(() => (props.generation?.orders ?? [])
    .filter((order) => Number(order.branch_id) === Number(selectedBranchId.value))
    .map((order) => ({
        ...order,
        selected: selectedOrderIds.value.includes(Number(order.id)),
    })))
const selectedSourceOrders = computed(() => (props.generation?.orders ?? [])
    .filter((order) => selectedOrderIds.value.includes(Number(order.id))))
const selectedBranchesCount = computed(() => new Set(
    selectedSourceOrders.value.map((order) => Number(order.branch_id)),
).size)
const canPrepareGeneralOrder = computed(() => can('inventory.purchase-orders.generate.create')
    && selectedSourceOrders.value.length > 0)
const availableTabs = computed(() => [
    can('inventory.purchase-orders.generate.view') && { key: 'GENERATE', label: 'Generar', icon: 'playlist_add' },
    can('inventory.purchase-orders.purchasing.view') && { key: 'PURCHASING', label: 'En compra', icon: 'local_shipping' },
    can('inventory.purchase-orders.completed.view') && { key: 'COMPLETED', label: 'Completadas', icon: 'task_alt' },
].filter(Boolean))
const toolbarConfig = computed(() => getPurchaseOrdersToolbarConfig({
    filters: orders.localFilters.value,
    total: activeStatus.value === 'GENERATE'
        ? Number(props.generation?.orders?.length ?? 0)
        : Number(orders.pagination.value?.total ?? 0),
    mode: 'view',
    tabs: availableTabs.value,
}))

watch(() => props.generation?.orders, (availableOrders = []) => {
    const availableIds = new Set(availableOrders.map((order) => Number(order.id)))
    selectedOrderIds.value = selectedOrderIds.value.filter((orderId) => availableIds.has(Number(orderId)))

    if (!availableOrders.some((order) => Number(order.branch_id) === Number(selectedBranchId.value))) {
        selectedBranchId.value = Number(
            props.generation?.branches?.find((branch) => Number(branch.orders_count) > 0)?.id
            ?? props.generation?.branches?.[0]?.id
            ?? 0,
        ) || null
    }
})

onMounted(() => {
    if (!page.props.auth?.user?.id) return

    unsubscribeOrderActivity = subscribePrivateRealtime(
        REALTIME_CHANNELS.user(page.props.auth.user.id),
        REALTIME_EVENTS.activityLogged,
        (event) => {
            if (!['assigned', 'purchase_order_transferred'].includes(event?.action)) return

            router.reload({ only: ['generation'], preserveScroll: true })
        },
    )
})

onBeforeUnmount(() => unsubscribeOrderActivity?.())

async function openGeneralOrder(order) {
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

async function openSourceOrder(order, edit = false) {
    if (!order?.id || loadingSourceOrder.value) return
    loadingSourceOrder.value = true

    try {
        const { data } = await window.axios.get(
            route('inventory.branches.reports.purchase-orders.source-orders.show', {
                branch: props.currentBranch.id,
                purchaseOrder: order.id,
            }),
        )
        if (edit) {
            editingSourceOrder.value = data
            return
        }

        selectedSourceOrder.value = data
    } catch {
        ErrorAlert({
            title: 'No se pudo abrir la orden de compra',
            message: 'La orden pudo haber sido transferida o agregada a otra Orden de compra general.',
        })
    } finally {
        loadingSourceOrder.value = false
    }
}

function updateStatus(status) {
    if (!status || status === orders.localFilters.value.status) return
    orders.localFilters.value.status = status
}

function selectBranch(branch) {
    selectedBranchId.value = Number(branch.id)
}

function addSourceOrder(order) {
    const orderId = Number(order.id)
    if (!selectedOrderIds.value.includes(orderId)) {
        selectedOrderIds.value = [...selectedOrderIds.value, orderId]
    }
}

function removeSourceOrder(order) {
    selectedOrderIds.value = selectedOrderIds.value.filter((orderId) => Number(orderId) !== Number(order.id))
}

function handleSourceTableAction({ action, row }) {
    if (action === 'view') openSourceOrder(row)
    if (action === 'transfer') transferOrder.value = row
    if (action === 'edit') openSourceOrder(row, true)
    if (action === 'add') addSourceOrder(row)
    if (action === 'remove') removeSourceOrder(row)
}

function handleGeneralTableAction({ action, row }) {
    if (action === 'view') openGeneralOrder(row)

    if (action === 'edit') {
        router.get(route('inventory.branches.reports.purchase-orders.capture', {
            branch: props.currentBranch.id,
            generalPurchaseOrder: row.id,
        }))
    }
}

function saveDraft() {
    if (!canPrepareGeneralOrder.value) return

    router.post(
        route('inventory.branches.reports.purchase-orders.draft', {
            branch: props.currentBranch.id,
        }),
        {
            order_ids: selectedOrderIds.value,
            draft_id: props.generation?.draft?.id ?? null,
        },
        getModalRequestOptions({
            mode: 'create',
            entityName: 'Borrador de Orden de compra general',
            successTitle: 'Borrador de Orden de compra general guardado',
            errorTitle: 'No se pudo guardar el borrador',
            errorMessage: 'Verifica que las órdenes continúen asignadas y disponibles.',
        }),
    )
}

async function generateGeneralOrder() {
    if (!canPrepareGeneralOrder.value) return

    const confirmation = await confirmModalAction({
        mode: 'create',
        entityName: 'orden general',
        title: 'Generar Orden de compra general',
        confirmText: 'Generar Orden general',
        message: `Se incluirán ${selectedSourceOrders.value.length} Órdenes de compra de ${selectedBranchesCount.value} sucursal(es).`,
    })

    if (!confirmation.isConfirmed) return

    router.post(
        route('inventory.branches.reports.purchase-orders.consolidate', {
            branch: props.currentBranch.id,
        }),
        {
            order_ids: selectedOrderIds.value,
            draft_id: props.generation?.draft?.id ?? null,
        },
        getModalRequestOptions({
            mode: 'create',
            entityName: 'Orden general',
            successTitle: 'Orden de compra general generada correctamente',
            errorTitle: 'No se pudo generar la Orden de compra general',
            errorMessage: 'Verifica que todas las órdenes sigan pendientes y asignadas.',
            onSuccess: () => updateStatus('PURCHASING'),
        }),
    )
}
</script>

<template>
    <Head title="Órdenes de compra generales" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
                @back="orders.backToReportsCenter"
                @update:search="orders.localFilters.value.search = $event"
                @update:filter="orders.updateFilter"
                @update:records-per-page="orders.localFilters.value.per_page = $event"
                @update:active-tab="updateStatus"
            />
        </template>

        <div
            v-if="activeStatus === 'GENERATE'"
            class="grid min-w-0 gap-4 xl:grid-cols-[250px_minmax(0,1fr)_310px]"
        >
            <FormPanel
                title="1. Sucursal"
                description="Elige una sucursal para consultar todas sus Órdenes de compra asignadas."
                panel-class="min-w-0 shadow-none"
            >
                <div class="space-y-2">
                    <SelectionCheckboxCard
                        v-for="branch in generation.branches"
                        :key="branch.id"
                        :checked="Number(selectedBranchId) === Number(branch.id)"
                        :title="branch.name"
                        :description="`${branch.orders_count} Orden(es) de compra pendiente(s)`"
                        :badge="String(branch.orders_count)"
                        compact
                        @toggle="selectBranch(branch)"
                    />

                    <p v-if="!generation.branches?.length" class="py-6 text-center text-sm text-text opacity-60">
                        No tienes sucursales asignadas.
                    </p>
                </div>
            </FormPanel>

            <FormPanel
                title="2. Órdenes de compra"
                description="Revisa, transfiere o agrega cada Orden de compra a la Orden de compra general."
                panel-class="min-w-0 shadow-none"
            >
                <div v-if="loadingSourceOrder" class="mb-3 rounded-xl bg-secondary px-4 py-3 text-sm text-text">
                    Cargando detalle de la orden...
                </div>

                <GlobalTable
                    :items="sourceRows"
                    :pagination="{}"
                    :loading="false"
                    v-bind="sourceTableConfig"
                    @action="handleSourceTableAction"
                />
            </FormPanel>

            <FormPanel
                title="3. Resumen de Órdenes de compra generales"
                :description="`Ciclo ${purchaseCycle.folio || 'actual'}`"
                panel-class="min-w-0 shadow-none"
            >
                <div class="mb-4 grid grid-cols-2 gap-2">
                    <div class="rounded-xl bg-secondary p-3 text-center text-text">
                        <strong class="block text-xl">{{ selectedSourceOrders.length }}</strong>
                        <span class="text-xs opacity-65">Órdenes</span>
                    </div>
                    <div class="rounded-xl bg-secondary p-3 text-center text-text">
                        <strong class="block text-xl">{{ selectedBranchesCount }}</strong>
                        <span class="text-xs opacity-65">Sucursales</span>
                    </div>
                </div>

                <div class="max-h-[430px] space-y-2 overflow-y-auto pr-1">
                    <article
                        v-for="order in selectedSourceOrders"
                        :key="order.id"
                        class="flex items-center justify-between gap-2 rounded-xl border border-secondary bg-background p-3 text-text"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-black">{{ order.folio }}</p>
                            <p class="truncate text-xs opacity-60">{{ order.branch_name }}</p>
                        </div>
                        <AppButton
                            v-if="can('inventory.purchase-orders.generate.create')"
                            variant="secondary"
                            class="!px-2 !py-2"
                            title="Quitar de la Orden de compra general"
                            @click="removeSourceOrder(order)"
                        >
                            <span class="material-symbols-outlined text-base">close</span>
                        </AppButton>
                    </article>

                    <p v-if="!selectedSourceOrders.length" class="py-8 text-center text-sm text-text opacity-60">
                        Agrega las Órdenes de compra que formarán parte de la Orden de compra general.
                    </p>
                </div>

                <div
                    v-if="can('inventory.purchase-orders.generate.create')"
                    class="mt-4 grid gap-2"
                >
                    <AppButton
                        variant="secondary"
                        block
                        :disabled="!canPrepareGeneralOrder"
                        @click="saveDraft"
                    >
                        Guardar borrador
                    </AppButton>
                    <AppButton
                        variant="primary"
                        block
                        :disabled="!canPrepareGeneralOrder"
                        @click="generateGeneralOrder"
                    >
                        Generar Orden de compra general
                    </AppButton>
                </div>
            </FormPanel>
        </div>

        <template v-else>
            <div v-if="orders.loadingOrder.value" class="mb-3 rounded-xl border border-secondary bg-secondary px-4 py-3 text-sm text-text">
                Cargando detalle de la orden...
            </div>

            <GlobalTable
                :items="orders.rows.value"
                :pagination="orders.pagination.value"
                :loading="false"
                v-bind="tableConfig"
                @action="handleGeneralTableAction"
                @page-change="handlePageChange"
            />
        </template>

        <GeneralPurchaseOrderModal
            v-if="selectedOrder"
            :order="selectedOrder"
            @close="selectedOrder = null"
        />

        <BranchPurchaseOrderModal
            v-if="selectedSourceOrder"
            :order="selectedSourceOrder"
            :branch-id="selectedSourceOrder.branch.id"
            mode="view"
            @close="selectedSourceOrder = null"
        />

        <PurchaseOrderTransferModal
            v-if="transferOrder"
            :order="transferOrder"
            :inventory-users="generation.inventory_users"
            :context-branch-id="currentBranch.id"
            @close="transferOrder = null"
            @transferred="transferOrder = null"
        />

        <PendingPurchaseOrderEditModal
            v-if="editingSourceOrder"
            :order="editingSourceOrder"
            :branch-id="currentBranch.id"
            :update-url="route('inventory.branches.reports.purchase-orders.source-orders.update', { branch: currentBranch.id, purchaseOrder: editingSourceOrder.id })"
            @close="editingSourceOrder = null"
            @updated="editingSourceOrder = null"
        />
    </PageLayout>
</template>
