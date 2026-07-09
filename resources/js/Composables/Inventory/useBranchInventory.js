import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useBatchAdjustmentModal } from "@/Composables/Inventory/useBatchAdjustmentModal";
import { useGlobalTablePagination } from "@/Composables/useGlobalTablePagination";
import { router } from "@inertiajs/vue3";

function normalizeSearchValue(value) {
    return String(value ?? "")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .trim();
}

function buildProductSearchText(product) {
    const batches = Array.isArray(product.batches) ? product.batches : [];
    const batchText = batches
        .flatMap((batch) => [
            batch?.lot_number,
            batch?.formatted_expiration_date,
            batch?.expiration_date,
        ])
        .filter(Boolean)
        .join(" ");

    return normalizeSearchValue(
        [
            product.name,
            product.code,
            product.category_name,
            product.category,
            product.branch,
            product.status,
            product.administrativeStatus,
            product.stockLabel,
            product.minStockLabel,
            product.unit,
            product.expirationDate,
            product.lastRestockedAt,
            batchText,
        ].join(" "),
    );
}

export function useBranchInventory(props) {
    const { handlePageChange } = useGlobalTablePagination();
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
    const stockFilter = ref(props.filters?.stock ?? "");
    const statusFilter = ref(props.filters?.status ?? "");
    const expirationStatusFilter = ref(props.filters?.expiration_status ?? "");
    const inactiveCandidateFilter = ref(
        props.filters?.inactive_candidate ?? "",
    );
    const recordsToShow = ref(Number(props.filters?.per_page ?? 50));

    const realtimeUpdates = ref({});
    const detailCache = ref({});
    const detailRequests = ref({});
    let searchTimeout = null;

    const products = computed(() => props.productsDB ?? []);
    const branches = computed(() => props.branchesDB ?? []);
    const categories = computed(() => props.categoriesDB ?? []);
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
            const detail = detailCache.value[item.id] ?? {};

            const stock = Number(realtime.stock ?? item.stock ?? 0);
            const minStock = Number(item.min_stock ?? 0);
            const price = Number(item.price ?? item.product?.sale_price ?? 0);
            const unit = detail.product?.unit ?? item.product?.unit ?? "pieza";

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
                category_name: item.product?.category?.name ?? "Sin categoría",
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
                batches:
                    detail.batches ?? item.batches ?? realtime.batches ?? [],
                recentMovements:
                    detail.movements ??
                    item.movements ??
                    realtime.movements ??
                    [],
                raw: {
                    ...item,
                    ...detail,
                },
            };
        });
    });

    const {
        showBatchAdjustmentModal,
        liveSelectedBatch,
        processing: batchAdjustmentProcessing,
        form: batchAdjustmentForm,
        frontendErrors: batchAdjustmentErrors,
        totalErrors: batchAdjustmentTotalErrors,
        isSeasonal: batchAdjustmentIsSeasonal,
        calculatedQuantity: batchAdjustmentCalculatedQuantity,
        adjustmentText: batchAdjustmentText,
        quantityResultColor: batchAdjustmentQuantityResultColor,
        adjustBatch,
        closeBatchAdjustmentModal,
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

    async function openProductBatchesModal(product) {
        const detailedProduct = await ensureProductDetails(product);
        selectedBatchesProductId.value = detailedProduct?.id ?? product.id;
        showProductBatchesModal.value = true;
    }

    function closeProductBatchesModal() {
        showProductBatchesModal.value = false;
        selectedBatchesProductId.value = null;
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

    const filteredProducts = computed(() => {
        const searchValue = normalizeSearchValue(search.value);

        if (!searchValue) {
            return visualProducts.value;
        }

        return visualProducts.value.filter((product) =>
            buildProductSearchText(product).includes(searchValue),
        );
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
                inactiveCandidates: "Productos sin rotación",
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

    const goToPage = handlePageChange;

    function clearProductDetailCache(productId) {
        if (!productId || !detailCache.value[productId]) return;

        const nextCache = { ...detailCache.value };
        delete nextCache[productId];
        detailCache.value = nextCache;
    }

    function shouldRefreshRealtimeDetails(productId) {
        if (!productId) return false;

        return Boolean(
            detailCache.value[productId] ||
                selectedMovementProduct.value?.id === productId ||
                selectedMovementsProduct.value?.id === productId ||
                selectedConfigProduct.value?.id === productId ||
                selectedBatchesProductId.value === productId,
        );
    }

    async function ensureProductDetails(product, options = {}) {
        if (!product?.id) return product;
        const { forceRefresh = false } = options;

        if (forceRefresh) {
            clearProductDetailCache(product.id);
        }

        if (detailCache.value[product.id]) {
            return (
                visualProducts.value.find((item) => item.id === product.id) ??
                product
            );
        }

        if (detailRequests.value[product.id]) {
            await detailRequests.value[product.id];

            return (
                visualProducts.value.find((item) => item.id === product.id) ??
                product
            );
        }

        detailRequests.value[product.id] = window.axios
            .get(
                route("inventory.branch-inventory.details", {
                    branchProduct: product.id,
                }),
            )
            .then(({ data }) => {
                detailCache.value = {
                    ...detailCache.value,
                    [product.id]: data,
                };
            })
            .catch((error) => {
                console.error("No se pudo cargar el detalle del producto", error);
            })
            .finally(() => {
                const requests = { ...detailRequests.value };
                delete requests[product.id];
                detailRequests.value = requests;
            });

        await detailRequests.value[product.id];

        return (
            visualProducts.value.find((item) => item.id === product.id) ??
            product
        );
    }

    async function refreshProductDetails(productId) {
        if (!productId) return null;

        const product =
            visualProducts.value.find((item) => item.id === productId) ??
            selectedMovementsProduct.value ??
            selectedMovementProduct.value ??
            selectedConfigProduct.value ??
            liveSelectedBatchesProduct.value ??
            { id: productId };

        return ensureProductDetails(product, { forceRefresh: true });
    }

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

    const openExitModal = async (product) => {
        selectedMovementProduct.value = await ensureProductDetails(product);
        showExitModal.value = true;
    };

    const closeExitModal = () => {
        showExitModal.value = false;
        selectedMovementProduct.value = null;
    };

    const openMovementsModal = async (product) => {
        selectedMovementsProduct.value = await ensureProductDetails(product);
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
    watch(stockFilter, reloadInventory);
    watch(statusFilter, reloadInventory);
    watch(expirationStatusFilter, reloadInventory);
    watch(inactiveCandidateFilter, reloadInventory);

    onMounted(() => {
        if (!window.Echo || !currentBranch.value?.id) return;

        window.Echo.channel(
            `inventory.branch.${currentBranch.value.id}`,
        )
            .listen(".stock.updated", (event) => {
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

                        batches: currentProduct?.batches ?? [],
                        movements: currentProduct?.recentMovements ?? [],
                    },
                };

                const shouldRefreshDetails =
                    shouldRefreshRealtimeDetails(branchProductId);

                router.reload({
                    only: ["branchProductsDB", "inventoryStats", "inventoryAlerts"],
                    preserveScroll: true,
                    preserveState: true,

                    onSuccess: async () => {
                        if (shouldRefreshDetails) {
                            await refreshProductDetails(branchProductId);
                        }

                        if (!selectedMovementsProduct.value) return;

                        const updatedProduct = visualProducts.value.find(
                            (product) => {
                                return (
                                    product.id === selectedMovementsProduct.value.id
                                );
                            },
                        );

                        if (updatedProduct) {
                            selectedMovementsProduct.value = updatedProduct;
                        }
                    },
                });
            })
            .listen(".product.changed", () => {
                router.reload({
                    only: [
                        "branchProductsDB",
                        "productsDB",
                        "inventoryStats",
                        "inventoryAlerts",
                    ],
                    preserveScroll: true,
                    preserveState: true,
                });
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
        stockFilter,
        statusFilter,
        expirationStatusFilter,
        inactiveCandidateFilter,
        recordsToShow,

        paginationLinks,
        hasPagination,

        visualProducts,
        filteredProducts,

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
        showBatchAdjustmentModal,
        liveSelectedBatch,

        adjustBatch,
        closeBatchAdjustmentModal,

        batchAdjustmentProcessing,
        batchAdjustmentForm,
        batchAdjustmentErrors,
        batchAdjustmentTotalErrors,
        batchAdjustmentIsSeasonal,
        batchAdjustmentCalculatedQuantity,
        batchAdjustmentText,
        batchAdjustmentQuantityResultColor,

        setBatchAdjustmentType,
        validateBatchAdjustmentField,
        saveEditedBatch,
    };
}
