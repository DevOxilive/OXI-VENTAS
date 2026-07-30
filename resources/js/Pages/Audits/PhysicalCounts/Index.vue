<script setup>
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'

import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import CreatePhysicalCountModal from '@/Components/Audits/PhysicalCounts/CreatePhysicalCountModal.vue'
import ManagePhysicalCountParticipantsModal from '@/Components/Audits/PhysicalCounts/ManagePhysicalCountParticipantsModal.vue'
import ReopenPhysicalCountModal from '@/Components/Audits/PhysicalCounts/ReopenPhysicalCountModal.vue'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales/useModalConfig'

import { usePermissions } from '@/Composables/usePermissions'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { physicalCountTableConfig } from '@/config/TableConfigs/physicalCountTableConfig'
import { getPhysicalCountToolbarConfig } from '@/config/ToolbarConfigs/physicalCountToolbarConfig'
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribeRealtime } from '@/realtime'

defineOptions({ layout: AdminLayout })

let unsubscribePhysicalCountChanged = null

const props = defineProps({
    physicalCounts: {
        type: Object,
        default: () => ({ data: [] }),
    },
    branches: {
        type: Array,
        default: () => [],
    },
    branch: {
        type: Object,
        default: null,
    },
    users: {
        type: Array,
        default: () => [],
    },
    canViewReports: {
        type: Boolean,
        default: false,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
})

const { can } = usePermissions()
const { handlePageChange } = useGlobalTablePagination({
    only: ['physicalCounts', 'filters'],
})

const search = ref(props.filters.search || '')
const statusFilter = ref(props.filters.status || '')
const recordsPerPage = ref(Number(props.filters.per_page || 25))
const showCreateModal = ref(false)
const showParticipantsModal = ref(false)
const showReopenModal = ref(false)
const selectedPhysicalCount = ref(null)
const reopeningPhysicalCount = ref(false)
let filterTimeout = null

const physicalCounts = computed(() => props.physicalCounts?.data || [])

const physicalCountToolbarConfig = computed(() =>
    getPhysicalCountToolbarConfig({
        branch: props.branch,
        canCreate: can('audits.physical-counts.create'),
        status: statusFilter.value,
    })
)

function handleToolbarFilter({ key, value }) {
    if (key === 'statusFilter') {
        statusFilter.value = value
    }
}

function reloadPhysicalCounts() {
    router.get(route('audits.physical-counts.index'), {
        branch: props.branch?.slug,
        search: search.value || undefined,
        status: statusFilter.value || undefined,
        per_page: recordsPerPage.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
        only: ['physicalCounts', 'filters'],
    })
}

function handleToolbarAction(action) {
    if (action === 'create' && can('audits.physical-counts.create')) {
        showCreateModal.value = true
        return
    }
}

async function handleTableAction({ action, row }) {
    if (action === 'open' && can('audits.physical-counts.count')) {
        router.visit(route('audits.physical-counts.show', row.id))
        return
    }

    if (action === 'participants' && can('audits.physical-counts.participants')) {
        selectedPhysicalCount.value = row
        showParticipantsModal.value = true
        return
    }

    if (action === 'close' && can('audits.physical-counts.close')) {
        const result = await confirmModalAction({
            mode: 'update',
            title: 'Cerrar auditoría',
            message: '¿Deseas cerrar esta auditoría? Ya no se podrá capturar hasta reabrirla.',
            confirmText: 'Sí, cerrar',
            cancelText: 'Cancelar',
            confirmButtonColor: '#f59e0b',
        })

        if (!result.isConfirmed) return

        router.patch(route('audits.physical-counts.close', row.id), {}, getModalRequestOptions({
            mode: 'update',
            entityName: 'Auditoría',
            successTitle: 'Auditoría cerrada correctamente',
            errorTitle: 'Error al cerrar auditoría',
            errorMessage: 'No fue posible cerrar la auditoría.',
            onSuccess: () => {
                reloadPhysicalCounts()
            },
        }))

        return
    }

    if (action === 'reopen' && can('audits.physical-counts.reopen')) {
        selectedPhysicalCount.value = row
        showReopenModal.value = true

        return
    }

    if (action === 'finalize' && can('audits.physical-counts.finalize')) {
        const result = await confirmModalAction({
            mode: 'update',
            title: 'Finalizar auditoría',
            message: '¿Confirmas que todas las rondas fueron revisadas? Después de finalizar ya no se podrá reabrir ni capturar.',
            confirmText: 'Sí, finalizar',
            cancelText: 'Cancelar',
            confirmButtonColor: '#4f46e5',
        })

        if (!result.isConfirmed) return

        router.patch(route('audits.physical-counts.finalize', row.id), {}, getModalRequestOptions({
            mode: 'update',
            entityName: 'Auditoría',
            successTitle: 'Auditoría finalizada correctamente',
            errorTitle: 'Error al finalizar auditoría',
            errorMessage: 'No fue posible finalizar la auditoría.',
            onSuccess: reloadPhysicalCounts,
        }))

        return
    }

    if (action === 'apply' && can('audits.physical-counts.apply')) {
        const result = await confirmModalAction({
            mode: 'update',
            title: 'Aplicar ajustes',
            message: row.status === 'applied'
                ? '¿Deseas volver a aplicar los ajustes? Se recalculará el stock con los conteos actuales de esta auditoría.'
                : '¿Deseas aplicar los ajustes de esta auditoría? El stock se actualizará con base en el conteo físico.',
            confirmText: 'Sí, aplicar',
            cancelText: 'Cancelar',
            confirmButtonColor: '#16a34a',
        })

        if (!result.isConfirmed) return

        router.patch(route('audits.physical-counts.apply-adjustments', row.id), {}, getModalRequestOptions({
            mode: 'update',
            entityName: 'Auditoría',
            successTitle: 'Ajustes aplicados correctamente',
            errorTitle: 'Error al aplicar ajustes',
            errorMessage: 'No fue posible aplicar los ajustes.',
            onSuccess: () => {
                reloadPhysicalCounts()
            },
        }))

        return
    }

    if (action === 'delete' && can('audits.physical-counts.delete')) {
        const result = await confirmModalAction({
            mode: 'delete',
            title: 'Eliminar auditoría',
            message: '¿Deseas eliminar esta auditoría? Esta acción no se puede deshacer.',
            confirmText: 'Sí, eliminar',
            cancelText: 'Cancelar',
            confirmButtonColor: '#ef4444',
        })

        if (!result.isConfirmed) return

        router.delete(route('audits.physical-counts.destroy', row.id), getModalRequestOptions({
            mode: 'delete',
            entityName: 'Auditoría',
            successTitle: 'Auditoría eliminada correctamente',
            errorTitle: 'Error al eliminar auditoría',
            errorMessage: 'No fue posible eliminar la auditoría.',
            onSuccess: () => {
                reloadPhysicalCounts()
            },
        }))
    }
}

function reopenPhysicalCount(scope) {
    if (
        !can('audits.physical-counts.reopen')
        || !selectedPhysicalCount.value
        || reopeningPhysicalCount.value
    ) return

    reopeningPhysicalCount.value = true

    router.patch(route('audits.physical-counts.reopen', selectedPhysicalCount.value.id), {
        recapture_scope: scope,
    }, getModalRequestOptions({
        mode: 'update',
        entityName: 'Auditoría',
        close: () => {
            showReopenModal.value = false
        },
        successTitle: 'Auditoría reabierta correctamente',
        errorTitle: 'Error al reabrir la auditoría',
        errorMessage: 'No fue posible reabrir la auditoría.',
        onSuccess: reloadPhysicalCounts,
        onFinish: () => {
            reopeningPhysicalCount.value = false
        },
    }))
}

onMounted(() => {
    unsubscribePhysicalCountChanged = subscribeRealtime(
        REALTIME_CHANNELS.audits,
        REALTIME_EVENTS.physicalCountChanged,
        () => {
            reloadPhysicalCounts()
        },
    )
})

onBeforeUnmount(() => {
    clearTimeout(filterTimeout)

    unsubscribePhysicalCountChanged?.()
})

watch([statusFilter, recordsPerPage], () => {
    reloadPhysicalCounts()
})

watch(search, () => {
    clearTimeout(filterTimeout)
    filterTimeout = setTimeout(() => {
        reloadPhysicalCounts()
    }, 350)
})
</script>

<template>
    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="physicalCountToolbarConfig"
                :search="search"
                :records-per-page="recordsPerPage"
                :filtered-records="physicalCounts.length"
                :total-records="props.physicalCounts?.total || physicalCounts.length"
                @update:search="search = $event"
                @update:filter="handleToolbarFilter"
                @update:records-per-page="recordsPerPage = $event"
                @action="handleToolbarAction"
            />
        </template>

        <GlobalTable
            :items="physicalCounts"
            v-bind="physicalCountTableConfig"
            :pagination="props.physicalCounts"
            row-key="id"
            @page-change="handlePageChange"
            @action="handleTableAction"
        />

        <CreatePhysicalCountModal
            v-if="can('audits.physical-counts.create')"
            :show="showCreateModal"
            :branch="props.branch"
            :users="props.users"
            @close="showCreateModal = false"
        />

        <ManagePhysicalCountParticipantsModal
            v-if="can('audits.physical-counts.participants')"
            :show="showParticipantsModal"
            :physical-count="selectedPhysicalCount"
            :users="props.users"
            @close="showParticipantsModal = false"
            @updated="reloadPhysicalCounts"
        />

        <ReopenPhysicalCountModal
            v-if="can('audits.physical-counts.reopen')"
            :show="showReopenModal"
            :physical-count="selectedPhysicalCount"
            :processing="reopeningPhysicalCount"
            @close="showReopenModal = false"
            @confirm="reopenPhysicalCount"
        />
    </PageLayout>
</template>
