import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { useBatchAdjustmentModal } from "@/Composables/Inventory/useBatchAdjustmentModal";
import { useGlobalTablePagination } from "@/Composables/useGlobalTablePagination";
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribeRealtime, subscribeRealtimeReconnect } from "@/realtime";
import { createInventoryRealtimeState } from "@/utils/inventoryRealtimeState.mjs";
import { createInventoryRealtimeBatch, createInventoryPageRefresh, readInventoryPage, inventoryPartialHeaders } from "@/utils/inventoryRealtimeBatch.mjs";

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

function normalizeFilterIds(value) {
    return (Array.isArray(value) ? value : value ? [value] : [])
        .map((item) => String(item))
        .filter(Boolean);
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

export function useBranchInventory(props) {
    let unsubscribeStockUpdated = null;
    let unsubscribeProductChanged = null;
    let unsubscribeReconnect = null;
    let realtimeMounted = false;
    const page = usePage();
    const { handlePageChange } = useGlobalTablePagination({ only: ["branchProductsDB", "filters"] });
    const showCreateModal = ref(false);
    const showEntryModal = ref(false);
    const showExitModal = ref(false);
    const showMovementsModal = ref(false);
    const showConfigModal = ref(false);

    const selectedMovementProduct = ref(null);
    const selectedMovementBranch = ref(null);
    const selectedMovementsProduct = ref(null);
    const selectedConfigProduct = ref(null);
    const selectedAlertType = ref(null);

    const search = ref(props.filters?.search ?? "");
    const productDepartmentFilter = ref(
        normalizeFilterIds(props.filters?.product_department),
    );
    const categoryFilter = ref(normalizeFilterIds(props.filters?.category));
    const stockFilter = ref(props.filters?.stock ?? "");
    const statusFilter = ref(props.filters?.status ?? "");
    const expirationStatusFilter = ref(props.filters?.expiration_status ?? "");
    const inactiveCandidateFilter = ref(
        props.filters?.inactive_candidate ?? "",
    );
    const recordsToShow = ref(Number(props.filters?.per_page ?? 25));

    const branchProductsState = ref(cloneBranchProductsPayload(props.branchProductsDB));
    const inventoryStatsState = ref({ ...(props.inventoryStats ?? {}) });
    const inventoryAlertsState = ref(cloneInventoryAlerts(props.inventoryAlerts));
    const detailCache = ref({});
    const detailRequests = ref({});
    const staleDetails = new Set();
    const detailRevisions = new Map();
    const movementLoading = ref(false);
    const movementParams = ref({});
    let movementLoadSequence = 0;
    let lastSearchVisit = { key: "", at: 0 };
    const realtimeState = createInventoryRealtimeState();
    const detailState = createInventoryRealtimeState();
    const summaryState = createInventoryRealtimeState();
    let searchTimeout = null;

    const products = computed(() => props.productsDB ?? []);
    const branches = computed(() => props.branchesDB ?? []);
    const productDepartments = computed(() => props.productDepartmentsDB ?? []);
    const categories = computed(() => props.categoriesDB ?? []);
    const currentBranch = computed(() => props.currentBranch ?? null);
    const inventoryAlerts = computed(() => inventoryAlertsState.value ?? {});

    watch(
        () => props.branchProductsDB,
        (value) => {
            realtimeState.invalidateAll();
            detailState.invalidateAll();
            pageRefresh.invalidate();
            branchProductsState.value = cloneBranchProductsPayload(value);
        },
        { deep: true },
    );

    watch(
        () => props.inventoryStats,
        (value) => {
            summaryState.invalidateAll();
            inventoryStatsState.value = { ...(value ?? {}) };
        },
        { deep: true },
    );

    watch(
        () => props.inventoryAlerts,
        (value) => {
            summaryState.invalidateAll();
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
            const inventoryQuantityMode = detail.product?.inventory_quantity_mode
                ?? item.product?.inventory_quantity_mode
                ?? "base";
            const inventoryUnit = detail.product?.inventory_unit
                ?? item.product?.inventory_unit
                ?? item.product?.unit
                ?? "pza";
            const unit = inventoryQuantityMode === "legacy_presentation"
                ? (detail.product?.unit ?? item.product?.unit ?? inventoryUnit)
                : inventoryUnit;
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
                barcodes: (item.product?.barcodes ?? [])
                    .map((barcode) => barcode?.code)
                    .filter(Boolean),
                product_department_id: item.product?.category?.product_department_id ?? null,
                product_department_name: item.product?.category?.product_department?.name ?? "Sin departamento",
                department: item.product?.category?.product_department?.name ?? "Sin departamento",
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
                inventory_unit: inventoryUnit,
                inventory_quantity_mode: inventoryQuantityMode,
                has_box_presentation: Boolean(
                    detail.product?.has_box_presentation ?? item.product?.has_box_presentation,
                ),
                pieces_per_box: Number(
                    detail.product?.pieces_per_box ?? item.product?.pieces_per_box ?? 0,
                ) || null,
                assigned_branch_ids: Array.isArray(detail.assigned_branch_ids)
                    ? detail.assigned_branch_ids.map(Number)
                    : [],
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
                movementsPagination: detail.movements_pagination ?? null,
                movementsTotal: detail.movements_total ?? null,
                movementsUsers: detail.movements_users ?? [],
                movementsSummary: detail.movements_summary ?? [],
                raw: {
                    ...item,
                    ...detail,
                },
            };
        });
    });

    const {
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
        clearSelectedBatch,
        setAdjustmentType: setBatchAdjustmentType,
        validateField: validateBatchAdjustmentField,
        saveEditedBatch,
    } = useBatchAdjustmentModal(visualProducts);

    const showProductBatchesModal = ref(false);
    const selectedBatchesProductId = ref(null);
    const selectedBatchesProduct = ref(null);

    function detailedSelectedProduct(product) {
        if (!product) return null;
        const current = visualProducts.value.find((item) => Number(item.id) === Number(product.id));
        if (current) return current;
        const detail = detailCache.value[product.id];
        return detail ? {
            ...product,
            stock: Number(detail.stock ?? product.stock ?? 0),
            batches: detail.batches ?? product.batches,
            recentMovements: detail.movements ?? product.recentMovements,
            movementsPagination: detail.movements_pagination ?? product.movementsPagination,
            movementsTotal: detail.movements_total ?? product.movementsTotal,
            movementsUsers: detail.movements_users ?? product.movementsUsers,
            movementsSummary: detail.movements_summary ?? product.movementsSummary,
        } : product;
    }

    const liveSelectedBatchesProduct = computed(() => {
        if (!selectedBatchesProductId.value) return null;

        return detailedSelectedProduct(selectedBatchesProduct.value);
    });

    async function openProductBatchesModal(product) {
        const branchId = currentBranch.value?.id;
        const detailedProduct = await ensureProductDetails(product);
        if (!realtimeState.isActive() || Number(currentBranch.value?.id) !== Number(branchId)) return;
        clearSelectedBatch();
        selectedBatchesProductId.value = detailedProduct?.id ?? product.id;
        selectedBatchesProduct.value = detailedProduct;
        showProductBatchesModal.value = true;
    }

    function closeProductBatchesModal() {
        clearSelectedBatch();
        showProductBatchesModal.value = false;
        selectedBatchesProductId.value = null;
        selectedBatchesProduct.value = null;
    }

    const liveSelectedMovementProduct = computed(() => {
        if (!selectedMovementProduct.value) return null;

        return detailedSelectedProduct(selectedMovementProduct.value);
    });

    const liveSelectedMovementsProduct = computed(() => {
        if (!selectedMovementsProduct.value) return null;

        return detailedSelectedProduct(selectedMovementsProduct.value);
    });

    const liveSelectedConfigProduct = computed(() => {
        if (!selectedConfigProduct.value) return null;

        return (
            visualProducts.value.find((product) => {
                return product.id === selectedConfigProduct.value.id;
            }) ?? selectedConfigProduct.value
        );
    });

    // La lista ya llega filtrada desde el motor central. Volver a aplicar aqui
    // una coincidencia literal ocultaria resultados validos como "coc 600".
    const filteredProducts = computed(() => visualProducts.value);

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
        clearTimeout(searchTimeout);
        pageRefresh.invalidate();
        const requestKey = JSON.stringify([search.value, productDepartmentFilter.value, categoryFilter.value,
            stockFilter.value, statusFilter.value, expirationStatusFilter.value, inactiveCandidateFilter.value, recordsToShow.value]);
        if (requestKey === lastSearchVisit.key && Date.now() - lastSearchVisit.at < 300) return;
        lastSearchVisit = { key: requestKey, at: Date.now() };
        router.get(
            window.location.pathname,
            {
                search: search.value || undefined,
                product_department: productDepartmentFilter.value.length
                    ? productDepartmentFilter.value
                    : undefined,
                category: categoryFilter.value.length
                    ? categoryFilter.value
                    : undefined,
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
                only: ["branchProductsDB", "filters"],
            },
        );
    };

    const goToPage = (url) => {
        pageRefresh.invalidate();
        handlePageChange(url);
    };

    function productRequestKeys({ productId = null, branchProductId = null } = {}) {
        const row = rawBranchProducts.value.find((item) => (
            (branchProductId && Number(item.id) === Number(branchProductId)) ||
            (productId && Number(item.product_id) === Number(productId))
        ));
        const resolvedProductId = productId ?? row?.product_id;
        const resolvedBranchProductId = branchProductId ?? row?.id;

        return [
            resolvedProductId ? `product:${resolvedProductId}` : null,
            resolvedBranchProductId ? `branch-product:${resolvedBranchProductId}` : null,
        ];
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

    const pageRefresh = createInventoryPageRefresh({
        getContext: () => JSON.stringify([window.location.href, page.component, currentBranch.value?.id,
            search.value, productDepartmentFilter.value, categoryFilter.value, stockFilter.value,
            statusFilter.value, expirationStatusFilter.value, inactiveCandidateFilter.value, recordsToShow.value]),
        load: async ({ signal }) => {
            const { data } = await window.axios.get(window.location.href, {
                signal,
                headers: inventoryPartialHeaders(page, ["branchProductsDB", "filters"]),
            });
            return readInventoryPage(data, page.component, "branchProductsDB");
        },
        apply: (value) => { branchProductsState.value = cloneBranchProductsPayload(value); },
        onError: (error) => console.error("No se pudo actualizar el inventario", error),
    });

    async function refreshInventorySummary() {
        if (!currentBranch.value?.id) return;
        const branchId = currentBranch.value.id;
        return summaryState.request("summary", {
            load: async () => {
                const { data } = await window.axios.get(route("inventory.branches.inventory.summary", { branch: branchId }), {
                    params: { include_alerts: showAlertModal.value ? 1 : 0 },
                });
                if (!data?.inventoryStats || !data?.inventoryAlerts) throw new Error("Resumen de inventario incompleto");
                return data;
            },
            onSuccess: (data) => {
                if (Number(currentBranch.value?.id) !== Number(branchId)) return;
                inventoryStatsState.value = { ...data.inventoryStats };
                inventoryAlertsState.value = cloneInventoryAlerts({ ...inventoryAlertsState.value, ...data.inventoryAlerts });
            },
            onError: (error) => console.error("No se pudo actualizar el resumen del inventario", error),
        });
    }

    function invalidateEventDetails(event) {
        const productId = event.productId ?? event.product_id;
        const branchProductId = event.branch_product_id ?? event.branchProduct?.id ?? event.branch_product?.id;
        const candidates = [...rawBranchProducts.value, ...Object.values(detailCache.value)];
        const touched = new Set();
        candidates.forEach((item) => {
            if (event.reconnect || Number(item.id) === Number(branchProductId) ||
                (productId && Number(item.product_id ?? item.product?.id) === Number(productId))) touched.add(item.id);
        });
        touched.forEach((id) => {
            staleDetails.add(id);
            detailRevisions.set(id, (detailRevisions.get(id) ?? 0) + 1);
        });
    }

    const realtimeBatch = createInventoryRealtimeBatch({
        flush: async () => {
            const branchId = currentBranch.value?.id;
            const refreshPage = search.value === (props.filters?.search ?? "")
                ? pageRefresh.refresh() : Promise.resolve();
            await Promise.allSettled([refreshPage, refreshInventorySummary()]);
            if (!realtimeState.isActive() || Number(currentBranch.value?.id) !== Number(branchId)) return;
            const opened = new Map();
            if (showEntryModal.value || showExitModal.value) opened.set(selectedMovementProduct.value?.id, selectedMovementProduct.value);
            if (showProductBatchesModal.value) opened.set(selectedBatchesProduct.value?.id, selectedBatchesProduct.value);
            if (showMovementsModal.value) opened.set(selectedMovementsProduct.value?.id, selectedMovementsProduct.value);
            await Promise.allSettled([...opened.values()].filter((product) => product && staleDetails.has(product.id)).map((product) => (
                ensureProductDetails(product, { forceRefresh: true,
                    params: showMovementsModal.value && product.id === selectedMovementsProduct.value?.id
                        ? { ...movementParams.value, include_movements: 1 } : {} })
            )));
        },
    });

    async function ensureProductDetails(product, options = {}) {
        if (!product?.id) return product;
        const { forceRefresh = false, params = {} } = options;

        if (!forceRefresh && !staleDetails.has(product.id) && detailCache.value[product.id]) {
            return (
                visualProducts.value.find((item) => item.id === product.id) ??
                product
            );
        }

        if (!forceRefresh && detailRequests.value[product.id]) {
            await detailRequests.value[product.id];

            return (
                visualProducts.value.find((item) => item.id === product.id) ??
                product
            );
        }

        const revision = detailRevisions.get(product.id) ?? 0;
        const request = detailState
            .request(productRequestKeys({ branchProductId: product.id }), {
                load: async () => {
                    const { data } = await window.axios.get(
                        route("inventory.branch-inventory.details", {
                            branchProduct: product.id,
                        }),
                        { params: { include_movements: 0, ...params } },
                    );

                    return data;
                },
                onSuccess: (data) => {
                    if (revision === (detailRevisions.get(product.id) ?? 0)) staleDetails.delete(product.id);
                    detailCache.value = {
                        ...detailCache.value,
                        [product.id]: data,
                    };
                },
                onError: (error) => {
                    console.error("No se pudo cargar el detalle del producto", error);
                },
            })
            .finally(() => {
                if (!realtimeState.isActive()) return;
                if (detailRequests.value[product.id] !== request) return;
                const requests = { ...detailRequests.value };
                delete requests[product.id];
                detailRequests.value = requests;
            });
        detailRequests.value[product.id] = request;

        await detailRequests.value[product.id];

        return (
            visualProducts.value.find((item) => item.id === product.id) ??
            product
        );
    }

    const openCreateModal = () => {
        showCreateModal.value = true;
    };

    const closeCreateModal = () => {
        showCreateModal.value = false;
    };

    const openAlertModal = (type) => {
        selectedAlertType.value = type;
        void refreshInventorySummary();
    };

    const closeAlertModal = () => {
        selectedAlertType.value = null;
    };

    const openEntryModal = async (product) => {
        const branch = { ...currentBranch.value };
        const detailedProduct = await ensureProductDetails(product);
        if (!realtimeState.isActive() || Number(currentBranch.value?.id) !== Number(branch.id)) return;
        selectedMovementProduct.value = detailedProduct;
        selectedMovementBranch.value = branch;
        showEntryModal.value = true;
    };

    const closeEntryModal = () => {
        showEntryModal.value = false;
        selectedMovementProduct.value = null;
    };

    const openExitModal = async (product) => {
        const branchId = currentBranch.value?.id;
        const detailedProduct = await ensureProductDetails(product);
        if (!realtimeState.isActive() || Number(currentBranch.value?.id) !== Number(branchId)) return;
        selectedMovementProduct.value = detailedProduct;
        showExitModal.value = true;
    };

    const closeExitModal = () => {
        showExitModal.value = false;
        selectedMovementProduct.value = null;
    };

    const openMovementsModal = async (product) => {
        const branchId = currentBranch.value?.id;
        movementParams.value = {};
        const detailedProduct = await ensureProductDetails(product, { forceRefresh: true, params: { include_movements: 1 } });
        if (!realtimeState.isActive() || Number(currentBranch.value?.id) !== Number(branchId)) return;
        selectedMovementsProduct.value = detailedProduct;
        showMovementsModal.value = true;
    };

    const closeMovementsModal = () => {
        showMovementsModal.value = false;
        selectedMovementsProduct.value = null;
    };

    async function loadMovementPage(params = {}) {
        if (!selectedMovementsProduct.value) return;
        const loadSequence = ++movementLoadSequence;
        movementParams.value = params;
        movementLoading.value = true;
        const result = await ensureProductDetails(selectedMovementsProduct.value, { forceRefresh: true, params: { ...params, include_movements: 1 } });
        if (realtimeState.isActive() && loadSequence === movementLoadSequence) movementLoading.value = false;
        return result;
    }

    function handleSearchEnter(event, placeholder) {
        if (event.key !== "Enter" || event.target?.tagName !== "INPUT" || event.target?.placeholder !== placeholder) return;
        reloadInventory();
    }

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
        pageRefresh.invalidate();

        searchTimeout = setTimeout(() => {
            reloadInventory();
        }, /^\d{8,14}$/.test(search.value.trim()) ? 0 : 250);
    });

    watch(recordsToShow, reloadInventory);
    watch(productDepartmentFilter, reloadInventory);
    watch(categoryFilter, reloadInventory);
    watch(stockFilter, reloadInventory);
    watch(statusFilter, reloadInventory);
    watch(expirationStatusFilter, reloadInventory);
    watch(inactiveCandidateFilter, reloadInventory);

    function subscribeForCurrentBranch() {
        unsubscribeStockUpdated?.();
        unsubscribeProductChanged?.();
        unsubscribeStockUpdated = null;
        unsubscribeProductChanged = null;
        if (!currentBranch.value?.id) return;

        const subscribedBranchId = currentBranch.value.id;
        const channelName = REALTIME_CHANNELS.inventoryBranch(subscribedBranchId);

        unsubscribeStockUpdated = subscribeRealtime(
            channelName,
            REALTIME_EVENTS.stockUpdated,
            (event) => {
                if (Number(currentBranch.value?.id) !== Number(subscribedBranchId)) return;
                const branchProductId =
                    event.branch_product_id ??
                    event.branchProduct?.id ??
                    event.branch_product?.id;

                if (!branchProductId) return;

                invalidateEventDetails(event);
                realtimeBatch.add(event);
            },
        );
        unsubscribeProductChanged = subscribeRealtime(
            channelName,
            REALTIME_EVENTS.productChanged,
            (event) => {
                if (Number(currentBranch.value?.id) !== Number(subscribedBranchId)) return;
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
                if (!productId) return;
                invalidateEventDetails(event);

                if (event.action === "deleted") {
                    const requestKeys = productRequestKeys({ productId });
                    realtimeState.invalidate(requestKeys);
                    detailState.invalidate(requestKeys);
                    removeBranchProductByProductId(productId);
                }

                realtimeBatch.add(event);
            },
        );
    }

    watch(() => currentBranch.value?.id, () => {
        clearTimeout(searchTimeout);
        realtimeState.invalidateAll();
        detailState.invalidateAll();
        summaryState.invalidateAll();
        pageRefresh.invalidate();
        realtimeBatch.clear();
        detailCache.value = {};
        detailRequests.value = {};
        staleDetails.clear();
        detailRevisions.clear();
        movementParams.value = {};
        movementLoadSequence++;
        movementLoading.value = false;
        inventoryStatsState.value = { ...(props.inventoryStats ?? {}) };
        inventoryAlertsState.value = cloneInventoryAlerts(props.inventoryAlerts);
        lastSearchVisit = { key: "", at: 0 };
        if (realtimeMounted) subscribeForCurrentBranch();
    }, { flush: "post" });

    onMounted(() => {
        realtimeMounted = true;
        subscribeForCurrentBranch();
        unsubscribeReconnect = subscribeRealtimeReconnect(() => {
            detailState.invalidateAll();
            invalidateEventDetails({ reconnect: true });
            realtimeBatch.add({ reconnect: true });
        });
    });

    onBeforeUnmount(() => {
        clearTimeout(searchTimeout);
        realtimeMounted = false;
        realtimeState.dispose();
        detailState.dispose();
        summaryState.dispose();
        pageRefresh.dispose();
        realtimeBatch.dispose();

        unsubscribeStockUpdated?.();
        unsubscribeProductChanged?.();
        unsubscribeReconnect?.();
    });

    return {
        products,
        branches,
        productDepartments,
        categories,
        currentBranch,
        inventoryStats: computed(() => inventoryStatsState.value),

        showCreateModal,
        showEntryModal,
        showExitModal,
        showMovementsModal,
        showConfigModal,

        selectedMovementProduct,
        selectedMovementBranch,
        selectedMovementsProduct,
        selectedConfigProduct,

        liveSelectedMovementProduct,
        liveSelectedMovementsProduct,
        liveSelectedConfigProduct,

        search,
        productDepartmentFilter,
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
        inventoryPagination: branchProductsState,
        movementLoading,
        loadMovementPage,
        handleSearchEnter,

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
        liveSelectedBatch,

        adjustBatch,

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
