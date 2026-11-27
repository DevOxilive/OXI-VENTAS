<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import GlobalSelect from '@/Components/Forms/GlobalSelect.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import { GlobalModal } from '@/Components/Modales'
import GeneralPurchaseOrderModal from '@/Components/Inventory/PurchaseReports/GeneralPurchaseOrderModal.vue'
import BranchPurchaseOrderModal from '@/Components/Inventory/PurchaseReports/BranchPurchaseOrderModal.vue'
import PendingPurchaseOrderEditModal from '@/Components/Inventory/PurchaseReports/PendingPurchaseOrderEditModal.vue'
import { ErrorAlert } from '@/Components/Modales/UniversalActionModal'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales/useModalConfig'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { usePurchaseOrders } from '@/Composables/Inventory/usePurchaseOrders'
import { usePermissions } from '@/Composables/usePermissions'
import { getPurchaseOrdersTableConfig } from '@/config/TableConfigs/purchaseOrdersTableConfig'
import { getGeneralPurchaseSourceOrdersTableConfig } from '@/config/TableConfigs/generalPurchaseSourceOrdersTableConfig'
import { getPurchaseOrdersToolbarConfig } from '@/config/ToolbarConfigs/purchaseOrdersToolbarConfig'
import { REALTIME_CHANNELS, REALTIME_EVENTS, refreshRealtimeProps, subscribePrivateRealtime, subscribeRealtime } from '@/realtime'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    currentBranch: { type: Object, required: true },
    ordersDB: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    generation: {
        type: Object,
        default: () => ({ branches: [], orders: [], inventory_users: [], draft: null }),
    },
})

const page = usePage()
const orders = usePurchaseOrders(props)
const currentGeneration = ref(props.generation)
const { can } = usePermissions()
const { handlePageChange } = useGlobalTablePagination()
const selectedOrder = ref(null)
const selectedSourceOrder = ref(null)
const editingSourceOrder = ref(null)
const loadingSourceOrder = ref(false)
const showInitialResponsibleModal = ref(false)
const initialResponsibleChoice = ref('')
const pendingApprovalOrder = ref(null)
const selectedOrderIds = computed(() => currentGeneration.value?.preparation?.order_ids ?? [])
const responsible = computed(() => currentGeneration.value?.preparation ?? {})
const isResponsible = computed(() => Number(responsible.value.responsible_id) === Number(page.props.auth.user.id))
const responsibleChoice = ref('')
const eligibleInitialResponsibleUsers = computed(() => (currentGeneration.value?.inventory_users ?? [])
    .filter((user) => (user.branch_ids ?? []).some((branchId) => Number(branchId) === Number(pendingApprovalOrder.value?.branch_id))))
const canAssignResponsible = computed(() => can('inventory.purchase-orders.general.create')
    && (!responsible.value.responsible_id || isResponsible.value))
const openReview = ref(null)
let reviewTimer = null
let refreshTimer = null

function preparationAction(action, order = null, responsibleId = null) {
    router.put(route('inventory.branches.reports.purchase-orders.preparation', { branch: props.currentBranch.id }),
        { action, order_id: order?.id, responsible_id: responsibleId }, { preserveScroll: true })
}

async function assignResponsible() {
    const responsibleId = Number(responsibleChoice.value)
    if (!responsibleId || responsibleId === Number(responsible.value.responsible_id)) return
    const selectedUser = (currentGeneration.value?.inventory_users ?? []).find((user) => Number(user.id) === responsibleId)
    const transfer = Boolean(responsible.value.responsible_id)
    const result = await confirmModalAction({
        mode: 'update',
        entityName: 'responsable de compra',
        title: transfer ? 'Pasar el mando' : 'Elegir responsable',
        confirmText: transfer ? 'Pasar el mando' : 'Asignar responsable',
        message: transfer
            ? `${selectedUser?.name ?? 'La persona elegida'} quedará a cargo de crear la Compra central.`
            : `${selectedUser?.name ?? 'La persona elegida'} quedará a cargo de esta Compra central.`,
    })
    if (!result.isConfirmed) return
    preparationAction('assign', null, responsibleId)
    responsibleChoice.value = ''
}

async function assignInitialResponsibleAndApprove() {
    const responsibleId = Number(initialResponsibleChoice.value)
    const order = pendingApprovalOrder.value
    if (!responsibleId || !order) return

    try {
        await window.axios.put(
            route('inventory.branches.reports.purchase-orders.preparation', { branch: props.currentBranch.id }),
            { action: 'assign', responsible_id: responsibleId },
        )
        showInitialResponsibleModal.value = false
        initialResponsibleChoice.value = ''
        pendingApprovalOrder.value = null
        await reviewSourceOrder(order, 'APPROVED', true)
    } catch (error) {
        ErrorAlert({
            title: 'No se pudo elegir responsable',
            message: error?.response?.data?.message || 'Actualiza la página e inténtalo de nuevo.',
        })
    }
}

function closeInitialResponsibleModal() {
    showInitialResponsibleModal.value = false
    initialResponsibleChoice.value = ''
    pendingApprovalOrder.value = null
}

async function reviewPresence(open) {
    if (!openReview.value) return
    const order = openReview.value
    await window.axios.put(route('inventory.branches.reports.purchase-orders.presence', { branch: order.branch_id, purchaseOrder: order.id }), { open }).catch(() => {})
}

watch([selectedSourceOrder, editingSourceOrder], ([viewing, editing]) => {
    if (!viewing && !editing && openReview.value) {
        reviewPresence(false)
        openReview.value = null
    }
})
const sourcePage = ref(1)
const sourcePerPage = 10
let unsubscribeOrderActivity = null
let unsubscribePurchaseWorkflow = null

const activeStatus = computed(() => orders.localFilters.value.status || 'GENERATE')
const tableMode = computed(() => 'central')
const activeViewPermission = computed(() => 'inventory.purchase-orders.general.view')
const tableConfig = computed(() => getPurchaseOrdersTableConfig({
    mode: tableMode.value,
    viewPermission: activeViewPermission.value,
}))
const sourceTableConfig = getGeneralPurchaseSourceOrdersTableConfig()
const allSourceRows = computed(() => (currentGeneration.value?.orders ?? [])
    .filter((order) => orders.localFilters.value.source_status === 'ALL'
        || (order.review_status || 'PENDING') === orders.localFilters.value.source_status)
    .filter((order) => {
        const search = String(orders.localFilters.value.search || '').trim().toLocaleLowerCase()
        if (!search) return true
        return [order.folio, order.branch_name, order.requested_by_name]
            .some((value) => String(value || '').toLocaleLowerCase().includes(search))
    })
    .map((order) => ({
        ...order,
        selected: selectedOrderIds.value.includes(Number(order.id)),
    })))
const sourceRows = computed(() => {
    const start = (sourcePage.value - 1) * sourcePerPage
    return allSourceRows.value.slice(start, start + sourcePerPage)
})
const sourcePagination = computed(() => {
    const total = allSourceRows.value.length
    const lastPage = Math.max(1, Math.ceil(total / sourcePerPage))
    const currentPage = Math.min(sourcePage.value, lastPage)
    const pageUrl = (pageNumber) => `?source_page=${pageNumber}`

    return {
        current_page: currentPage,
        last_page: lastPage,
        per_page: sourcePerPage,
        total,
        prev_page_url: currentPage > 1 ? pageUrl(currentPage - 1) : null,
        next_page_url: currentPage < lastPage ? pageUrl(currentPage + 1) : null,
        links: Array.from({ length: lastPage }, (_, index) => ({
            url: pageUrl(index + 1),
            label: String(index + 1),
            active: index + 1 === currentPage,
        })),
    }
})
const selectedSourceOrders = computed(() => (currentGeneration.value?.orders ?? [])
    .filter((order) => selectedOrderIds.value.includes(Number(order.id))))
const selectedBranchesCount = computed(() => new Set(
    selectedSourceOrders.value.map((order) => Number(order.branch_id)),
).size)
const canPrepareGeneralOrder = computed(() => can('inventory.purchase-orders.general.create')
    && isResponsible.value
    && selectedSourceOrders.value.length > 0)
const availableTabs = computed(() => [
    can('inventory.purchase-orders.source.view') && { key: 'GENERATE', label: 'Solicitudes de compra', icon: 'playlist_add' },
    can('inventory.purchase-orders.general.view') && { key: 'CENTRAL', label: 'Compra Central', icon: 'local_shipping' },
].filter(Boolean))
const toolbarConfig = computed(() => getPurchaseOrdersToolbarConfig({
    filters: orders.localFilters.value,
    total: activeStatus.value === 'GENERATE'
        ? Number(allSourceRows.value.length)
        : Number(orders.pagination.value?.total ?? 0),
    tabs: availableTabs.value,
}))

watch(() => props.generation, (generation) => {
    currentGeneration.value = generation
})

watch(() => currentGeneration.value?.orders, () => {
    const lastPage = Math.max(1, Math.ceil(allSourceRows.value.length / sourcePerPage))
    sourcePage.value = Math.min(sourcePage.value, lastPage)
})

function refreshPurchaseWorkflow() {
    return refreshRealtimeProps(page, ['generation'], {
        onSuccess: (nextProps) => {
            if (nextProps?.generation) currentGeneration.value = nextProps.generation
        },
    })
}

function refreshVisibleWorkflow() {
    if (!document.hidden && activeStatus.value === 'GENERATE') refreshPurchaseWorkflow()
}

onMounted(() => {
    reviewTimer = window.setInterval(() => reviewPresence(true), 25000)
    refreshTimer = window.setInterval(refreshVisibleWorkflow, 3000)
    window.addEventListener('focus', refreshVisibleWorkflow)
    document.addEventListener('visibilitychange', refreshVisibleWorkflow)
    if (!page.props.auth?.user?.id) return
    unsubscribeOrderActivity = subscribePrivateRealtime(
        REALTIME_CHANNELS.user(page.props.auth.user.id),
        REALTIME_EVENTS.activityLogged,
        (event) => {
            if (!['purchase_order_assigned', 'purchase_order_transferred'].includes(event?.action)) return

            refreshPurchaseWorkflow()
        },
    )
    unsubscribePurchaseWorkflow = subscribeRealtime(
        REALTIME_CHANNELS.systems,
        REALTIME_EVENTS.purchaseWorkflowChanged,
        refreshPurchaseWorkflow,
    )
})

onBeforeUnmount(() => {
    unsubscribeOrderActivity?.()
    unsubscribePurchaseWorkflow?.()
    clearInterval(reviewTimer)
    clearInterval(refreshTimer)
    window.removeEventListener('focus', refreshVisibleWorkflow)
    document.removeEventListener('visibilitychange', refreshVisibleWorkflow)
    reviewPresence(false)
})
watch(() => [orders.localFilters.value.source_status, orders.localFilters.value.search], () => { sourcePage.value = 1 })

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
                branch: order.branch_id,
                purchaseOrder: order.id,
            }),
        )
        openReview.value = order
        await reviewPresence(true)
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

function handleSourceTableAction({ action, row }) {
    if (action === 'view') openSourceOrder(row)
    if (action === 'approve') reviewSourceOrder(row, 'APPROVED')
    if (action === 'reject') reviewSourceOrder(row, 'REJECTED')
    if (action === 'edit') openSourceOrder(row, true)
    if (action === 'reopen') reviewSourceOrder(row, 'PENDING')
    if (action === 'delete') deleteSourceOrder(row)
}

function handleSourcePageChange(url) {
    if (!url) return

    const parsed = new URL(url, window.location.origin)
    sourcePage.value = Math.max(1, Number(parsed.searchParams.get('source_page') || 1))
}

async function reviewSourceOrder(order, reviewStatus, responsibleJustAssigned = false) {
    const approved = reviewStatus === 'APPROVED'
    const reopened = reviewStatus === 'PENDING'

    if (approved && !responsible.value.responsible_id && !responsibleJustAssigned) {
        pendingApprovalOrder.value = order
        showInitialResponsibleModal.value = true
        return
    }
    const result = await confirmModalAction({
        mode: reopened || approved ? 'update' : 'delete',
        entityName: 'solicitud de compra',
        title: reopened ? `Regresar ${order.folio} a revisión` : (approved ? `Aceptar ${order.folio}` : `Rechazar ${order.folio}`),
        confirmText: reopened ? 'Regresar a pendientes' : (approved ? 'Aceptar solicitud' : 'Rechazar solicitud'),
        message: reopened
            ? 'La solicitud volverá a Pendientes para revisarla nuevamente.'
            : (approved
                ? 'La solicitud se agregará directamente a la Compra central.'
                : 'La solicitud no se incluirá en una Compra central.'),
    })

    if (!result.isConfirmed) return

    router.put(
        route('inventory.branches.reports.purchase-orders.source-orders.review', {
            branch: order.branch_id,
            purchaseOrder: order.id,
        }),
        { review_status: reviewStatus },
        getModalRequestOptions({
            mode: reopened || approved ? 'update' : 'delete',
            entityName: 'Orden de compra',
            successTitle: reopened ? 'Solicitud regresada a revisión' : (approved ? 'Orden aprobada correctamente' : 'Orden rechazada correctamente'),
            errorTitle: 'No se pudo revisar la orden',
            errorMessage: 'Actualiza la página y verifica que la orden siga disponible.',
        }),
    )
}

async function deleteSourceOrder(order) {
    const result = await confirmModalAction({
        mode: 'delete',
        entityName: 'solicitud de compra',
        title: `Eliminar ${order.folio}`,
        confirmText: 'Eliminar solicitud',
        message: 'La solicitud rechazada y sus productos se eliminarán definitivamente.',
    })
    if (!result.isConfirmed) return

    router.delete(
        route('inventory.branches.reports.purchase-orders.source-orders.destroy', {
            branch: order.branch_id,
            purchaseOrder: order.id,
        }),
        getModalRequestOptions({
            mode: 'delete',
            entityName: 'Solicitud de compra',
            successTitle: 'Solicitud eliminada correctamente',
            errorTitle: 'No se pudo eliminar la solicitud',
            errorMessage: 'Verifica que la solicitud continúe rechazada.',
        }),
    )
}

function handleGeneralTableAction({ action, row }) {
    if (action === 'view') openGeneralOrder(row)

    if (action === 'edit') {
        router.get(route('inventory.branches.reports.purchase-orders.capture', {
            branch: props.currentBranch.id,
            generalPurchaseOrder: row.id,
        }))
    }

    if (action === 'reopen') reopenGeneralOrder(row)
    if (action === 'discard') discardGeneralOrder(row)
}

async function reopenGeneralOrder(order) {
    const confirmation = await confirmModalAction({
        mode: 'update',
        entityName: 'Compra central',
        title: `Regresar ${order.folio} a edición`,
        confirmText: 'Regresar a edición',
        message: 'La compra central se desmontará y sus solicitudes volverán a la selección compartida. Sólo está disponible si todavía no se capturó mercancía.',
    })
    if (!confirmation.isConfirmed) return

    router.delete(
        route('inventory.branches.reports.purchase-orders.reopen', {
            branch: props.currentBranch.id,
            generalPurchaseOrder: order.id,
        }),
        getModalRequestOptions({
            mode: 'update',
            entityName: 'Compra central',
            successTitle: 'Compra central regresada a edición',
            errorTitle: 'No se pudo regresar la compra a edición',
            errorMessage: 'Sólo puede hacerse antes de capturar lotes, cantidades o una revisión física.',
        }),
    )
}

async function discardGeneralOrder(order) {
    const confirmation = await confirmModalAction({
        mode: 'delete',
        entityName: 'Compra central',
        title: `Descartar ${order.folio}`,
        confirmText: 'Descartar compra',
        message: 'Se guardará una fotografía de esta compra antes de descartarla. Las solicitudes regresarán a Pendientes y deberán revisarse nuevamente.',
    })
    if (!confirmation.isConfirmed) return

    router.delete(
        route('inventory.branches.reports.purchase-orders.discard', {
            branch: props.currentBranch.id,
            generalPurchaseOrder: order.id,
        }),
        getModalRequestOptions({
            mode: 'delete',
            entityName: 'Compra central',
            successTitle: 'Compra central descartada',
            errorTitle: 'No se pudo descartar la compra central',
            errorMessage: 'Sólo puede descartarse antes de capturar lotes, cantidades o una revisión física.',
        }),
    )
}

function disabledGeneralDraftSave() {
    if (!canPrepareGeneralOrder.value) return

    router.post(
        route('inventory.branches.reports.purchase-orders.consolidate', {
            branch: props.currentBranch.id,
        }),
        {
            order_ids: selectedOrderIds.value,
        },
        getModalRequestOptions({
            mode: 'create',
            entityName: 'Compra central',
            successTitle: 'Compra central creada correctamente',
            errorTitle: 'No se pudo crear la Compra central',
            errorMessage: 'Verifica que las solicitudes continúen disponibles.',
        }),
    )
}

async function generateGeneralOrder() {
    if (!canPrepareGeneralOrder.value) return

    const confirmation = await confirmModalAction({
        mode: 'create',
        entityName: 'Compra central',
        title: 'Crear Compra central',
        confirmText: 'Crear Compra central',
        message: `Se reunirán ${selectedSourceOrders.value.length} solicitudes de ${selectedBranchesCount.value} sucursal(es). Responsable de compra: ${page.props.auth.user.name}.`,
    })

    if (!confirmation.isConfirmed) return

    router.post(
        route('inventory.branches.reports.purchase-orders.consolidate', {
            branch: props.currentBranch.id,
        }),
        {
            order_ids: selectedOrderIds.value,
        },
        getModalRequestOptions({
            mode: 'create',
            entityName: 'Compra central',
            successTitle: 'Compra central creada correctamente',
            errorTitle: 'No se pudo crear la Compra central',
            errorMessage: 'Verifica que las solicitudes sigan pendientes.',
            onSuccess: () => updateStatus('CENTRAL'),
        }),
    )
}
</script>

<template>
    <Head title="Solicitudes de compra" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
                @back="orders.backToBranchInventory"
                @update:search="orders.localFilters.value.search = $event"
                @update:filter="orders.updateFilter"
                @update:records-per-page="orders.localFilters.value.per_page = $event"
                @update:active-tab="updateStatus"
            />
        </template>

        <div
            v-if="activeStatus === 'GENERATE'"
            class="grid min-w-0 gap-4"
        >
                <div class="flex flex-wrap items-center justify-end gap-3 text-sm">
                        <span class="text-text opacity-70">
                            Responsable: <strong class="font-semibold opacity-100">{{ responsible.responsible_name || 'Sin asignar' }}</strong>
                        </span>
                        <div v-if="canAssignResponsible && responsible.responsible_id" class="flex items-center gap-2">
                            <GlobalSelect
                                v-model="responsibleChoice"
                                class="w-52"
                                aria-label="Elegir responsable de la compra central"
                            >
                                <option value="">{{ responsible.responsible_id ? 'Pasar el mando a…' : 'Elegir responsable…' }}</option>
                                <option
                                    v-for="inventoryUser in currentGeneration.inventory_users"
                                    :key="inventoryUser.id"
                                    :value="String(inventoryUser.id)"
                                    :disabled="Number(inventoryUser.id) === Number(responsible.responsible_id)"
                                >
                                    {{ inventoryUser.name }}
                                </option>
                            </GlobalSelect>
                            <AppButton
                                variant="secondary"
                                :disabled="!responsibleChoice"
                                @click="assignResponsible"
                            >
                                {{ responsible.responsible_id ? 'Pasar mando' : 'Asignar' }}
                            </AppButton>
                        </div>
                        <AppButton
                            v-if="isResponsible"
                            :disabled="!canPrepareGeneralOrder"
                            @click="generateGeneralOrder"
                        >
                            Crear compra central · {{ selectedOrderIds.length }}
                        </AppButton>
                </div>
                <div v-if="loadingSourceOrder" class="mb-3 rounded-xl bg-secondary px-4 py-3 text-sm text-text">
                    Cargando detalle de la orden...
                </div>

                <GlobalTable
                    :items="sourceRows"
                    :pagination="sourcePagination"
                    :loading="false"
                    v-bind="sourceTableConfig"
                    @action="handleSourceTableAction"
                    @page-change="handleSourcePageChange"
                />

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

        <PendingPurchaseOrderEditModal
            v-if="editingSourceOrder"
            :order="editingSourceOrder"
            :branch-id="editingSourceOrder.branch.id"
            :update-url="route('inventory.branches.reports.purchase-orders.source-orders.update', { branch: editingSourceOrder.branch.id, purchaseOrder: editingSourceOrder.id })"
            @close="editingSourceOrder = null"
            @updated="editingSourceOrder = null"
        />

        <GlobalModal
            v-if="showInitialResponsibleModal"
            title="Elegir responsable de compra"
            subtitle="Esta solicitud se guardará como aprobada y se agregará a la Compra central de la persona elegida."
            mode="create"
            size="md"
            height="auto"
            :columns="1"
            :show-save="false"
            :close-on-backdrop="false"
            :close-on-esc="false"
            @close="closeInitialResponsibleModal"
        >
            <div class="space-y-4">
                <div class="rounded-2xl border border-secondary bg-secondary p-4 text-sm text-text">
                    <p class="font-black">Primera solicitud aprobada</p>
                    <p class="mt-1 opacity-70">
                        Antes de guardarla, elijan quién llevará la Compra central.
                    </p>
                </div>

                <GlobalSelect
                    v-model="initialResponsibleChoice"
                    class="w-full"
                    aria-label="Elegir responsable de la compra central"
                >
                    <option value="">Elegir responsable…</option>
                    <option
                        v-for="inventoryUser in eligibleInitialResponsibleUsers"
                        :key="inventoryUser.id"
                        :value="String(inventoryUser.id)"
                    >
                        {{ inventoryUser.name }}
                    </option>
                </GlobalSelect>
                <p v-if="!eligibleInitialResponsibleUsers.length" class="text-sm text-primary">
                    No hay una responsable de Inventario con acceso a esta sucursal.
                </p>
            </div>

            <template #footer>
                <footer class="flex w-full justify-end gap-2 border-t border-secondary bg-background p-3">
                    <AppButton variant="secondary" @click="closeInitialResponsibleModal">
                        Cancelar
                    </AppButton>
                    <AppButton :disabled="!initialResponsibleChoice || !eligibleInitialResponsibleUsers.length" @click="assignInitialResponsibleAndApprove">
                        Guardar y aprobar
                    </AppButton>
                </footer>
            </template>
        </GlobalModal>
    </PageLayout>
</template>
