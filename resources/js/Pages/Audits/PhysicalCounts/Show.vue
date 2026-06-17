<script setup>

import { onMounted, onBeforeUnmount } from 'vue'
import PhysicalCountHeader from "@/Components/Audits/PhysicalCounts/PhysicalCountHeader.vue"
import ProductScanForm from "@/Components/Audits/PhysicalCounts/ProductScanForm.vue"
import ProductFoundCard from "@/Components/Audits/PhysicalCounts/ProductFoundCard.vue"
import CountEntryForm from "@/Components/Audits/PhysicalCounts/CountEntryForm.vue"
import CountEntriesTable from "@/Components/Audits/PhysicalCounts/CountEntriesTable.vue"
import PhysicalCountSummary from "@/Components/Audits/PhysicalCounts/PhysicalCountSummary.vue"
import InventoryComparisonTable from "@/Components/Audits/PhysicalCounts/InventoryComparisonTable.vue"
import PhysicalCountEntryModal from "@/Components/Audits/PhysicalCounts/PhysicalCountEntryModal.vue"
import { router, Link } from "@inertiajs/vue3"
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { usePhysicalCountEntryActions } from '@/Composables/Audits/usePhysicalCountEntryActions'

const props = defineProps({
  physicalCount: Object,
  entries: Array,
  summary: Array,
  scannedProduct: Object,
  comparison: Array,
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
        <AdminLayout>
  <div class="p-6 space-y-6">
    <Link
    :href="route('audits.physical-counts.index', { branch: physicalCount.branch.slug })"
    class="inline-flex items-center rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
>
    ← Volver a auditorías
</Link>

  <div
  v-if="physicalCount.status === 'closed' || physicalCount.status === 'applied'"
  class="flex items-center gap-3"
>
  <a
    :href="route('audits.physical-counts.export-pdf', physicalCount.id)"
    class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
  >
    Exportar PDF
  </a>

  <a
    :href="route('audits.physical-counts.export-excel', physicalCount.id)"
    class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700"
  >
    Exportar Excel
  </a>
</div>
<div
    v-if="physicalCount.status === 'closed'"
    class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-2 text-sm text-gray-600"
>
    Esta auditoría ya fue finalizada. Puedes reabrirla o aplicar ajustes al inventario.
</div>

<div
    v-if="physicalCount.status === 'applied'"
    class="rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700"
>
    Esta auditoría ya fue aplicada al inventario.
</div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
      <div class="space-y-6 xl:col-span-8">
        <template v-if="physicalCount.status === 'open'">
          <ProductScanForm :physical-count-id="physicalCount.id" />

          <ProductFoundCard :product="scannedProduct" />

          <CountEntryForm
            :physical-count-id="physicalCount.id"
            :product="scannedProduct"
          />
        </template>

        <div
          v-else
          class="rounded-xl border border-gray-200 bg-gray-50 p-6 text-sm text-gray-600"
        >
          Esta auditoría está finalizada. La captura está bloqueada y solo puede
          consultarse la información.
        </div>

<CountEntriesTable
    :entries="entries"
    :status="physicalCount.status"
    @view="openViewModal"
    @edit="openEditModal"
    @delete="openDeleteModal"
/>
        <InventoryComparisonTable :comparison="comparison" />
      </div>

      <div class="xl:col-span-4">
        <PhysicalCountSummary :summary="summary" />
      </div>
    </div>
  </div>
<PhysicalCountEntryModal
    v-if="showModal && selectedEntry"
    :mode="modalMode"
    :entry="selectedEntry"
    @close="closeModal"
/>
  </AdminLayout>
</template>