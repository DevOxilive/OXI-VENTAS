<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
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
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribeRealtime } from '@/realtime'

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
const audit = ref({ ...props.physicalCount })
const selectedProduct = ref(props.scannedProduct ? {
    ...props.scannedProduct,
    batches: [...(props.scannedProduct.batches || [])],
} : null)

watch(() => props.physicalCount, (value) => {
    audit.value = { ...value }
})

watch(() => props.scannedProduct, (value) => {
    selectedProduct.value = value ? { ...value, batches: [...(value.batches || [])] } : null
})

const isCaptureStatus = computed(() => audit.value.status === 'open')
const canCapture = computed(() =>
    isCaptureStatus.value &&
    can('audits.physical-counts.count')
)
const canViewAuditStock = computed(() => can('audits.physical-counts.view-stock'))

const toolbarConfig = computed(() =>
    getPhysicalCountDetailToolbarConfig({
        physicalCount: audit.value,
    })
)

function handleToolbarAction(action) {
    if (action === 'back') {
        router.visit(route('audits.physical-counts.index', {
            branch: props.physicalCount.branch.slug,
        }))
    }
}

onMounted(() => {
    unsubscribePhysicalCountChanged = subscribeRealtime(
        REALTIME_CHANNELS.audits,
        REALTIME_EVENTS.physicalCountChanged,
        (event) => {
            if (event.physicalCount?.id !== audit.value.id) return

            if (event.action === 'deleted') {
                router.visit(route('audits.physical-counts.index', {
                    branch: props.physicalCount.branch.slug,
                }))
                return
            }

            const currentUserId = Number(page.props.auth?.user?.id)
            const wasDetached = (event.details?.detached_user_ids || [])
                .map(Number)
                .includes(currentUserId)
            const participantIds = (event.physicalCount.participant_ids || []).map(Number)

            if (
                event.action === 'participants_updated'
                && (wasDetached || !participantIds.includes(currentUserId))
            ) {
                router.visit(route('audits.physical-counts.index', {
                    branch: audit.value.branch.slug,
                }))
                return
            }

            audit.value = {
                ...audit.value,
                ...event.physicalCount,
                branch: event.physicalCount.branch || audit.value.branch,
            }

            if (event.action === 'entry_created' && selectedProduct.value) {
                const entry = event.details?.entry
                if (Number(entry?.branch_product_id) !== Number(selectedProduct.value.branch_product_id)) return

                selectedProduct.value = {
                    ...selectedProduct.value,
                    batches: selectedProduct.value.batches.map((batch) => {
                        if (Number(batch.id) !== Number(entry.product_batch_id)) return batch

                        const users = [...(batch.counted_by || [])]
                        if (entry.user && !users.some((user) => Number(user.id) === Number(entry.user.id))) {
                            users.push(entry.user)
                        }

                        return {
                            ...batch,
                            is_counted: true,
                            counted_by: users,
                            count_records: Number(batch.count_records || 0) + 1,
                        }
                    }),
                }
            }

            if (event.action === 'entry_deleted' && selectedProduct.value) {
                const entry = event.details?.entry
                if (Number(entry?.branch_product_id) !== Number(selectedProduct.value.branch_product_id)) return

                selectedProduct.value = {
                    ...selectedProduct.value,
                    batches: selectedProduct.value.batches.map((batch) =>
                        Number(batch.id) === Number(entry.product_batch_id)
                            ? { ...batch, ...(event.details?.batch_status || {}) }
                            : batch
                    ),
                }
            }
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
                v-if="audit.current_round"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-secondary bg-secondary px-4 py-3 text-sm text-text"
            >
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-xl text-accent">history</span>
                    <div>
                        <p class="font-semibold">
                            Ronda {{ audit.current_round.round_number }}
                            ·
                            {{ audit.current_round.type === 'original' ? 'Conteo original' : 'Reapertura' }}
                        </p>
                        <p class="opacity-70">
                            Alcance:
                            {{ audit.current_round.scope === 'zero_stock' ? 'productos con stock en cero' : 'todos los productos' }}
                        </p>
                    </div>
                </div>
                <span class="opacity-70">
                    Iniciada por {{ audit.current_round.opener?.name || 'Sin usuario' }}
                </span>
            </div>

            <div
                v-if="audit.status === 'closed'"
                class="flex items-start gap-3 rounded-xl border border-secondary bg-secondary px-4 py-3 text-sm text-text"
            >
                <span class="material-symbols-outlined text-xl opacity-70">lock</span>
                <span>La ronda actual está cerrada. Puede reabrirse o finalizarse según los permisos asignados.</span>
            </div>

            <div
                v-if="audit.status === 'finalized'"
                class="flex items-start gap-3 rounded-xl border border-accent bg-secondary px-4 py-3 text-sm text-accent"
            >
                <span class="material-symbols-outlined text-xl">verified</span>
                <span>La auditoría está finalizada. La captura y las reaperturas están bloqueadas; sólo falta aplicar los ajustes.</span>
            </div>

            <div
                v-if="audit.status === 'applied'"
                class="flex items-start gap-3 rounded-xl border border-accent bg-secondary px-4 py-3 text-sm text-accent"
            >
                <span class="material-symbols-outlined text-xl">inventory</span>
                <span>Esta auditoría ya fue aplicada al inventario y quedó bloqueada definitivamente.</span>
            </div>

            <div
                v-if="audit.recapture_scope === 'zero_stock'"
                class="flex items-start gap-3 rounded-xl border border-accent bg-secondary px-4 py-3 text-sm text-accent"
            >
                <span class="material-symbols-outlined text-xl">filter_alt</span>
                <span>Este conteo fue reactivado solo para productos sin existencias. La búsqueda y la captura se limitan a esos productos.</span>
            </div>

            <div class="space-y-3">
                <template v-if="canCapture">
                    <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(300px,0.75fr)_minmax(540px,1.5fr)]">
                        <ProductScanForm :physical-count-id="audit.id" />

                        <ProductFoundCard
                            :product="selectedProduct"
                            :can-view-stock="canViewAuditStock"
                        />
                    </div>

                    <CountEntryForm
                        v-if="selectedProduct"
                        :physical-count-id="audit.id"
                        :product="selectedProduct"
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
