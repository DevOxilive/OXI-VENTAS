<script setup>
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import { GlobalModal } from '@/Components/Modales'
import InventoryMovementReportToolbar from '@/Components/Inventory/Reports/InventoryMovementReportToolbar.vue'
import MovementReportTable from '@/Components/Inventory/Reports/MovementReportTable.vue'
import { useInventoryMovementReport } from '@/Composables/Inventory/useInventoryMovementReport'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    currentBranch: {
        type: Object,
        required: true,
    },
    filters: {
        type: Object,
        default: () => ({}),
    },
    catalogs: {
        type: Object,
        default: () => ({}),
    },
    movements: {
        type: [Array, Object],
        default: () => [],
    },
})

const {
    filtersState,
    pageTitle,
    departmentOptions,
    filteredCategoryOptions,
    userOptions,
    movementTypeOptions,
    movementReasonOptions,
    tableRows,
    tablePagination,
    backToReportsCenter,
    updateSearch,
    updateFilter,
    handlePageChange,
    reloadReport,
    handleToolbarAction,
} = useInventoryMovementReport(props)

const selectedMovement = ref(null)

function handleMovementAction({ action, row }) {
    if (action === 'view') {
        selectedMovement.value = row
    }
}

function closeMovementDetail() {
    selectedMovement.value = null
}

function formatCurrency(value) {
    return new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
    }).format(Number(value || 0))
}

function formatQuantity(value) {
    return new Intl.NumberFormat('es-MX', {
        maximumFractionDigits: 3,
    }).format(Number(value || 0))
}
</script>

<template>
    <Head :title="pageTitle" />

    <PageLayout>
        <template #toolbar>
            <InventoryMovementReportToolbar
                :filters="filtersState"
                :product-departments="departmentOptions"
                :categories="filteredCategoryOptions"
                :users="userOptions"
                :movement-types="movementTypeOptions"
                :movement-reasons="movementReasonOptions"
                @back="backToReportsCenter"
                @update:search="updateSearch"
                @update:filter="updateFilter"
                @update:records-per-page="filtersState.perPage = $event"
                @action="handleToolbarAction"
            />
        </template>

        <MovementReportTable
            :rows="tableRows"
            :pagination="tablePagination"
            @page-change="handlePageChange"
            @action="handleMovementAction"
        />

        <GlobalModal
            v-if="selectedMovement"
            title="Detalle del movimiento"
            :subtitle="selectedMovement.product"
            mode="view"
            size="md"
            height="auto"
            backdrop="soft"
            :columns="1"
            :show-save="false"
            close-button-text="Cerrar"
            content-class="p-4 sm:p-5"
            @close="closeMovementDetail"
        >
            <section class="space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-secondary bg-secondary/60 px-4 py-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-text/50">Código</p>
                        <p class="mt-0.5 font-bold text-text">{{ selectedMovement.code || '-' }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">{{ selectedMovement.status_label }}</span>
                        <span class="rounded-full border border-secondary bg-background px-3 py-1 text-xs font-bold text-text">{{ selectedMovement.movement_reason_label }}</span>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-primary/25 bg-primary/5 p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-primary/70">Cantidad</p>
                        <p class="mt-1 text-2xl font-black text-primary">{{ formatQuantity(selectedMovement.quantity) }}</p>
                        <p class="text-xs font-semibold text-text/60">{{ selectedMovement.inventory_unit }}</p>
                    </div>
                    <div class="rounded-2xl border border-secondary bg-background p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-text/50">Costo unitario</p>
                        <p class="mt-2 text-lg font-bold">{{ formatCurrency(selectedMovement.unit_cost) }}</p>
                    </div>
                    <div class="rounded-2xl border border-secondary bg-background p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-text/50">Importe</p>
                        <p class="mt-2 text-lg font-bold">{{ formatCurrency(selectedMovement.estimated_loss) }}</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-secondary bg-background p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-text/50">Existencia</p>
                    <div class="mt-3 grid grid-cols-[1fr_auto_1fr] items-center gap-3 text-center">
                        <div>
                            <p class="text-xs text-text/60">Anterior</p>
                            <p class="mt-1 text-lg font-bold">{{ formatQuantity(selectedMovement.previous_stock) }}</p>
                        </div>
                        <span class="material-symbols-outlined text-primary">arrow_forward</span>
                        <div>
                            <p class="text-xs text-text/60">Nueva</p>
                            <p class="mt-1 text-lg font-bold text-primary">{{ formatQuantity(selectedMovement.new_stock) }}</p>
                        </div>
                    </div>
                </div>

                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 rounded-2xl border border-secondary bg-background p-4 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-text/50">Fecha</dt>
                        <dd class="mt-0.5 font-semibold">{{ selectedMovement.movement_date }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text/50">Usuario</dt>
                        <dd class="mt-0.5 font-semibold">{{ selectedMovement.user || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text/50">Lote</dt>
                        <dd class="mt-0.5 font-semibold">{{ selectedMovement.lot_number || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text/50">Departamento</dt>
                        <dd class="mt-0.5 font-semibold">{{ selectedMovement.department || '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-text/50">Categoría</dt>
                        <dd class="mt-0.5 font-semibold">{{ selectedMovement.category || '-' }}</dd>
                    </div>
                </dl>

                <div v-if="selectedMovement.notes" class="rounded-2xl border border-secondary bg-secondary/40 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-text/50">Notas</p>
                    <p class="mt-1 whitespace-pre-line text-sm font-medium">{{ selectedMovement.notes }}</p>
                </div>
            </section>
        </GlobalModal>
    </PageLayout>
</template>
