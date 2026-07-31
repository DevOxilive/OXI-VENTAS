<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import EmptyStateCard from '@/Components/Cards/EmptyStateCard.vue'
import ProductScanForm from '@/Components/Audits/PhysicalCounts/ProductScanForm.vue'
import ProductFoundCard from '@/Components/Audits/PhysicalCounts/ProductFoundCard.vue'
import CountEntryForm from '@/Components/Audits/PhysicalCounts/CountEntryForm.vue'
import { getPhysicalCountDetailToolbarConfig } from '@/config/ToolbarConfigs/physicalCountDetailToolbarConfig'
import { usePermissions } from '@/Composables/usePermissions'
import { REALTIME_CHANNELS, REALTIME_EVENTS, refreshRealtimeProps, subscribeRealtime } from '@/realtime'

defineOptions({ layout: AdminLayout })

let unsubscribePhysicalCountChanged = null

const props = defineProps({
    physicalCount: {
        type: Object,
        required: true,
    },
    scannedProduct: {
        type: Object,
        default: null,
    },
    canViewReports: {
        type: Boolean,
        default: false,
    },
})

const { can } = usePermissions()
const page = usePage()

const isCaptureStatus = computed(() => props.physicalCount.status === 'open')
const canCapture = computed(() =>
    isCaptureStatus.value &&
    can('audits.physical-counts.count')
)
const canViewAuditStock = computed(() => can('audits.physical-counts.view-stock'))

const toolbarConfig = computed(() =>
    getPhysicalCountDetailToolbarConfig({
        physicalCount: props.physicalCount,
    })
)

function handleToolbarAction(action) {
    if (action === 'back') {
        router.visit(route('audits.physical-counts.index', {
            branch: props.physicalCount.branch.slug,
        }))
    }
}

function reloadAuditDetail() {
    refreshRealtimeProps(page, ['physicalCount', 'scannedProduct', 'canViewReports'])
}

onMounted(() => {
    unsubscribePhysicalCountChanged = subscribeRealtime(
        REALTIME_CHANNELS.audits,
        REALTIME_EVENTS.physicalCountChanged,
        (event) => {
            if (event.physicalCount?.id !== props.physicalCount.id) return

            if (event.action === 'deleted') {
                router.visit(route('audits.physical-counts.index', {
                    branch: props.physicalCount.branch.slug,
                }))
                return
            }

            reloadAuditDetail()
        },
    )
})

onBeforeUnmount(() => {
    unsubscribePhysicalCountChanged?.()
})
</script>

<template>
    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
                :show-search="false"
                :show-records-per-page="false"
                :show-counter="false"
                @back="handleToolbarAction('back')"
                @action="handleToolbarAction"
            />
        </template>

        <div class="space-y-4">
            <div
                v-if="physicalCount.current_round"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-secondary bg-secondary px-4 py-3 text-sm text-text"
            >
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-xl text-accent">history</span>
                    <div>
                        <p class="font-semibold">
                            Ronda {{ physicalCount.current_round.round_number }}
                            ·
                            {{ physicalCount.current_round.type === 'original' ? 'Conteo original' : 'Reapertura' }}
                        </p>
                        <p class="opacity-70">
                            Alcance:
                            {{ physicalCount.current_round.scope === 'zero_stock' ? 'productos con stock en cero' : 'todos los productos' }}
                        </p>
                    </div>
                </div>
                <span class="opacity-70">
                    Iniciada por {{ physicalCount.current_round.opener?.name || 'Sin usuario' }}
                </span>
            </div>

            <div
                v-if="physicalCount.status === 'closed'"
                class="flex items-start gap-3 rounded-xl border border-secondary bg-secondary px-4 py-3 text-sm text-text"
            >
                <span class="material-symbols-outlined text-xl opacity-70">lock</span>
                <span>Esta auditoría ya fue finalizada. La captura está bloqueada.</span>
            </div>

            <div
                v-if="physicalCount.status === 'applied'"
                class="flex items-start gap-3 rounded-xl border border-accent bg-secondary px-4 py-3 text-sm text-accent"
            >
                <span class="material-symbols-outlined text-xl">inventory</span>
                <span>Esta auditoría ya fue aplicada al inventario. Puedes seguir capturando conteos dentro de la misma auditoría.</span>
            </div>

            <div
                v-if="physicalCount.recapture_scope === 'zero_stock'"
                class="flex items-start gap-3 rounded-xl border border-accent bg-secondary px-4 py-3 text-sm text-accent"
            >
                <span class="material-symbols-outlined text-xl">filter_alt</span>
                <span>Este conteo fue reactivado solo para productos sin existencias. La búsqueda y la captura se limitan a esos productos.</span>
            </div>

            <div class="space-y-3">
                <template v-if="canCapture">
                    <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(300px,0.75fr)_minmax(540px,1.5fr)]">
                        <ProductScanForm :physical-count-id="physicalCount.id" />

                        <ProductFoundCard
                            :product="scannedProduct"
                            :can-view-stock="canViewAuditStock"
                        />
                    </div>

                    <CountEntryForm
                        v-if="scannedProduct"
                        :physical-count-id="physicalCount.id"
                        :product="scannedProduct"
                        :can-view-stock="canViewAuditStock"
                    />

                    <EmptyStateCard
                        v-else
                        icon="barcode_scanner"
                        title="Selecciona un producto"
                        description="Escanea un código o utiliza la búsqueda para habilitar la captura del conteo."
                        min-height-class="min-h-[150px]"
                    />
                </template>

                <EmptyStateCard
                    v-else
                    icon="lock"
                    :title="
                        isCaptureStatus
                            ? 'No tienes permiso para capturar conteos en esta auditoría.'
                            : 'Esta auditoría está cerrada.'
                    "
                    :description="
                        isCaptureStatus
                            ? 'Puedes consultar la información, pero no modificar sus registros.'
                            : 'El detalle final está disponible desde los reportes de auditoría.'
                    "
                    min-height-class="min-h-[180px]"
                />
            </div>
        </div>
    </PageLayout>
</template>
