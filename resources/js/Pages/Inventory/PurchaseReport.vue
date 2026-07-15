<script setup>
import { computed, ref } from "vue";
import { Head } from "@inertiajs/vue3";

import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import GlobalToolbar from "@/Components/Toolbars/GlobalToolbar.vue";
import GlobalTable from "@/Components/Tables/GlobalTable.vue";
import AppButton from "@/Components/Buttons/AppButton.vue";
import InputField from "@/Components/Forms/InputField.vue";
import SelectField from "@/Components/Forms/SelectField.vue";
import QuantityStepper from "@/Components/Forms/QuantityStepper.vue";
import EmptyStateCard from "@/Components/Cards/EmptyStateCard.vue";
import MetricCard from "@/Components/Cards/MetricCard.vue";
import SectionHeading from "@/Components/Cards/SectionHeading.vue";
import TextareaField from "@/Components/Forms/TextareaField.vue";
import { useGlobalTablePagination } from "@/Composables/useGlobalTablePagination";
import { usePurchaseReport } from "@/Composables/Inventory/usePurchaseReport";
import { getPurchaseReportProductsTableConfig } from "@/config/TableConfigs/purchaseReportProductsTableConfig";
import { getPurchaseReportToolbarConfig } from "@/config/ToolbarConfigs/purchaseReportToolbarConfig";

import PurchaseReportDrafts from "@/Components/Inventory/PurchaseReports/PurchaseReportDrafts.vue";
import PurchaseReportDraftModal from "@/Components/Inventory/PurchaseReports/PurchaseReportDraftModal.vue";

const props = defineProps({
    currentBranch: Object,
    productsDB: Object,
    filters: Object,
    categoriesDB: Array,
    reportsDB: {
        type: Array,
        default: () => [],
    },
});

const report = usePurchaseReport(props);
const { handlePageChange } = useGlobalTablePagination();
const selectedDraft = ref(null);

const tableConfig = getPurchaseReportProductsTableConfig();
const stockOptions = [
    { label: "Todo el stock", value: "" },
    { label: "Stock bajo", value: "LOW" },
    { label: "Agotados", value: "OUT" },
];
const perPageOptions = [
    { label: "15 por pagina", value: 15 },
    { label: "25 por pagina", value: 25 },
    { label: "50 por pagina", value: 50 },
];

const branchLabel = computed(() => props.currentBranch?.name || "Sin sucursal");
const toolbarConfig = computed(() => getPurchaseReportToolbarConfig({
    branchName: branchLabel.value,
    editingFolio: report.editingOrder.value?.folio || "",
}));
const tableRows = computed(() => report.tableRows.value);
const pagination = computed(() => report.paginator.value);
const selectedProducts = computed(() => report.selectedProducts.value);

function formatMoney(value) {
    return new Intl.NumberFormat("es-MX", {
        style: "currency",
        currency: "MXN",
    }).format(Number(value || 0));
}

function handleTableAction({ action, row }) {
    if (action === "add") {
        report.addProduct(row);
        return;
    }

    if (action === "remove") {
        report.removeItem(row.id);
    }
}

function handleTableRowClick(row) {
    report.toggleProduct(row);
}

function openDraft(draft) {
    selectedDraft.value = draft;
}

function closeDraft() {
    selectedDraft.value = null;
}

function editDraft(draft) {
    closeDraft();
    report.editDraft(draft);
}

function handleToolbarAction(action) {
    if (action === "clear") {
        report.clearWorkspace();
    }
}
</script>

<template>
    <Head title="Lista de abastecimiento" />

    <AdminLayout>
        <PageLayout>
            <template #toolbar>
                <GlobalToolbar v-bind="toolbarConfig" @action="handleToolbarAction" />
            </template>

            <div class="space-y-5">
                <section class="grid min-w-0 gap-5 2xl:grid-cols-[minmax(0,1.15fr)_minmax(340px,0.85fr)_320px]">
                    <article class="min-w-0 rounded-[28px] border border-secondary bg-background p-5 shadow-sm">
                        <div class="grid gap-3 2xl:grid-cols-[minmax(0,1.3fr)_180px_180px_150px]">
                                <InputField
                                    v-model="report.localFilters.value.search"
                                    label="Buscar productos"
                                    field="toolbar_search"
                                    icon="barcode_scanner"
                                    placeholder="Codigo, codigo alterno, nombre o categoria"
                                />

                                <SelectField
                                    v-model="report.localFilters.value.category_id"
                                    label="Categoria"
                                    field="purchase_report_category"
                                    :options="categoriesDB"
                                    placeholder="Todas"
                                />

                                <SelectField
                                    v-model="report.localFilters.value.stock"
                                    label="Stock"
                                    field="purchase_report_stock"
                                    :options="stockOptions"
                                    placeholder="Todo"
                                />

                                <SelectField
                                    v-model="report.localFilters.value.per_page"
                                    label="Registros"
                                    field="purchase_report_per_page"
                                    :options="perPageOptions"
                                    placeholder="25 por pagina"
                                />
                        </div>

                        <div class="mt-4">
                            <GlobalTable
                                :items="tableRows"
                                :pagination="pagination"
                                :loading="false"
                                v-bind="tableConfig"
                                @action="handleTableAction"
                                @row-click="handleTableRowClick"
                                @page-change="handlePageChange"
                            />
                        </div>
                    </article>

                    <article class="min-w-0 rounded-[28px] border border-secondary bg-background p-5 shadow-sm">
                        <div class="rounded-2xl border border-secondary bg-secondary px-4 py-3">
                            <div>
                                <p class="text-sm font-semibold text-text">
                                    {{ report.isEditing.value ? `Productos de ${report.editingOrder.value?.folio}` : "Productos de la compra actual" }}
                                </p>
                                <p class="text-xs text-text opacity-70">
                                    Aqui defines cuantas piezas vas a pedir antes de guardar el borrador.
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 max-h-[calc(100vh-22rem)] space-y-3 overflow-y-auto pr-1">
                            <article
                                v-for="item in selectedProducts"
                                :key="item.branch_product_id"
                                class="rounded-2xl border border-secondary bg-secondary px-3 py-3"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-bold text-text">{{ item.name }}</p>
                                        <p class="truncate text-xs text-text opacity-60">{{ item.code || "Sin codigo" }}</p>
                                    </div>

                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-secondary bg-background text-text transition hover:border-primary hover:text-primary"
                                        title="Eliminar producto"
                                        @click="report.removeItem(item.branch_product_id)"
                                    >
                                        <span class="material-symbols-outlined text-[17px]">close</span>
                                    </button>
                                </div>

                                <div class="mt-2 flex items-center gap-3">
                                    <span class="hidden text-[10px] font-semibold uppercase tracking-[0.1em] text-text opacity-50 sm:inline">Pzas.</span>
                                    <QuantityStepper
                                        :value="item.requested_quantity"
                                        :decrease-disabled="Number(item.requested_quantity || 0) <= 1"
                                        @decrease="report.decreaseQuantity(item.branch_product_id)"
                                        @increase="report.increaseQuantity(item.branch_product_id)"
                                    />

                                    <p class="min-w-0 truncate text-xs text-text opacity-70">
                                        Unit. <strong class="text-text">{{ formatMoney(item.price) }}</strong>
                                    </p>

                                    <p class="ml-auto min-w-0 truncate text-right text-xs text-text opacity-70">
                                        Importe <strong class="text-text">{{ formatMoney(Number(item.price || 0) * Number(item.requested_quantity || 0)) }}</strong>
                                    </p>
                                </div>
                            </article>

                            <EmptyStateCard
                                v-if="selectedProducts.length === 0"
                                title="Todavia no hay productos en la lista"
                                description="Usa la tabla de la izquierda para ir agregando productos a la compra."
                                icon="shopping_cart"
                                min-height-class="min-h-[360px]"
                                tone="white"
                            />
                        </div>
                    </article>

                    <aside class="flex min-h-0 min-w-0 flex-col rounded-[28px] border border-secondary bg-background p-5 shadow-sm">
                        <SectionHeading
                            title="Compra actual"
                            description="Resumen y acciones de la lista."
                            spacing="sm"
                        />

                        <div class="mt-5 space-y-4">
                            <div class="grid grid-cols-2 gap-3">
                                <MetricCard
                                    label="Articulos"
                                    :value="report.selectedCount.value"
                                />

                                <MetricCard
                                    label="Piezas"
                                    :value="report.totalQuantity.value"
                                />
                            </div>

                            <TextareaField
                                v-model="report.notes.value"
                                label="Notas generales"
                                field="purchase_report_notes"
                                placeholder="Observaciones generales para la compra"
                                :rows="3"
                            />

                            <MetricCard
                                label="Aproximado a pagar"
                                :value="formatMoney(report.estimatedTotal.value)"
                                tone="dark"
                                size="lg"
                            />
                        </div>

                        <div class="mt-auto space-y-3 pt-6">
                            <AppButton
                                block
                                :disabled="report.selectedCount.value === 0"
                                @click="report.saveDraft"
                            >
                                {{ report.isEditing.value ? "Guardar cambios" : "Guardar borrador" }}
                            </AppButton>
                        </div>
                    </aside>
                </section>

                <PurchaseReportDrafts
                    :reports="reportsDB"
                    @open="openDraft"
                />
            </div>

            <PurchaseReportDraftModal
                v-if="selectedDraft"
                :report="selectedDraft"
                :branch-name="branchLabel"
                @close="closeDraft"
                @edit="editDraft"
            />
        </PageLayout>
    </AdminLayout>
</template>
