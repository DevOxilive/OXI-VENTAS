<script setup>
import { computed } from "vue";
import { Head, router } from "@inertiajs/vue3";

import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import GlobalToolbar from "@/Components/Toolbars/GlobalToolbar.vue";
import GlobalTable from "@/Components/Tables/GlobalTable.vue";
import AppButton from "@/Components/Buttons/AppButton.vue";
import InputField from "@/Components/Forms/InputField.vue";
import SelectField from "@/Components/Forms/SelectField.vue";
import QuantityStepper from "@/Components/Forms/QuantityStepper.vue";
import EmptyStateCard from "@/Components/Cards/EmptyStateCard.vue";
import SectionHeading from "@/Components/Cards/SectionHeading.vue";
import TextareaField from "@/Components/Forms/TextareaField.vue";
import { confirmModalAction, getModalRequestOptions } from "@/Components/Modales/useModalConfig";
import { useGlobalTablePagination } from "@/Composables/useGlobalTablePagination";
import { usePurchaseReport } from "@/Composables/Inventory/usePurchaseReport";
import { usePermissions } from "@/Composables/usePermissions";
import { getPurchaseReportProductsTableConfig } from "@/config/TableConfigs/purchaseReportProductsTableConfig";
import { getPurchaseReportToolbarConfig } from "@/config/ToolbarConfigs/purchaseReportToolbarConfig";

import PurchaseReportDrafts from "@/Components/Inventory/PurchaseReports/PurchaseReportDrafts.vue";

const props = defineProps({
    currentBranch: Object,
    productsDB: Object,
    filters: Object,
    categoriesDB: Array,
    reportsDB: {
        type: Object,
        default: () => ({}),
    },
    purchaseCycle: {
        type: Object,
        default: () => ({}),
    },
});

const report = usePurchaseReport(props);
const { handlePageChange } = useGlobalTablePagination();
const { can } = usePermissions();

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
const cycleSubmitted = computed(() => Boolean(props.purchaseCycle?.submitted));
const toolbarConfig = computed(() => getPurchaseReportToolbarConfig({
    branchName: branchLabel.value,
    editingFolio: report.editingOrder.value?.folio || "",
    cycleFolio: props.purchaseCycle?.folio || "",
    cycleSubmitted: cycleSubmitted.value,
    hasProducts: report.selectedCount.value > 0,
}));
const tableRows = computed(() => report.tableRows.value);
const pagination = computed(() => report.paginator.value);
const selectedProducts = computed(() => report.selectedProducts.value);
const purchaseLists = computed(() => props.reportsDB?.data ?? []);

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

async function openDraft(draft) {
    if (Number(report.editingOrder.value?.id) === Number(draft?.id)) return;

    if (report.selectedCount.value > 0) {
        const result = await confirmModalAction({
            mode: "update",
            entityName: "borrador",
            title: "Abrir borrador",
            message: "Ya tienes productos en la lista actual. Al abrir este borrador serán reemplazados por sus productos. ¿Deseas continuar?",
            confirmText: "Abrir borrador",
            cancelText: "Conservar lista",
        });

        if (!result.isConfirmed) return;
    }

    report.editDraft(draft);
}

function handleToolbarAction(action) {
    if (action === "clear") {
        report.clearWorkspace();
    }

    if (action === "submit-empty") submitWithoutProducts();
}

async function submitWithoutProducts() {
    const result = await confirmModalAction({
        mode: "create",
        entityName: "solicitud",
        title: "Confirmar sin productos",
        message: `Se registrará que ${branchLabel.value} no necesita productos en ${props.purchaseCycle?.folio || "el ciclo actual"}.`,
        confirmText: "Confirmar solicitud",
    });

    if (!result.isConfirmed) return;

    router.post(
        route("inventory.branches.purchase-reports.submit-empty", {
            branch: props.currentBranch.id,
        }),
        {},
        getModalRequestOptions({
            mode: "create",
            entityName: "Solicitud",
            successTitle: "Solicitud registrada correctamente",
            errorTitle: "No se pudo registrar la solicitud",
            errorMessage: "Actualiza la página y revisa el ciclo de compra actual.",
        }),
    );
}

async function generateOrder() {
    const result = await confirmModalAction({
        mode: "create",
        entityName: "orden de compra",
        title: "Generar orden de compra",
        message: "La lista dejará de ser un borrador y pasará al seguimiento de órdenes de compra. ¿Deseas continuar?",
        confirmText: "Generar orden",
        cancelText: "Cancelar",
    });

    if (result.isConfirmed) report.generateOrder();
}

async function deleteDraft(draft) {
    const result = await confirmModalAction({
        mode: "delete",
        entityName: "borrador",
        title: "Eliminar borrador",
        message: `Se eliminará ${draft.folio || `la lista #${draft.id}`} y sus productos. Esta acción no se puede deshacer.`,
        confirmText: "Eliminar borrador",
    });

    if (!result.isConfirmed) return;

    router.delete(
        route("inventory.branches.purchase-reports.destroy", {
            branch: props.currentBranch.id,
            purchaseReport: draft.id,
        }),
        getModalRequestOptions({
            mode: "delete",
            entityName: "Borrador",
            successTitle: "Borrador eliminado correctamente",
            errorTitle: "No se pudo eliminar el borrador",
            errorMessage: "Actualiza la página y vuelve a intentarlo.",
            onSuccess: () => {
                if (Number(report.editingOrder.value?.id) === Number(draft.id)) {
                    report.clearDraft();
                }
            },
        }),
    );
}

function paginateLists(url) {
    if (!url) return;

    router.get(url, {}, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

</script>

<template>
    <Head title="Generar lista de compra" />

    <AdminLayout>
        <PageLayout>
            <template #toolbar>
                <GlobalToolbar
                    v-bind="toolbarConfig"
                    @action="handleToolbarAction"
                />
            </template>

            <div>
                <section class="grid min-w-0 gap-5 xl:grid-cols-[280px_minmax(0,1.15fr)_minmax(340px,0.85fr)]">
                    <PurchaseReportDrafts
                        :reports="purchaseLists"
                        :pagination="reportsDB"
                        :active-draft-id="report.editingOrder.value?.id"
                        :can-delete="can('inventory.purchase-reports.delete')"
                        @open="openDraft"
                        @delete="deleteDraft"
                        @paginate="paginateLists"
                    />

                    <article class="min-w-0 rounded-[28px] border border-secondary bg-background p-5 shadow-sm">
                        <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-[minmax(0,1.3fr)_180px_180px_150px]">
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

                    <article class="flex min-h-0 min-w-0 flex-col rounded-[28px] border border-secondary bg-background p-5 shadow-sm">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <SectionHeading
                                :title="report.isEditing.value ? `Productos de ${report.editingOrder.value?.folio}` : 'Productos de la lista'"
                                description="Define las piezas necesarias antes de guardar la lista."
                                spacing="sm"
                            />

                            <div class="flex shrink-0 gap-2 text-xs font-semibold text-text">
                                <span class="rounded-full border border-secondary bg-secondary px-3 py-1.5">
                                    {{ report.selectedCount.value }} {{ report.selectedCount.value === 1 ? "producto" : "productos" }}
                                </span>
                                <span class="rounded-full border border-secondary bg-secondary px-3 py-1.5">
                                    {{ report.totalQuantity.value }} {{ report.totalQuantity.value === 1 ? "pieza" : "piezas" }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 max-h-[calc(100vh-27rem)] min-h-[260px] space-y-3 overflow-y-auto pr-1">
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
                                </div>
                            </article>

                            <EmptyStateCard
                                v-if="selectedProducts.length === 0"
                                title="Todavia no hay productos en la lista"
                                description="Usa la tabla de productos para agregar lo que necesitas solicitar."
                                icon="shopping_cart"
                                min-height-class="min-h-[260px]"
                                tone="white"
                            />
                        </div>

                        <div class="mt-4 space-y-4 border-t border-secondary pt-4">
                            <TextareaField
                                v-model="report.notes.value"
                                label="Notas generales"
                                field="purchase_report_notes"
                                placeholder="Observaciones generales para la lista"
                                :rows="2"
                            />

                            <div class="grid gap-3 sm:grid-cols-2">
                                <AppButton
                                    block
                                    :disabled="report.selectedCount.value === 0"
                                    variant="secondary"
                                    @click="report.saveDraft"
                                >
                                    {{ report.isEditing.value ? "Actualizar lista" : "Guardar lista" }}
                                </AppButton>

                                <AppButton
                                    block
                                    :disabled="report.selectedCount.value === 0"
                                    @click="generateOrder"
                                >
                                    Generar orden de compra
                                </AppButton>
                            </div>
                        </div>
                    </article>
                </section>
            </div>

        </PageLayout>
    </AdminLayout>
</template>
