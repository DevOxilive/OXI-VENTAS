<script setup>
import { computed, onBeforeUnmount, onMounted } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";

import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import GlobalToolbar from "@/Components/Toolbars/GlobalToolbar.vue";
import GlobalTable from "@/Components/Tables/GlobalTable.vue";
import AppButton from "@/Components/Buttons/AppButton.vue";
import ActionIconButton from "@/Components/Forms/ActionIconButton.vue";
import QuantityStepper from "@/Components/Forms/QuantityStepper.vue";
import PresentationSwitch from "@/Components/Inventory/PresentationSwitch.vue";
import GlobalCard from "@/Components/Cards/GlobalCard.vue";
import { confirmModalAction, getModalRequestOptions } from "@/Components/Modales/useModalConfig";
import { useGlobalTablePagination } from "@/Composables/useGlobalTablePagination";
import { usePurchaseReport } from "@/Composables/Inventory/usePurchaseReport";
import { usePermissions } from "@/Composables/usePermissions";
import { getPurchaseReportProductsTableConfig } from "@/config/TableConfigs/purchaseReportProductsTableConfig";
import { getPurchaseReportToolbarConfig } from "@/config/ToolbarConfigs/purchaseReportToolbarConfig";

import PurchaseReportDrafts from "@/Components/Inventory/PurchaseReports/PurchaseReportDrafts.vue";
import { REALTIME_CHANNELS, REALTIME_EVENTS, refreshRealtimeProps, subscribeRealtime } from '@/realtime'

const page = usePage()
const props = defineProps({
    selectorMode: {
        type: Boolean,
        default: false,
    },
    currentBranch: Object,
    branchesDB: {
        type: Array,
        default: () => [],
    },
    productsDB: Object,
    filters: Object,
    productDepartmentsDB: {
        type: Array,
        default: () => [],
    },
    categoriesDB: Array,
    reportsDB: {
        type: Object,
        default: () => ({}),
    },
});

const report = usePurchaseReport(props);
const { handlePageChange } = useGlobalTablePagination();
const { can } = usePermissions();

const stockOptions = [
    { label: "Todo el stock", value: "" },
    { label: "Stock bajo", value: "LOW" },
    { label: "Agotados", value: "OUT" },
];
const branchLabel = computed(() => props.currentBranch?.name || "Sin sucursal");
const canCreatePurchaseReport = computed(() => can("sales.purchase-lists.create"));
const canUpdatePurchaseReport = computed(() => can("sales.purchase-lists.update"));
const canSavePurchaseReport = computed(() => report.isEditing.value
    ? canUpdatePurchaseReport.value
    : canCreatePurchaseReport.value);
const canWorkOnPurchaseList = computed(() => canCreatePurchaseReport.value || canUpdatePurchaseReport.value);
const tableConfig = computed(() => getPurchaseReportProductsTableConfig({
    canManage: canSavePurchaseReport.value,
}));
const canGeneratePurchaseOrder = computed(() => canSavePurchaseReport.value);
const selectedDepartmentIds = computed(() => new Set((report.localFilters.value.departmentIds || []).map(String)));
const toolbarCategories = computed(() => {
    if (selectedDepartmentIds.value.size === 0) return props.categoriesDB ?? [];

    return (props.categoriesDB ?? []).filter((category) =>
        selectedDepartmentIds.value.has(String(category.product_department_id))
    );
});
const toolbarConfig = computed(() => props.selectorMode
    ? {
        title: "Mi lista de compra",
        subtitle: "Selecciona la sucursal para preparar su lista de compra.",
        showSearch: false,
        showRecordsPerPage: false,
        showCounter: false,
        filters: [],
        actions: [],
        tabs: [],
    }
    : getPurchaseReportToolbarConfig({
        branchName: branchLabel.value,
        filters: report.localFilters.value,
        departments: props.productDepartmentsDB ?? [],
        categories: toolbarCategories.value,
        stockOptions,
        perPage: report.localFilters.value.per_page,
        total: Number(pagination.value?.total ?? 0),
        search: report.localFilters.value.search,
    }));
const tableRows = computed(() => report.tableRows.value);
const pagination = computed(() => report.paginator.value);
const selectedProducts = computed(() => report.selectedProducts.value);
const purchaseLists = computed(() => props.reportsDB?.data ?? []);
let unsubscribeTrashRealtime = null

function itemUnitLabel(item) {
    if (item.presentation === "box") return "cajas";
    if (item.presentation === "kilo") return "kg";

    return "piezas";
}

onMounted(() => {
    unsubscribeTrashRealtime = subscribeRealtime(
        REALTIME_CHANNELS.systems,
        REALTIME_EVENTS.systemTrashChanged,
        (event) => {
            if (event?.resource === 'purchase-reports') {
                refreshRealtimeProps(page, ['reportsDB'])
            }
        },
    )
})

onBeforeUnmount(() => {
    unsubscribeTrashRealtime?.()
})

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
    if (!canSavePurchaseReport.value) return;

    if (row.in_purchase_list) {
        report.removeItem(row.id);
        return;
    }

    report.addProduct(row);
}

function handleToolbarFilter({ key, value }) {
    if (key === "departmentIds") {
        const departmentIds = (value || []).map(String);
        const validCategoryIds = new Set((props.categoriesDB ?? [])
            .filter((category) => departmentIds.length === 0
                || departmentIds.includes(String(category.product_department_id)))
            .map((category) => String(category.id)));
        report.localFilters.value.departmentIds = value || [];
        report.localFilters.value.categoryIds = (report.localFilters.value.categoryIds || [])
            .filter((categoryId) => validCategoryIds.has(String(categoryId)));
        return;
    }

    if (key === "categoryIds") {
        report.localFilters.value.categoryIds = value || [];
        return;
    }

    if (key === "stock") {
        report.localFilters.value.stock = value || "";
    }
}

function handleSearch(value) {
    report.localFilters.value.search = value || "";
}

async function openDraft(draft) {
    if (!canUpdatePurchaseReport.value) return;

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

async function generateOrder() {
    if (!canGeneratePurchaseOrder.value) return;

    const result = await confirmModalAction({
        mode: "create",
        entityName: "solicitud de compra",
        title: "Enviar a Compras",
        message: "La lista se enviará a Compras para su revisión. Después podrás solicitar una corrección, pero ya no editarla desde la sucursal. ¿Deseas continuar?",
        confirmText: "Enviar a Compras",
        cancelText: "Cancelar",
    });

    if (result.isConfirmed) report.generateOrder();
}

async function deleteDraft(draft) {
    if (!can("sales.purchase-lists.delete")) return;

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
        {
            ...getModalRequestOptions({
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
            data: {
                record_version: draft.record_version || draft.updated_at || null,
            },
        },
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

function selectBranch(branchId) {
    if (!branchId) return;

    router.get(
        route("ventas.purchase-reports.index"),
        { branch: branchId },
        { preserveScroll: true, replace: true },
    );
}

</script>

<template>
    <Head title="Generar lista de compra" />

    <AdminLayout>
        <PageLayout fill-height>
            <template v-if="selectorMode" #toolbar>
                <GlobalToolbar
                    v-bind="toolbarConfig"
                    @update:search="handleSearch"
                    @update:filter="handleToolbarFilter"
                    @update:records-per-page="report.localFilters.value.per_page = $event"
                />
            </template>

            <div
                v-if="selectorMode"
                class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
            >
                <GlobalCard
                    v-for="branch in branchesDB"
                    :key="branch.id"
                    :title="branch.name"
                    subtitle="Lista de compra"
                    description="Genera la solicitud con los productos que necesita esta sucursal."
                    icon="shopping_cart"
                    badge="Seleccionar"
                    @click="selectBranch(branch.id)"
                />
            </div>

            <div v-else class="block min-w-0 overflow-visible lg:grid lg:min-h-0 lg:flex-1 lg:gap-3 lg:overflow-hidden lg:grid-cols-[minmax(0,1fr)_20rem]">
                <div class="flex min-w-0 flex-col overflow-visible lg:min-h-0 lg:overflow-hidden">
                    <GlobalToolbar
                        class="mb-3 shrink-0"
                        v-bind="toolbarConfig"
                        @update:search="handleSearch"
                        @update:filter="handleToolbarFilter"
                        @update:records-per-page="report.localFilters.value.per_page = $event"
                    />

                <PurchaseReportDrafts
                    v-if="purchaseLists.length"
                    class="mb-3 shrink-0"
                    :reports="purchaseLists"
                    :pagination="reportsDB"
                    @edit="openDraft"
                    @delete="deleteDraft"
                    @paginate="paginateLists"
                />

                    <article
                        v-if="canWorkOnPurchaseList"
                        class="flex min-w-0 flex-none flex-col rounded-2xl border border-secondary bg-background p-3 shadow-sm lg:min-h-0 lg:flex-1"
                    >
                        <div>
                            <GlobalTable
                                :items="tableRows"
                                :pagination="pagination"
                                :loading="false"
                                table-max-height-class="max-h-[calc(100dvh-31rem)] overflow-auto"
                                v-bind="tableConfig"
                                @action="handleTableAction"
                                @row-click="handleTableRowClick"
                                @page-change="handlePageChange"
                            />
                        </div>
                    </article>
                </div>

                <article
                        v-if="canWorkOnPurchaseList"
                        class="mt-3 flex min-w-0 flex-none flex-col rounded-2xl border border-secondary bg-background p-3 shadow-sm lg:mt-0 lg:min-h-0 lg:flex-1"
                    >
                        <div class="flex items-center justify-between gap-3 border-b border-secondary pb-3">
                            <h2 class="text-sm font-black text-text">
                                Resumen de compra
                            </h2>

                            <div class="shrink-0 rounded-full bg-secondary px-2.5 py-1 text-xs font-bold text-text">
                                {{ report.selectedCount.value }}
                            </div>
                        </div>

                        <div
                            v-if="selectedProducts.length"
                            class="mt-3 space-y-2 overflow-visible pr-1 lg:min-h-0 lg:flex-1 lg:overflow-y-auto"
                        >
                            <article
                                v-for="item in selectedProducts"
                                :key="item.branch_product_id"
                                class="rounded-xl border border-secondary bg-secondary p-2.5"
                            >
                                <div class="flex items-start gap-2">
                                    <span class="material-symbols-outlined mt-0.5 rounded-lg bg-background p-1.5 text-base text-primary">
                                        inventory_2
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <p class="line-clamp-2 text-xs font-bold leading-4 text-text">{{ item.name }}</p>
                                        <p class="mt-0.5 truncate text-[11px] text-text opacity-60">{{ item.code || "Sin código" }}</p>
                                    </div>

                                    <ActionIconButton
                                        v-if="canSavePurchaseReport"
                                        class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-secondary bg-background text-text transition hover:border-primary hover:text-primary"
                                        icon="close"
                                        title="Eliminar producto"
                                        variant="red"
                                        @click="report.removeItem(item.branch_product_id)"
                                    />
                                </div>

                                <div class="mt-2 flex items-center justify-between gap-2 border-t border-secondary pt-2">
                                    <PresentationSwitch
                                        v-if="item.has_box_presentation"
                                        :model-value="item.presentation"
                                        @change="report.setPresentation(item.branch_product_id, $event)"
                                    />
                                    <span
                                        v-else
                                        class="text-[10px] font-semibold uppercase tracking-[0.1em] text-text opacity-50"
                                    >
                                        {{ itemUnitLabel(item) }}
                                    </span>
                                    <QuantityStepper
                                        :value="item.requested_quantity"
                                        :aria-label="`Cantidad solicitada de ${item.name}`"
                                        :allow-decimal="item.presentation === 'kilo'"
                                        :validation-field="item.presentation === 'kilo' ? 'kilogram_quantity' : 'product_piece_quantity'"
                                        :decrease-disabled="Number(item.requested_quantity || 0) <= (item.presentation === 'kilo' ? 0.001 : 1)"
                                        @decrease="report.decreaseQuantity(item.branch_product_id)"
                                        @increase="report.increaseQuantity(item.branch_product_id)"
                                        @update="report.updateItem(item.branch_product_id, 'requested_quantity', $event)"
                                    />
                                </div>

                            </article>

                        </div>

                        <p
                            v-else
                            class="mt-3 rounded-xl border border-dashed border-secondary bg-secondary px-3 py-4 text-center text-xs text-text opacity-70"
                        >
                            Selecciona productos de la tabla.
                        </p>

                        <div class="mt-3 border-t border-secondary pt-3">
                            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
                                <AppButton
                                    v-if="canSavePurchaseReport"
                                    block
                                    :disabled="report.selectedCount.value === 0"
                                    variant="secondary"
                                    @click="report.saveDraft"
                                >
                                    {{ report.isEditing.value ? "Guardar borrador" : "Guardar borrador" }}
                                </AppButton>

                                <AppButton
                                    v-if="canGeneratePurchaseOrder"
                                    block
                                    :disabled="report.selectedCount.value === 0"
                                    @click="generateOrder"
                                >
                                    Enviar a Compras
                                </AppButton>
                            </div>
                        </div>
                </article>
            </div>

        </PageLayout>
    </AdminLayout>
</template>
