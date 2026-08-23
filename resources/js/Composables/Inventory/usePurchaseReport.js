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

function normalizeQuantity(value, minimum = 1, allowDecimal = false) {
    if (value === "") return "";

    const rawValue = String(value ?? "").replace(",", ".");
    const quantity = allowDecimal
        ? Number(rawValue.replace(/[^\d.]/g, ""))
        : Number(rawValue.replace(/[^\d]/g, ""));

    if (!Number.isFinite(quantity)) return minimum;

    return allowDecimal
        ? Math.max(minimum, Math.round(quantity * 1000) / 1000)
        : Math.max(minimum, Math.floor(quantity));
}

function defaultPresentation(product) {
    return product.inventory_unit === "kg" ? "kilo" : "piece";
}

function presentationToServer(value) {
    return {
        box: "Caja",
        kilo: "Kilo",
        piece: "Pieza",
    }[value] || "Pieza";
}

function unitsPerPackage(item) {
    return item.presentation === "box" ? Number(item.pieces_per_box || 1) : 1;
}

function baseQuantity(item) {
    const quantity = Number(item.requested_quantity || 0);

    return item.presentation === "box"
        ? quantity * unitsPerPackage(item)
        : quantity;
}

function quantityStep(item) {
    return item.presentation === "kilo" ? 0.001 : 1;
}

export function usePurchaseReport(props) {
    const assignedToUserId = ref("");
    const selectedItems = ref({});
    const editingOrder = ref(null);
    let filterTimer = null;

    const localFilters = ref({
        departmentIds: props.filters?.department_ids ?? [],
        categoryIds: props.filters?.category_ids ?? [],
        productIds: props.filters?.product_ids ?? [],
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
        (total, item) => total + baseQuantity(item),
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
                inventory_unit: product.inventory_unit ?? "pza",
                has_box_presentation: Boolean(product.has_box_presentation),
                pieces_per_box: Number(product.pieces_per_box || 0),
                presentation: defaultPresentation(product),
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
        const currentItem = selectedItems.value[productId];
        const nextValue = field === "requested_quantity"
            ? normalizeQuantity(value, currentItem.presentation === "kilo" ? 0.001 : 1, currentItem.presentation === "kilo")
            : value;

        selectedItems.value = {
            ...selectedItems.value,
            [productId]: {
                ...selectedItems.value[productId],
                [field]: nextValue,
            },
        };
    }

    function increaseQuantity(productId) {
        const item = selectedItems.value[productId];
        if (!item) return;

        updateItem(productId, "requested_quantity", Number(item.requested_quantity || 0) + quantityStep(item));
    }

    function decreaseQuantity(productId) {
        const item = selectedItems.value[productId];
        if (!item) return;

        const step = quantityStep(item);
        const nextQuantity = Number(item.requested_quantity || 0) - step;

        if (nextQuantity < step) {
            removeItem(productId);
            return;
        }

        updateItem(productId, "requested_quantity", nextQuantity);
    }

    function setPresentation(productId, presentation) {
        const item = selectedItems.value[productId];
        if (!item) return;
        if (presentation === "box" && !item.has_box_presentation) return;
        if (presentation === "kilo" && item.inventory_unit !== "kg") return;

        selectedItems.value = {
            ...selectedItems.value,
            [productId]: {
                ...item,
                presentation,
                requested_quantity: normalizeQuantity(
                    item.requested_quantity,
                    presentation === "kilo" ? 0.001 : 1,
                    presentation === "kilo",
                ),
            },
        };
    }

    function itemBaseQuantity(item) {
        return baseQuantity(item);
    }

    function removeItem(productId) {
        const copy = { ...selectedItems.value };
        delete copy[productId];
        selectedItems.value = copy;
    }

    function clearDraft() {
        assignedToUserId.value = "";
        selectedItems.value = {};
        editingOrder.value = null;
    }

    function applyFilters(overrides = {}) {
        router.get(
            route("ventas.purchase-reports.index"),
            {
                branch: props.currentBranch.id,
                department_ids: localFilters.value.departmentIds?.length ? localFilters.value.departmentIds : undefined,
                category_ids: localFilters.value.categoryIds?.length ? localFilters.value.categoryIds : undefined,
                product_ids: localFilters.value.productIds?.length ? localFilters.value.productIds : undefined,
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
                inventory_unit: product.inventory_unit ?? "pza",
                has_box_presentation: Boolean(product.has_box_presentation),
                pieces_per_box: Number(product.pieces_per_box || 0),
                presentation: item.purchase_presentation === "Caja"
                    ? "box"
                    : ((product.inventory_unit ?? "pza") === "kg" ? "kilo" : "piece"),
                requested_quantity: Number(item.package_quantity || item.requested_quantity || 1),
            };
        }

        editingOrder.value = order;
        assignedToUserId.value = order.assigned_to_user_id ?? "";
        selectedItems.value = mappedItems;
        window.scrollTo({ top: 0, behavior: "smooth" });
    }

    function buildPayload() {
        return {
            assigned_to_user_id: assignedToUserId.value || null,
            record_version: editingOrder.value?.record_version || null,
            items: selectedProducts.value.map((item) => ({
                branch_product_id: item.branch_product_id,
                requested_quantity: baseQuantity(item),
                purchase_presentation: presentationToServer(item.presentation),
                package_quantity: Number(item.requested_quantity || 0),
                units_per_package: unitsPerPackage(item),
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
        assignedToUserId,
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
        setPresentation,
        itemBaseQuantity,
        removeItem,
        clearDraft,
        applyFilters,
        editDraft,
        saveDraft,
        generateOrder,
    };
}
