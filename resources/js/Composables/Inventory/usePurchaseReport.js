import { computed, onBeforeUnmount, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { getModalRequestOptions } from "@/Components/Modales/useModalConfig";

function resolveStockStatus(product) {
    const stock = Number(product.stock || 0);
    const minStock = Number(product.min_stock || 0);

    if (stock <= 0) return "Agotado";
    if (stock <= minStock) return "Stock bajo";
    return "Disponible";
}

export function usePurchaseReport(props) {
    const notes = ref("");
    const selectedItems = ref({});
    const editingOrder = ref(null);
    let filterTimer = null;

    const localFilters = ref({
        search: props.filters?.search ?? "",
        category_id: props.filters?.category_id ?? "",
        stock: props.filters?.stock ?? "",
        per_page: props.filters?.per_page ?? 25,
    });

    const products = computed(() => props.productsDB?.data ?? []);
    const paginator = computed(() => props.productsDB ?? {});
    const isEditing = computed(() => Boolean(editingOrder.value));

    const tableRows = computed(() =>
        products.value.map((product) => ({
            ...product,
            primary_code: product.primary_code || product.main_barcode || product.code || "",
            stock_status: resolveStockStatus(product),
            in_purchase_list: Boolean(selectedItems.value[product.id]),
        }))
    );

    const selectedProducts = computed(() =>
        Object.values(selectedItems.value).sort((left, right) =>
            String(left.name || "").localeCompare(String(right.name || ""), "es")
        )
    );

    const selectedCount = computed(() => selectedProducts.value.length);
    const totalQuantity = computed(() => selectedProducts.value.reduce(
        (total, item) => total + Number(item.requested_quantity || 0),
        0
    ));

    watch(localFilters, () => {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(() => applyFilters({ page: 1 }), 300);
    }, { deep: true });

    onBeforeUnmount(() => clearTimeout(filterTimer));

    function addProduct(product) {
        if (!product || selectedItems.value[product.id]) return;

        selectedItems.value = {
            ...selectedItems.value,
            [product.id]: {
                branch_product_id: product.id,
                name: product.name,
                code: product.primary_code || product.main_barcode || product.code || "",
                stock: Number(product.stock || 0),
                min_stock: Number(product.min_stock || 0),
                requested_quantity: 1,
            },
        };
    }

    function toggleProduct(product) {
        if (selectedItems.value[product.id]) {
            removeItem(product.id);
            return;
        }

        addProduct(product);
    }

    function updateItem(productId, field, value) {
        if (!selectedItems.value[productId]) return;

        selectedItems.value = {
            ...selectedItems.value,
            [productId]: {
                ...selectedItems.value[productId],
                [field]: value,
            },
        };
    }

    function increaseQuantity(productId) {
        const item = selectedItems.value[productId];
        if (!item) return;

        updateItem(productId, "requested_quantity", Number(item.requested_quantity || 0) + 1);
    }

    function decreaseQuantity(productId) {
        const item = selectedItems.value[productId];
        if (!item) return;

        const nextQuantity = Number(item.requested_quantity || 0) - 1;

        if (nextQuantity <= 0) {
            removeItem(productId);
            return;
        }

        updateItem(productId, "requested_quantity", nextQuantity);
    }

    function removeItem(productId) {
        const copy = { ...selectedItems.value };
        delete copy[productId];
        selectedItems.value = copy;
    }

    function clearDraft() {
        notes.value = "";
        selectedItems.value = {};
        editingOrder.value = null;
    }

    function applyFilters(overrides = {}) {
        router.get(
            route("inventory.branches.purchase-reports.index", {
                branch: props.currentBranch.id,
            }),
            {
                search: localFilters.value.search || undefined,
                category_id: localFilters.value.category_id || undefined,
                stock: localFilters.value.stock || undefined,
                per_page: localFilters.value.per_page || 25,
                list_status: props.listFilters?.status || "DRAFT",
                ...overrides,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    }

    function resetFilters() {
        localFilters.value = {
            search: "",
            category_id: "",
            stock: "",
            per_page: 25,
        };
    }

    function clearWorkspace() {
        clearDraft();
        resetFilters();
    }

    function editDraft(order) {
        if (!order || order.status !== "DRAFT") return;

        const mappedItems = {};
        for (const item of order.items ?? []) {
            const branchProduct = item.branch_product ?? {};
            const product = branchProduct.product ?? {};
            const productId = Number(item.branch_product_id);

            mappedItems[productId] = {
                branch_product_id: productId,
                name: product.name || "Producto sin nombre",
                code: product.barcodes?.[0]?.code || branchProduct.barcode || "",
                stock: Number(item.current_stock || branchProduct.stock || 0),
                min_stock: Number(item.min_stock || branchProduct.min_stock || 0),
                requested_quantity: Number(item.requested_quantity || 1),
            };
        }

        editingOrder.value = order;
        notes.value = order.notes ?? "";
        selectedItems.value = mappedItems;
        window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function buildPayload() {
        return {
            notes: notes.value,
            items: selectedProducts.value.map((item) => ({
                branch_product_id: item.branch_product_id,
                requested_quantity: Number(item.requested_quantity || 0),
            })),
        };
    }

    function saveDraft() {
        const payload = buildPayload();
        const updating = Boolean(editingOrder.value);
        const options = getModalRequestOptions({
            mode: updating ? "update" : "save",
            entityName: "Lista de compra",
            successTitle: updating
                ? "Lista de compra actualizada correctamente"
                : "Borrador guardado correctamente",
            errorTitle: "No se pudo guardar la lista",
            errorMessage: "Revisa los productos y cantidades antes de intentarlo nuevamente.",
            onSuccess: clearDraft,
        });

        if (updating) {
            router.put(
                route("inventory.branches.purchase-reports.update", {
                    branch: props.currentBranch.id,
                    purchaseReport: editingOrder.value.id,
                }),
                payload,
                options
            );
            return;
        }

        router.post(
            route("inventory.branches.purchase-reports.store", {
                branch: props.currentBranch.id,
            }),
            payload,
            options
        );
    }

    function generateOrder() {
        const options = getModalRequestOptions({
            mode: "create",
            entityName: "Orden de compra",
            successTitle: "Orden de compra generada correctamente",
            errorTitle: "No se pudo generar la orden",
            errorMessage: "Revisa los productos y cantidades antes de intentarlo nuevamente.",
            onSuccess: clearDraft,
        });

        if (!editingOrder.value) {
            router.post(
                route("inventory.branches.purchase-reports.store", {
                    branch: props.currentBranch.id,
                }),
                { ...buildPayload(), generate_order: true },
                options
            );
            return;
        }

        router.post(
            route("inventory.branches.purchase-reports.generate", {
                branch: props.currentBranch.id,
                purchaseReport: editingOrder.value.id,
            }),
            buildPayload(),
            options
        );
    }

    return {
        notes,
        selectedItems,
        editingOrder,
        isEditing,
        localFilters,
        products,
        paginator,
        tableRows,
        selectedProducts,
        selectedCount,
        totalQuantity,
        addProduct,
        toggleProduct,
        updateItem,
        increaseQuantity,
        decreaseQuantity,
        removeItem,
        clearDraft,
        clearWorkspace,
        applyFilters,
        resetFilters,
        editDraft,
        saveDraft,
        generateOrder,
    };
}
