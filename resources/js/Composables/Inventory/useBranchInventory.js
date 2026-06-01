import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";

export function useBranchInventory(props) {
    const showCreateModal = ref(false);
    const showAdjustModal = ref(false);
    const selectedProduct = ref(null);
    const selectedAlertType = ref(null);

    const search = ref(props.filters?.search ?? "");
    const categoryFilter = ref("");
    const stockFilter = ref("");
    const recordsToShow = ref(Number(props.filters?.per_page ?? 50));

    const realtimeUpdates = ref({});
    let searchTimeout = null;

    const products = computed(() => props.productsDB ?? []);
    const branches = computed(() => props.branchesDB ?? []);
    const currentBranch = computed(() => props.currentBranch ?? null);

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
            const tracksBatches = Boolean(
                item.tracks_batches ?? item.tracksBatches ?? false,
            );

            let status = "Disponible";

            if (stock <= 0) {
                status = "Agotado";
            } else if (stock <= minStock) {
                status = "Stock bajo";
            }

            return {
                id: item.id,
                name: item.product?.name ?? item.name ?? "Producto sin nombre",
                code:
                    item.product?.barcodes?.[0]?.code ??
                    item.barcode ??
                    `BP-${item.id}`,
                category: item.product?.category?.name ?? "Sin categoría",
                branch:
                    item.branch?.name ??
                    currentBranch.value?.name ??
                    "Sucursal",
                status,
                stock,
                minStock,
                price,
                tracksBatches,
                expirationDate:
                    item.next_expiration_date ?? item.expiration_date ?? null,
                active: item.active ?? true,
                activeBatchesCount: item.active_batches_count ?? 0,
                batches: realtime.batches ?? item.batches ?? [],
                recentMovements: realtime.movements ?? item.movements ?? [],
                raw: item,
            };
        });
    });

    const liveSelectedProduct = computed(() => {
        if (!selectedProduct.value) return null;

        return (
            visualProducts.value.find((product) => {
                return product.id === selectedProduct.value.id;
            }) ?? null
        );
    });

    const filteredProducts = computed(() => {
        return visualProducts.value.filter((product) => {
            const matchesCategory =
                !categoryFilter.value ||
                product.category === categoryFilter.value;

            const matchesStock =
                !stockFilter.value || product.status === stockFilter.value;

            return matchesCategory && matchesStock;
        });
    });

    const stats = computed(() => {
        const products = visualProducts.value;

        const totalStock = products.reduce((acc, product) => {
            return acc + Number(product.stock || 0);
        }, 0);

        const inventoryValue = products.reduce((acc, product) => {
            return (
                acc + Number(product.stock || 0) * Number(product.price || 0)
            );
        }, 0);

        const lowStock = products.filter((product) => {
            return (
                Number(product.stock || 0) > 0 &&
                Number(product.stock || 0) <= Number(product.minStock || 0)
            );
        }).length;

        const outOfStock = products.filter((product) => {
            return Number(product.stock || 0) <= 0;
        }).length;

        const expiringSoon = products.filter((product) => {
            return product.batches.some((batch) => {
                return batch.expiration_status === "NEAR_EXPIRATION";
            });
        }).length;

        return {
            total: products.length,
            totalStock,
            inventoryValue,
            lowStock,
            outOfStock,
            expiringSoon,
        };
    });

    const expiredBatchesList = computed(() => {
        return visualProducts.value.flatMap((product) => {
            return (product.batches || [])
                .filter((batch) => batch.expiration_status === "EXPIRED")
                .map((batch) => ({
                    ...batch,
                    product_name: product.name,
                    product_code: product.code,
                    branch_name: product.branch,
                    stock: product.stock,
                }));
        });
    });

    const nearExpirationBatchesList = computed(() => {
        return visualProducts.value.flatMap((product) => {
            return (product.batches || [])
                .filter((batch) => {
                    return batch.expiration_status === "NEAR_EXPIRATION";
                })
                .map((batch) => ({
                    ...batch,
                    product_name: product.name,
                    product_code: product.code,
                    branch_name: product.branch,
                    stock: product.stock,
                }));
        });
    });

    const lowStockProductsList = computed(() => {
        return visualProducts.value.filter((product) => {
            return (
                Number(product.stock || 0) > 0 &&
                Number(product.stock || 0) <= Number(product.minStock || 0)
            );
        });
    });

    const alerts = computed(() => ({
        expiredBatches: expiredBatchesList.value.length,
        nearExpirationBatches: nearExpirationBatchesList.value.length,
        lowStockProducts: lowStockProductsList.value.length,

        expiredBatchesList: expiredBatchesList.value,
        nearExpirationBatchesList: nearExpirationBatchesList.value,
    }));

    const selectedAlertTitle = computed(() => {
        return (
            {
                expired: "Lotes vencidos",
                nearExpiration: "Lotes próximos a vencer",
                lowStock: "Productos con stock bajo",
            }[selectedAlertType.value] || ""
        );
    });

    const selectedAlertBatches = computed(() => {
        return (
            {
                expired: expiredBatchesList.value,
                nearExpiration: nearExpirationBatchesList.value,
                lowStock: lowStockProductsList.value,
            }[selectedAlertType.value] || []
        );
    });

    const showAlertModal = computed(() => Boolean(selectedAlertType.value));

    const reloadInventory = () => {
        router.get(
            window.location.pathname,
            {
                search: search.value || undefined,
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

    const adjustStock = (product) => {
        selectedProduct.value = product;
        showAdjustModal.value = true;
    };

    const closeAdjustModal = () => {
        showAdjustModal.value = false;
        selectedProduct.value = null;
    };

    const exportExcel = () => {
        console.log("Exportar inventario", visualProducts.value);
    };

    const viewProduct = (product) => {
        console.log("Ver producto", product);
    };

    const editProduct = (product) => {
        console.log("Editar producto", product);
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

    watch(recordsToShow, () => {
        reloadInventory();
    });

    onMounted(() => {
        if (!window.Echo || !currentBranch.value?.id) return;

        window.Echo.channel(
            `inventory.branch.${currentBranch.value.id}`,
        ).listen(".stock.updated", (event) => {
            realtimeUpdates.value = {
                ...realtimeUpdates.value,

                [event.branch_product_id]: {
                    stock: Number(event.stock),
                    updated_at: event.updated_at,
                    batches: event.batches ?? [],
                    movements: event.recent_movements ?? [],
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
        currentBranch,

        showCreateModal,
        showAdjustModal,
        selectedProduct,
        liveSelectedProduct,

        search,
        categoryFilter,
        stockFilter,
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

        adjustStock,
        closeAdjustModal,

        reloadInventory,
        goToPage,

        exportExcel,
        viewProduct,
        editProduct,
        deleteProduct,
    };
}
