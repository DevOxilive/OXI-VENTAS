import { computed, ref } from "vue";
import { router } from "@inertiajs/vue3";

export function usePurchaseReport(props) {
    const notes = ref("");
    const selectedItems = ref({});

    const localFilters = ref({
        search: props.filters?.search ?? "",
        category: props.filters?.category ?? "",
        subcategory: props.filters?.subcategory ?? "",
        stock: props.filters?.stock ?? "",
        per_page: props.filters?.per_page ?? 50,
    });

    const products = computed(() => props.productsDB?.data ?? []);

    const selectedProducts = computed(() => Object.values(selectedItems.value));
    const selectedCount = computed(() => selectedProducts.value.length);

    const totalQuantity = computed(() => {
        return selectedProducts.value.reduce((total, item) => {
            return total + Number(item.requested_quantity || 0);
        }, 0);
    });

    const estimatedTotal = computed(() => {
        return selectedProducts.value.reduce((total, item) => {
            return (
                total +
                Number(item.requested_quantity || 0) * Number(item.price || 0)
            );
        }, 0);
    });

    function toggleProduct(product) {
        if (selectedItems.value[product.id]) {
            removeItem(product.id);
            return;
        }

        selectedItems.value = {
            ...selectedItems.value,
            [product.id]: {
                branch_product_id: product.id,
                name: product.name,
                code: product.code,
                main_barcode: product.main_barcode,
                barcodes: product.barcodes ?? [],
                stock: product.stock,
                min_stock: product.min_stock,
                price: product.price,
                requested_quantity: "",
                notes: "",
            },
        };
    }

    function updateItem(productId, field, value) {
        selectedItems.value = {
            ...selectedItems.value,
            [productId]: {
                ...selectedItems.value[productId],
                [field]: value,
            },
        };
    }

    function removeItem(productId) {
        const copy = { ...selectedItems.value };
        delete copy[productId];
        selectedItems.value = copy;
    }

    function applyFilters() {
        router.get(
            route("inventario.branches.purchase-reports.create", {
                branch: props.currentBranch.id,
            }),
            localFilters.value,
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    }

    function saveDraft() {
        router.post(
            route("inventario.branches.purchase-reports.store", {
                branch: props.currentBranch.id,
            }),
            {
                notes: notes.value,
                items: selectedProducts.value.map((item) => ({
                    branch_product_id: item.branch_product_id,
                    requested_quantity: item.requested_quantity,
                    notes: item.notes,
                })),
            },
        );
    }

    return {
        notes,
        selectedItems,
        localFilters,
        products,
        selectedProducts,
        selectedCount,
        totalQuantity,
        estimatedTotal,
        toggleProduct,
        updateItem,
        removeItem,
        applyFilters,
        saveDraft,
    };
}
