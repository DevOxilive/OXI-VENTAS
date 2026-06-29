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

const isOpen = computed(() => props.physicalCount.status === 'open')
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
        only: ['physicalCount', 'scannedProduct'],
        preserveScroll: true,
        preserveState: true,
    })
}

onMounted(() => {
    if (!window.Echo) return

    window.Echo.channel('audits')
        .listen('.PhysicalCountChanged', (event) => {
            if (event.physicalCount?.id !== props.physicalCount.id) return
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

        <div class="space-y-6">
            <div
                v-if="physicalCount.status === 'closed'"
                class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600"
            >
                Esta auditoria ya fue finalizada. La captura esta bloqueada.
            </div>

            <div
                v-if="physicalCount.status === 'applied'"
                class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700"
            >
                Esta auditoria ya fue aplicada al inventario.
            </div>

            <div class="space-y-6">
                <template v-if="isOpen">
                    <ProductScanForm :physical-count-id="physicalCount.id" />

                    <ProductFoundCard
                        :product="scannedProduct"
                        :can-view-stock="canViewAuditStock"
                    />

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
                    Esta auditoria esta cerrada. Solo puede consultarse desde reportes.
                </div>
            </div>
        </div>
    </PageLayout>
</template>
