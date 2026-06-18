<script setup>
import { computed, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'

import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'

import ProductScanForm from '@/Components/Audits/PhysicalCounts/ProductScanForm.vue'
import ProductFoundCard from '@/Components/Audits/PhysicalCounts/ProductFoundCard.vue'
import CountEntryForm from '@/Components/Audits/PhysicalCounts/CountEntryForm.vue'
import PhysicalCountSummary from '@/Components/Audits/PhysicalCounts/PhysicalCountSummary.vue'
import PhysicalCountEntryModal from '@/Components/Audits/PhysicalCounts/PhysicalCountEntryModal.vue'

import { usePhysicalCountEntryActions } from '@/Composables/Audits/usePhysicalCountEntryActions'
import { getPhysicalCountDetailToolbarConfig } from '@/config/ToolbarConfigs/physicalCountDetailToolbarConfig'
import { getPhysicalCountEntryTableConfig } from '@/config/TableConfigs/physicalCountEntryTableConfig'
import { physicalCountComparisonTableConfig } from '@/config/ToolbarConfigs/physicalCountComparisonTableConfig'
defineOptions({ layout: AdminLayout })

const props = defineProps({
  physicalCount: Object,
  entries: {
    type: Array,
    default: () => [],
  },
  summary: {
    type: [Array, Object],
    default: () => [],
  },
  scannedProduct: Object,
  comparison: {
    type: Array,
    default: () => [],
  }
})

const {
  showModal,
  modalMode,
  selectedEntry,
  openViewModal,
  openEditModal,
  openDeleteModal,
  closeModal,
} = usePhysicalCountEntryActions()

const isClosed = computed(() =>
  props.physicalCount.status === 'closed' || props.physicalCount.status === 'applied'
)

const isOpen = computed(() => props.physicalCount.status === 'open')

const toolbarConfig = computed(() =>
  getPhysicalCountDetailToolbarConfig({
    physicalCount: props.physicalCount,
  })
)

const entryTableConfig = computed(() =>
  getPhysicalCountEntryTableConfig({
    status: props.physicalCount.status,
  })
)

function handleToolbarAction(action) {
  if (action === 'back') {
    router.visit(route('audits.physical-counts.index', {
      branch: props.physicalCount.branch.slug,
    }))
    return
  }

  if (action === 'exportPdf') {
    window.location.href = route('audits.physical-counts.export-pdf', props.physicalCount.id)
    return
  }

  if (action === 'exportExcel') {
    window.location.href = route('audits.physical-counts.export-excel', props.physicalCount.id)
  }
}

function handleEntryAction({ action, row }) {
  if (action === 'view') openViewModal(row)
  if (action === 'edit') openEditModal(row)
  if (action === 'delete') openDeleteModal(row)
}

function reloadAuditDetail() {
  router.reload({
    only: ['entries', 'summary', 'comparison'],
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
      <GlobalToolbar v-bind="toolbarConfig" :show-search="false" :show-records-per-page="false" :show-counter="false"
        @action="handleToolbarAction" />
    </template>

    <div class="space-y-6">
      <div v-if="physicalCount.status === 'closed'"
        class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
        Esta auditoría ya fue finalizada. Puedes reabrirla o aplicar ajustes al inventario.
      </div>

      <div v-if="physicalCount.status === 'applied'"
        class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
        Esta auditoría ya fue aplicada al inventario.
      </div>

      <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="space-y-6 xl:col-span-8">
          <template v-if="isOpen">
            <ProductScanForm :physical-count-id="physicalCount.id" />

            <ProductFoundCard :product="scannedProduct" />

            <CountEntryForm :physical-count-id="physicalCount.id" :product="scannedProduct" />
          </template>

          <div v-else class="rounded-2xl border border-slate-200 bg-slate-50 p-6 text-sm text-slate-600">
            Esta auditoría está finalizada. La captura está bloqueada y solo puede consultarse la información.
          </div>

          <section class="space-y-3">
            <h3 class="text-lg font-black text-slate-800">
              Productos contados
            </h3>

            <GlobalTable :items="entries" v-bind="entryTableConfig" row-key="id" :show-pagination="false"
              @action="handleEntryAction" />
          </section>

          <section class="space-y-3">
            <h3 class="text-lg font-black text-slate-800">
              Comparativo de inventario
            </h3>

            <GlobalTable :items="comparison" v-bind="physicalCountComparisonTableConfig" row-key="id"
              :show-pagination="false" />
          </section>
        </div>

        <div class="xl:col-span-4">
          <PhysicalCountSummary :summary="Array.isArray(summary) ? summary : Object.values(summary || {})" />
        </div>
      </div>
    </div>

    <PhysicalCountEntryModal v-if="showModal && selectedEntry" :mode="modalMode" :entry="selectedEntry"
      @close="closeModal" />
  </PageLayout>
</template>