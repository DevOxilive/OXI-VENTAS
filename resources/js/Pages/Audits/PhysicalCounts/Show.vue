<script setup>
import { computed, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import ProductScanForm from '@/Components/Audits/PhysicalCounts/ProductScanForm.vue'
import ProductFoundCard from '@/Components/Audits/PhysicalCounts/ProductFoundCard.vue'
import CountEntryForm from '@/Components/Audits/PhysicalCounts/CountEntryForm.vue'
import { getPhysicalCountDetailToolbarConfig } from '@/config/ToolbarConfigs/physicalCountDetailToolbarConfig'
import { usePermissions } from '@/Composables/usePermissions'

defineOptions({ layout: AdminLayout })

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

const canCapture = computed(() => ['open', 'applied'].includes(props.physicalCount.status))
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
        return
    }
}

function reloadAuditDetail() {
    router.reload({
        preserveScroll: true,
        preserveState: false,
    })
}

onMounted(() => {
    if (!window.Echo) return

    window.Echo.channel('audits')
        .listen('.PhysicalCountChanged', (event) => {
            if (event.physicalCount?.id !== props.physicalCount.id) return

            if (event.action === 'deleted') {
                router.visit(route('audits.physical-counts.index', {
                    branch: props.physicalCount.branch.slug,
                }))
                return
            }

            reloadAuditDetail()
        })
})

onBeforeUnmount(() => {
    if (!window.Echo) return
    window.Echo.leave('audits')
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
                @action="handleToolbarAction"
            />
        </template>

        <div class="space-y-4">
            <div
                v-if="physicalCount.status === 'closed'"
                class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600"
            >
                Esta auditoría ya fue finalizada. La captura está bloqueada.
            </div>

            <div
                v-if="physicalCount.status === 'applied'"
                class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
            >
                Esta auditoria ya fue aplicada al inventario. Puedes seguir capturando conteos dentro de la misma auditoria.
            </div>

            <div
                v-if="physicalCount.recapture_scope === 'zero_stock'"
                class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700"
            >
                Este conteo fue reactivado solo para productos con stock en cero. La busqueda y captura se limitan a esos productos.
            </div>

            <div class="space-y-3">
                <template v-if="canCapture">
                    <div class="grid grid-cols-1 gap-3 xl:grid-cols-[minmax(220px,320px)_minmax(520px,1fr)]">
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

                    <div
                        v-else
                        class="rounded-xl border border-dashed border-gray-300 bg-gray-50 p-6 text-sm text-gray-500"
                    >
                        Selecciona un producto para habilitar la captura de conteo.
                    </div>
                </template>

                <div
                    v-else
                    class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-600"
                >
                    Esta auditoría está cerrada. Solo puede consultarse desde reportes.
                </div>
            </div>
        </div>
    </PageLayout>
</template>
