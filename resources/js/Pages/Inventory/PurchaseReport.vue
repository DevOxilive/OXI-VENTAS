<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { usePurchaseReport } from '@/Composables/Inventory/usePurchaseReport'

import PurchaseReportHeader from '@/Components/Inventory/PurchaseReports/PurchaseReportHeader.vue'
import PurchaseReportFilters from '@/Components/Inventory/PurchaseReports/PurchaseReportFilters.vue'
import PurchaseReportTable from '@/Components/Inventory/PurchaseReports/PurchaseReportTable.vue'
import PurchaseReportCart from '@/Components/Inventory/PurchaseReports/PurchaseReportCart.vue'
import PurchaseReportPagination from '@/Components/Inventory/PurchaseReports/PurchaseReportPagination.vue'
import PurchaseReportDrafts from '@/Components/Inventory/PurchaseReports/PurchaseReportDrafts.vue'
import PurchaseReportDraftModal from '@/Components/Inventory/PurchaseReports/PurchaseReportDraftModal.vue'

const props = defineProps({
    currentBranch: Object,
    productsDB: Object,
    filters: Object,
    categoriesDB: Array,
    subcategoriesDB: Array,
    reportsDB: {
        type: Array,
        default: () => [],
    },
})

const report = usePurchaseReport(props)

const selectedDraft = ref(null)

function openDraft(report) {
    selectedDraft.value = report
}

function closeDraft() {
    selectedDraft.value = null
}
</script>

<template>

    <Head title="Reporte de compra" />

    <AdminLayout>
        <div class="space-y-6">
            <PurchaseReportHeader :branch="currentBranch" :selected-count="report.selectedCount.value"
                @save="report.saveDraft" />

            <PurchaseReportDrafts :reports="reportsDB" @open="openDraft" />
            <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_420px]">
                <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <PurchaseReportFilters :filters="report.localFilters.value" :categories="categoriesDB"
                        :subcategories="subcategoriesDB" @apply="report.applyFilters" />
                    <PurchaseReportTable :products="report.products.value" :selected-items="report.selectedItems.value"
                        @toggle="report.toggleProduct" />

                    <PurchaseReportPagination :links="productsDB.links" />
                </div>

                <PurchaseReportCart :notes="report.notes.value" :selected-products="report.selectedProducts.value"
                    :selected-count="report.selectedCount.value" :total-quantity="report.totalQuantity.value"
                    :estimated-total="report.estimatedTotal.value" @update-notes="report.notes.value = $event"
                    @update-item="report.updateItem" @remove="report.removeItem" @save="report.saveDraft" />
            </section>
        </div>

        <PurchaseReportDraftModal v-if="selectedDraft" :report="selectedDraft" @close="closeDraft" />
    </AdminLayout>
</template>