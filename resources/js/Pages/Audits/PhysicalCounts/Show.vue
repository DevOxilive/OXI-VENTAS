<script setup>
import PhysicalCountHeader from "@/Components/Audits/PhysicalCounts/PhysicalCountHeader.vue";
import ProductScanForm from "@/Components/Audits/PhysicalCounts/ProductScanForm.vue";
import ProductFoundCard from "@/Components/Audits/PhysicalCounts/ProductFoundCard.vue";
import CountEntryForm from "@/Components/Audits/PhysicalCounts/CountEntryForm.vue";
import CountEntriesTable from "@/Components/Audits/PhysicalCounts/CountEntriesTable.vue";
import PhysicalCountSummary from "@/Components/Audits/PhysicalCounts/PhysicalCountSummary.vue";
import InventoryComparisonTable from "@/Components/Audits/PhysicalCounts/InventoryComparisonTable.vue";
import { router } from "@inertiajs/vue3";
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  physicalCount: Object,
  entries: Array,
  summary: Array,
  scannedProduct: Object,
  comparison: Array,
});
const closePhysicalCount = () => {
  if (
    !confirm(
      "¿Seguro que quieres finalizar esta auditoría? Ya no se podrán registrar más conteos."
    )
  ) {
    return;
  }

  router.patch(
    route("audits.physical-counts.close", props.physicalCount.id),
    {},
    {
      preserveScroll: true,
    }
  );
};
const applyAdjustments = () => {
  if (
    !confirm(
      "¿Seguro que quieres aplicar estos ajustes al inventario? Esta acción actualizará el stock del sistema y ya no se podrá modificar la auditoría."
    )
  ) {
    return;
  }

  router.patch(
    route("audits.physical-counts.apply-adjustments", props.physicalCount.id),
    {},
    {
      preserveScroll: true,
    }
  );
};
const reopenPhysicalCount = () => {
  if (
    !confirm(
      "¿Seguro que quieres reabrir esta auditoría? Se podrán registrar nuevos conteos."
    )
  ) {
    return;
  }

  router.patch(
    route("audits.physical-counts.reopen", props.physicalCount.id),
    {},
    {
      preserveScroll: true,
    }
  );
};
</script>
<template>
        <AdminLayout>
  <div class="p-6 space-y-6">
    <div class="flex items-start justify-between gap-4">
      <PhysicalCountHeader
        :title="physicalCount?.name ?? 'Conteo físico'"
       :subtitle="`Folio: ${physicalCount?.folio ?? 'Sin folio'} | Sucursal: ${physicalCount?.branch?.name ?? 'Sin sucursal seleccionada'}`"
      />
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
      
      <button
        v-if="physicalCount.status === 'open'"
        type="button"
        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
        @click="closePhysicalCount"
      >
        Finalizar auditoría
      </button>
      <button
        v-if="physicalCount.status === 'closed'"
        type="button"
        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
        @click="reopenPhysicalCount"
      >
        Reabrir auditoría
      </button>
      <button
        v-if="physicalCount.status === 'closed'"
        type="button"
        class="rounded-lg bg-purple-600 px-4 py-2 text-sm font-semibold text-white hover:bg-purple-700"
        @click="applyAdjustments"
      >
        Aplicar ajustes
      </button>
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

        <CountEntriesTable :entries="entries" />

        <InventoryComparisonTable :comparison="comparison" />
      </div>

      <div class="xl:col-span-4">
        <PhysicalCountSummary :summary="summary" />
      </div>
    </div>
  </div>
  </AdminLayout>
</template>