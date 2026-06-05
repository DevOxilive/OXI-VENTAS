import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useBatchAdjustmentModal } from "@/Composables/Inventory/useBatchAdjustmentModal";
import { router } from "@inertiajs/vue3";

export function useBranchInventory(props) {
    const showCreateModal = ref(false);
    const showEntryModal = ref(false);
    const showExitModal = ref(false);
    const showMovementsModal = ref(false);
    const showConfigModal = ref(false);

    const selectedMovementProduct = ref(null);
    const selectedMovementsProduct = ref(null);
    const selectedConfigProduct = ref(null);
    const selectedAlertType = ref(null);

    const search = ref(props.filters?.search ?? "");
    const categoryFilter = ref(props.filters?.category ?? "");
    const subcategoryFilter = ref(props.filters?.subcategory ?? "");
    const stockFilter = ref(props.filters?.stock ?? "");
    const statusFilter = ref(props.filters?.status ?? "");
    const expirationStatusFilter = ref(props.filters?.expiration_status ?? "");
    const inactiveCandidateFilter = ref(
        props.filters?.inactive_candidate ?? "",
    );
    const recordsToShow = ref(Number(props.filters?.per_page ?? 50));

    const realtimeUpdates = ref({});
    let searchTimeout = null;

    const products = computed(() => props.productsDB ?? []);
    const branches = computed(() => props.branchesDB ?? []);
    const categories = computed(() => props.categoriesDB ?? []);
    const subcategories = computed(() => props.subcategoriesDB ?? []);
    const currentBranch = computed(() => props.currentBranch ?? null);

    const inventoryAlerts = computed(() => props.inventoryAlerts ?? {});

    const rawBranchProducts = computed(() => {
        if (Array.isArray(props.branchProductsDB)) {
            return props.branchProductsDB;
        }

        return props.branchProductsDB?.data ?? [];
    });

    const paginationLinks = computed(() => {
        return Array.isArray(props.branchProductsDB?.links)
            ? props.branchProductsDB.links
            : [];
    });

    const hasPagination = computed(() => {
        return (
            !Array.isArray(props.branchProductsDB) &&
            paginationLinks.value.length > 0
        );
    });

    const visualProducts = computed(() => {
        return rawBranchProducts.value.map((item) => {
            const realtime = realtimeUpdates.value[item.id] ?? {};

            const stock = Number(realtime.stock ?? item.stock ?? 0);
            const minStock = Number(item.min_stock ?? 0);
            const price = Number(item.price ?? item.product?.sale_price ?? 0);
            const unit = item.product?.unit ?? "pieza";

            const tracksBatches = Boolean(
                item.tracks_batches ?? item.tracksBatches ?? false,
            );

            let operationalStatus = "Disponible";

            if (stock <= 0) {
                operationalStatus = "Agotado";
            } else if (stock <= minStock) {
                operationalStatus = "Stock bajo";
            }

            return {
                id: item.id,
                name:
                    item.product?.name ||
                    item.name ||
                    item.barcode ||
                    "Producto sin nombre",
                code:
                    item.product?.barcodes?.[0]?.code ??
                    item.barcode ??
                    `BP-${item.id}`,
                category: item.product?.category?.name ?? "Sin categoría",
                branch:
                    item.branch?.name ??
                    currentBranch.value?.name ??
                    "Sucursal",
                status: operationalStatus,
                administrativeStatus: item.status ?? "active",
                stock,
                minStock,
                unit,
                stockLabel: `${stock} ${unit}`,
                minStockLabel: `${minStock} ${unit}`,
                price,
                tracksBatches,
                expirationDate:
                    item.next_expiration_date ?? item.expiration_date ?? null,
                lastRestockedAt: item.last_restocked_at ?? null,
                inactiveCandidateAfterDays:
                    item.inactive_candidate_after_days ?? 90,
                activeBatchesCount: item.active_batches_count ?? 0,
                batches: realtime.batches ?? item.batches ?? [],
                recentMovements: realtime.movements ?? item.movements ?? [],
                raw: item,
            };
        });
    });

    const {
        showBatchAdjustmentModal,
        liveSelectedBatch,
        processing: batchAdjustmentProcessing,
        usesLot: batchAdjustmentUsesLot,
        form: batchAdjustmentForm,
        frontendErrors: batchAdjustmentErrors,
        totalErrors: batchAdjustmentTotalErrors,
        isSeasonal: batchAdjustmentIsSeasonal,
        calculatedQuantity: batchAdjustmentCalculatedQuantity,
        adjustmentText: batchAdjustmentText,
        quantityResultColor: batchAdjustmentQuantityResultColor,
        adjustBatch,
        closeBatchAdjustmentModal,
        toggleLot: toggleBatchAdjustmentLot,
        setAdjustmentType: setBatchAdjustmentType,
        validateField: validateBatchAdjustmentField,
        saveEditedBatch,
    } = useBatchAdjustmentModal(visualProducts);

    const showProductBatchesModal = ref(false);
    const selectedBatchesProductId = ref(null);

    const liveSelectedBatchesProduct = computed(() => {
        if (!selectedBatchesProductId.value) return null;

        return (
            visualProducts.value.find((product) => {
                return product.id === selectedBatchesProductId.value;
            }) ?? null
        );
    });

    function openProductBatchesModal(product) {
        selectedBatchesProductId.value = product.id;
        showProductBatchesModal.value = true;
    }

    function closeProductBatchesModal() {
        showProductBatchesModal.value = false;
        selectedBatchesProductId.value = null;
    }

    function openBatchAdjustmentFromList(batch) {
        closeProductBatchesModal();
        adjustBatch(batch);
    }

    const liveSelectedMovementProduct = computed(() => {
        if (!selectedMovementProduct.value) return null;

        return (
            visualProducts.value.find((product) => {
                return product.id === selectedMovementProduct.value.id;
            }) ?? selectedMovementProduct.value
        );
    });

    const liveSelectedMovementsProduct = computed(() => {
        if (!selectedMovementsProduct.value) return null;

        return (
            visualProducts.value.find((product) => {
                return product.id === selectedMovementsProduct.value.id;
            }) ?? selectedMovementsProduct.value
        );
    });

    const liveSelectedConfigProduct = computed(() => {
        if (!selectedConfigProduct.value) return null;

        return (
            visualProducts.value.find((product) => {
                return product.id === selectedConfigProduct.value.id;
            }) ?? selectedConfigProduct.value
        );
    });

    const filteredProducts = computed(() => visualProducts.value);

    const stats = computed(() => {
        const inventoryStats = props.inventoryStats ?? {};

        return {
            total: Number(inventoryStats.total_products ?? 0),
            totalStock: Number(inventoryStats.total_stock ?? 0),
            inventoryValue: Number(inventoryStats.inventory_value ?? 0),
            lowStock: Number(inventoryStats.low_stock ?? 0),
            outOfStock: Number(inventoryStats.out_of_stock ?? 0),
            expiringSoon: Number(inventoryStats.expiring_soon ?? 0),
            inactiveCandidates: Number(inventoryStats.inactive_candidates ?? 0),
        };
    });

    const expiredBatchesList = computed(() => {
        return inventoryAlerts.value.expired_batches_list ?? [];
    });

    const nearExpirationBatchesList = computed(() => {
        return inventoryAlerts.value.near_expiration_batches_list ?? [];
    });

    const lowStockProductsList = computed(() => {
        return inventoryAlerts.value.low_stock_products_list ?? [];
    });

    const inactiveCandidateProductsList = computed(() => {
        return inventoryAlerts.value.inactive_candidate_products_list ?? [];
    });

    const alerts = computed(() => ({
        expiredBatches:
            inventoryAlerts.value.expired_batches ??
            expiredBatchesList.value.length,

        nearExpirationBatches:
            inventoryAlerts.value.near_expiration_batches ??
            nearExpirationBatchesList.value.length,

        lowStockProducts:
            inventoryAlerts.value.low_stock_products ??
            lowStockProductsList.value.length,

        inactiveCandidateProducts:
            inventoryAlerts.value.inactive_candidate_products ??
            inactiveCandidateProductsList.value.length,

        expiredBatchesList: expiredBatchesList.value,
        nearExpirationBatchesList: nearExpirationBatchesList.value,
        lowStockProductsList: lowStockProductsList.value,
        inactiveCandidateProductsList: inactiveCandidateProductsList.value,
    }));

    const selectedAlertTitle = computed(() => {
        return (
            {
                expired: "Lotes vencidos",
                nearExpiration: "Lotes próximos a vencer",
                lowStock: "Productos con stock bajo",
                inactiveCandidates: "Productos candidatos a inactivar",
            }[selectedAlertType.value] || ""
        );
    });

    const selectedAlertBatches = computed(() => {
        return (
            {
                expired: expiredBatchesList.value,
                nearExpiration: nearExpirationBatchesList.value,
                lowStock: lowStockProductsList.value,
                inactiveCandidates: inactiveCandidateProductsList.value,
            }[selectedAlertType.value] || []
        );
    });

    const showAlertModal = computed(() => Boolean(selectedAlertType.value));

    const reloadInventory = () => {
        router.get(
            window.location.pathname,
            {
                search: search.value || undefined,
                subcategory: subcategoryFilter.value || undefined,
                category: categoryFilter.value || undefined,
                stock: stockFilter.value || undefined,
                status: statusFilter.value || undefined,
                expiration_status: expirationStatusFilter.value || undefined,
                inactive_candidate: inactiveCandidateFilter.value || undefined,
                per_page: recordsToShow.value,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const goToPage = (url) => {
        if (!url) return;

        router.visit(url, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const openCreateModal = () => {
        showCreateModal.value = true;
    };

    const closeCreateModal = () => {
        showCreateModal.value = false;
    };

    const openAlertModal = (type) => {
        selectedAlertType.value = type;
    };

    const closeAlertModal = () => {
        selectedAlertType.value = null;
    };

    const openEntryModal = (product) => {
        selectedMovementProduct.value = product;
        showEntryModal.value = true;
    };

    const closeEntryModal = () => {
        showEntryModal.value = false;
        selectedMovementProduct.value = null;
    };

    const openExitModal = (product) => {
        selectedMovementProduct.value = product;
        showExitModal.value = true;
    };

    const closeExitModal = () => {
        showExitModal.value = false;
        selectedMovementProduct.value = null;
    };

    const openMovementsModal = (product) => {
        selectedMovementsProduct.value = product;
        showMovementsModal.value = true;
    };

    const closeMovementsModal = () => {
        showMovementsModal.value = false;
        selectedMovementsProduct.value = null;
    };

    const editProduct = (product) => {
        selectedConfigProduct.value = product;
        showConfigModal.value = true;
    };

    const closeConfigModal = () => {
        showConfigModal.value = false;
        selectedConfigProduct.value = null;
    };

    const exportExcel = () => {
        console.log("Exportar inventario", visualProducts.value);
    };

    const viewProduct = (product) => {
        console.log("Ver producto", product);
    };

    const deleteProduct = (product) => {
        console.log("Eliminar producto", product);
    };

    watch(search, () => {
        clearTimeout(searchTimeout);

        searchTimeout = setTimeout(() => {
            reloadInventory();
        }, 400);
    });

    watch(recordsToShow, reloadInventory);
    watch(categoryFilter, reloadInventory);
    watch(subcategoryFilter, reloadInventory);
    watch(stockFilter, reloadInventory);
    watch(statusFilter, reloadInventory);
    watch(expirationStatusFilter, reloadInventory);
    watch(inactiveCandidateFilter, reloadInventory);

    onMounted(() => {
        if (!window.Echo || !currentBranch.value?.id) return;

        window.Echo.channel(
            `inventory.branch.${currentBranch.value.id}`,
        ).listen(".stock.updated", (event) => {
            const branchProductId =
                event.branch_product_id ??
                event.branchProduct?.id ??
                event.branch_product?.id;

            if (!branchProductId) return;

            const currentProduct = visualProducts.value.find((product) => {
                return product.id === branchProductId;
            });

            realtimeUpdates.value = {
                ...realtimeUpdates.value,

                [branchProductId]: {
                    stock: Number(
                        event.stock ??
                            event.branchProduct?.stock ??
                            event.branch_product?.stock ??
                            currentProduct?.stock ??
                            0,
                    ),

                    updated_at: event.updated_at ?? new Date().toISOString(),

                    batches:
                        event.batches ??
                        event.branchProduct?.batches ??
                        event.branch_product?.batches ??
                        currentProduct?.batches ??
                        [],

                    movements:
                        event.recent_movements ??
                        event.movements ??
                        event.branchProduct?.movements ??
                        event.branch_product?.movements ??
                        currentProduct?.recentMovements ??
                        [],
                },
            };
        });
    });

    onBeforeUnmount(() => {
        clearTimeout(searchTimeout);

        if (window.Echo && currentBranch.value?.id) {
            window.Echo.leave(`inventory.branch.${currentBranch.value.id}`);
        }
    });

    return {
        products,
        branches,
        categories,
        subcategories,
        currentBranch,

        showCreateModal,
        showEntryModal,
        showExitModal,
        showMovementsModal,
        showConfigModal,

        selectedMovementProduct,
        selectedMovementsProduct,
        selectedConfigProduct,

        liveSelectedMovementProduct,
        liveSelectedMovementsProduct,
        liveSelectedConfigProduct,

        search,
        categoryFilter,
        subcategoryFilter,
        stockFilter,
        statusFilter,
        expirationStatusFilter,
        inactiveCandidateFilter,
        recordsToShow,

        paginationLinks,
        hasPagination,

        visualProducts,
        filteredProducts,

        stats,
        alerts,

        selectedAlertType,
        selectedAlertTitle,
        selectedAlertBatches,
        showAlertModal,

        openCreateModal,
        closeCreateModal,

        openAlertModal,
        closeAlertModal,

        openEntryModal,
        closeEntryModal,

        openExitModal,
        closeExitModal,

        openMovementsModal,
        closeMovementsModal,

        editProduct,
        closeConfigModal,

        exportExcel,
        viewProduct,
        deleteProduct,

        reloadInventory,
        goToPage,

        showProductBatchesModal,
        liveSelectedBatchesProduct,
        openProductBatchesModal,
        closeProductBatchesModal,
        openBatchAdjustmentFromList,

        showBatchAdjustmentModal,
        liveSelectedBatch,

        adjustBatch,
        closeBatchAdjustmentModal,

        batchAdjustmentProcessing,
        batchAdjustmentUsesLot,
        batchAdjustmentForm,
        batchAdjustmentErrors,
        batchAdjustmentTotalErrors,
        batchAdjustmentIsSeasonal,
        batchAdjustmentCalculatedQuantity,
        batchAdjustmentText,
        batchAdjustmentQuantityResultColor,

        toggleBatchAdjustmentLot,
        setBatchAdjustmentType,
        validateBatchAdjustmentField,
        saveEditedBatch,
    };
}
