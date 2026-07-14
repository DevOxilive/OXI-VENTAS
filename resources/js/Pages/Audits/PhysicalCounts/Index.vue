<script setup>
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue'
import { router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'

import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import CreatePhysicalCountModal from '@/Components/Audits/PhysicalCounts/CreatePhysicalCountModal.vue'
import ManagePhysicalCountParticipantsModal from '@/Components/Audits/PhysicalCounts/ManagePhysicalCountParticipantsModal.vue'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales/useModalConfig'

import { usePermissions } from '@/Composables/usePermissions'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { physicalCountTableConfig } from '@/config/TableConfigs/physicalCountTableConfig'
import { getPhysicalCountToolbarConfig } from '@/config/ToolbarConfigs/physicalCountToolbarConfig'

defineOptions({ layout: AdminLayout })

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
const selectedPhysicalCount = ref(null)
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
    if (action === 'create') {
        showCreateModal.value = true
        return
    }
}

async function handleTableAction({ action, row }) {
    if (action === 'open') {
        router.visit(route('audits.physical-counts.show', row.id))
        return
    }

    if (action === 'participants') {
        selectedPhysicalCount.value = row
        showParticipantsModal.value = true
        return
    }

    if (action === 'close') {
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

    if (action === 'reopen') {
        const result = await confirmModalAction({
            mode: 'update',
            title: row.status === 'applied' ? 'Reactivar conteo' : 'Reabrir auditoria',
            html: '<div class="text-left text-xs text-[#0f0001cc] dark:text-[#fff0f1cc]">Selecciona que productos se van a contar en esta nueva ronda.</div>',
            icon: 'question',
            input: 'radio',
            inputValue: 'all',
            inputOptions: {
                all: 'Todos los productos',
                zero_stock: 'Solo productos con stock en cero',
            },
            confirmButtonText: 'Continuar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#2563eb',
            width: 420,
            customClass: {
                popup: 'rounded-2xl !p-5',
                title: '!text-xl !pt-2',
                icon: '!mt-2 !mb-2',
                htmlContainer: '!mt-2 !mb-1',
                input: '!mt-3 !mb-2 grid grid-cols-1 gap-2 text-left sm:grid-cols-2',
                actions: '!mt-4',
                confirmButton: 'px-4 py-2 rounded-full',
                cancelButton: 'px-4 py-2 rounded-full',
            },
            inputValidator: (value) => {
                if (!value) return 'Selecciona una opcion para continuar.'
            },
        })

        if (!result.isConfirmed) return

        router.patch(route('audits.physical-counts.reopen', row.id), {
            recapture_scope: result.value || 'all',
        }, getModalRequestOptions({
            mode: 'update',
            entityName: 'Auditoria',
            successTitle: 'Conteo reactivado correctamente',
            errorTitle: 'Error al reactivar conteo',
            errorMessage: 'No fue posible reactivar el conteo.',
            onSuccess: () => {
                reloadPhysicalCounts()
            },
        }))

        return
    }

    if (action === 'apply') {
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

    if (action === 'delete') {
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

onMounted(() => {
    if (!window.Echo) return

    window.Echo.channel('audits')
        .listen('.PhysicalCountChanged', () => {
            reloadPhysicalCounts()
        })
})

onBeforeUnmount(() => {
    clearTimeout(filterTimeout)

    if (!window.Echo) return

    window.Echo.leave('audits')
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
            v-if="can('audits.physical-counts.update')"
            :show="showParticipantsModal"
            :physical-count="selectedPhysicalCount"
            :users="props.users"
            @close="showParticipantsModal = false"
            @updated="reloadPhysicalCounts"
        />
    </PageLayout>
</template>
