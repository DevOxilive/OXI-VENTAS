import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { useBatchAdjustmentModal } from "@/Composables/Inventory/useBatchAdjustmentModal";
import { useGlobalTablePagination } from "@/Composables/useGlobalTablePagination";

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

function cloneBranchProductsPayload(payload) {
    if (Array.isArray(payload)) {
        return payload.map((item) => ({ ...item }));
    }

    return {
        ...(payload ?? {}),
        data: Array.isArray(payload?.data)
            ? payload.data.map((item) => ({ ...item }))
            : [],
        links: Array.isArray(payload?.links) ? [...payload.links] : [],
    };
}

function cloneArray(items) {
    return Array.isArray(items) ? items.map((item) => ({ ...item })) : [];
}

function cloneInventoryAlerts(alerts) {
    return {
        ...(alerts ?? {}),
        expired_batches_list: cloneArray(alerts?.expired_batches_list),
        near_expiration_batches_list: cloneArray(alerts?.near_expiration_batches_list),
        low_stock_products_list: cloneArray(alerts?.low_stock_products_list),
        inactive_candidate_products_list: cloneArray(
            alerts?.inactive_candidate_products_list,
        ),
    };
}

function sortBranchProducts(items) {
    return [...items].sort((left, right) => {
        const leftName = String(left?.product?.name ?? left?.name ?? "");
        const rightName = String(right?.product?.name ?? right?.name ?? "");

        return leftName.localeCompare(rightName, "es");
    });
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

    const branchProductsState = ref(cloneBranchProductsPayload(props.branchProductsDB));
    const inventoryStatsState = ref({ ...(props.inventoryStats ?? {}) });
    const inventoryAlertsState = ref(cloneInventoryAlerts(props.inventoryAlerts));
    const detailCache = ref({});
    const detailRequests = ref({});
    let searchTimeout = null;

    const products = computed(() => props.productsDB ?? []);
    const branches = computed(() => props.branchesDB ?? []);
    const categories = computed(() => props.categoriesDB ?? []);
    const currentBranch = computed(() => props.currentBranch ?? null);
    const inventoryAlerts = computed(() => inventoryAlertsState.value ?? {});

    watch(
        () => props.branchProductsDB,
        (value) => {
            branchProductsState.value = cloneBranchProductsPayload(value);
        },
        { deep: true },
    );

    watch(
        () => props.inventoryStats,
        (value) => {
            inventoryStatsState.value = { ...(value ?? {}) };
        },
        { deep: true },
    );

    watch(
        () => props.inventoryAlerts,
        (value) => {
            inventoryAlertsState.value = cloneInventoryAlerts(value);
        },
        { deep: true },
    );

    const rawBranchProducts = computed(() => {
        if (Array.isArray(branchProductsState.value)) {
            return branchProductsState.value;
        }

        return branchProductsState.value?.data ?? [];
    });

    const paginationLinks = computed(() => {
        return Array.isArray(branchProductsState.value?.links)
            ? branchProductsState.value.links
            : [];
    });

    const hasPagination = computed(() => {
        return (
            !Array.isArray(branchProductsState.value) &&
            paginationLinks.value.length > 0
        );
    });

    const visualProducts = computed(() => {
        return rawBranchProducts.value.map((item) => {
            const detail = detailCache.value[item.id] ?? {};
            const stock = Number(item.stock ?? 0);
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
                category_name: item.product?.category?.name ?? "Sin categorÃ­a",
                category: item.product?.category?.name ?? "Sin categorÃ­a",
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
                batches: detail.batches ?? item.batches ?? [],
                recentMovements: detail.movements ?? item.movements ?? [],
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
                nearExpiration: "Lotes prÃ³ximos a vencer",
                lowStock: "Productos con stock bajo",
                inactiveCandidates: "Productos sin rotaciÃ³n",
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

    function updateBranchProductsState(mutator) {
        const currentValue = branchProductsState.value;

        if (Array.isArray(currentValue)) {
            branchProductsState.value = mutator([...currentValue], true);
            return;
        }

        const nextData = mutator([...(currentValue?.data ?? [])], false);
        branchProductsState.value = {
            ...(currentValue ?? {}),
            data: nextData,
        };
    }

    function upsertBranchProductRow(branchProduct) {
        if (!branchProduct?.id) return;

        updateBranchProductsState((items, isArrayPayload) => {
            const nextItems = [...items];
            const existingIndex = nextItems.findIndex(
                (item) => Number(item.id) === Number(branchProduct.id),
            );

            if (existingIndex >= 0) {
                nextItems[existingIndex] = {
                    ...nextItems[existingIndex],
                    ...branchProduct,
                };
            } else {
                nextItems.push(branchProduct);
            }

            const sortedItems = sortBranchProducts(nextItems);

            if (!isArrayPayload && branchProductsState.value) {
                const currentTotal = Number(
                    branchProductsState.value.total ?? items.length,
                );
                branchProductsState.value.total =
                    existingIndex >= 0 ? currentTotal : currentTotal + 1;
            }

            return sortedItems;
        });
    }

    function removeBranchProductByProductId(productId) {
        if (!productId) return;

        updateBranchProductsState((items, isArrayPayload) => {
            const nextItems = items.filter(
                (item) => Number(item.product_id) !== Number(productId),
            );

            if (!isArrayPayload && branchProductsState.value) {
                const currentTotal = Number(
                    branchProductsState.value.total ?? items.length,
                );
                const removed = nextItems.length !== items.length;
                branchProductsState.value.total = removed
                    ? Math.max(0, currentTotal - 1)
                    : currentTotal;
            }

            return nextItems;
        });
    }

    function applyRealtimeSnapshot(snapshot) {
        if (snapshot?.branchProduct) {
            upsertBranchProductRow(snapshot.branchProduct);
        }

        if (snapshot?.branchProductDetails?.id) {
            detailCache.value = {
                ...detailCache.value,
                [snapshot.branchProductDetails.id]: snapshot.branchProductDetails,
            };
        }

        if (snapshot?.inventoryStats) {
            inventoryStatsState.value = { ...snapshot.inventoryStats };
        }

        if (snapshot?.inventoryAlerts) {
            inventoryAlertsState.value = cloneInventoryAlerts(
                snapshot.inventoryAlerts,
            );
        }
    }

    async function fetchRealtimeSnapshot(params = {}) {
        if (!currentBranch.value?.id) return null;

        try {
            const { data } = await window.axios.get(
                route("inventory.branches.inventory.realtime-snapshot", {
                    branch: currentBranch.value.id,
                }),
                {
                    params,
                },
            );

            applyRealtimeSnapshot(data);
            return data;
        } catch (error) {
            console.error(
                "No se pudo actualizar el estado en tiempo real del inventario",
                error,
            );
            return null;
        }
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

        window.Echo.channel(`inventory.branch.${currentBranch.value.id}`)
            .listen(".stock.updated", async (event) => {
                const branchProductId =
                    event.branch_product_id ??
                    event.branchProduct?.id ??
                    event.branch_product?.id;

                if (!branchProductId) return;

                const shouldRefreshDetails =
                    shouldRefreshRealtimeDetails(branchProductId);

                await fetchRealtimeSnapshot({
                    branch_product_id: branchProductId,
                });

                if (shouldRefreshDetails) {
                    await refreshProductDetails(branchProductId);
                }
            })
            .listen(".product.changed", async (event) => {
                const branchIds = event.branchIds ?? event.branch_ids ?? [];
                const currentBranchId = Number(currentBranch.value?.id);

                if (
                    Array.isArray(branchIds) &&
                    branchIds.length > 0 &&
                    !branchIds.map(Number).includes(currentBranchId)
                ) {
                    return;
                }

                const productId = event.productId ?? event.product_id ?? null;

                const snapshot = await fetchRealtimeSnapshot({
                    product_id: productId,
                });

                if (!snapshot?.branchProduct) {
                    removeBranchProductByProductId(productId);
                }
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
        inventoryStats: computed(() => inventoryStatsState.value),

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
