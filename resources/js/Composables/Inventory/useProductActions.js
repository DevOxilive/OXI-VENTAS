import { ref } from "vue";
import { router } from "@inertiajs/vue3";

export function useProductActions() {
    const showModal = ref(false);
    const modalMode = ref("create");
    const selectedProduct = ref(null);

    function openCreateModal() {
        selectedProduct.value = null;
        modalMode.value = "create";
        showModal.value = true;
    }

    function openViewModal(product) {
        selectedProduct.value = product;
        modalMode.value = "view";
        showModal.value = true;
    }

    function openEditModal(product) {
        selectedProduct.value = product;
        modalMode.value = "edit";
        showModal.value = true;
    }

    function closeModal() {
        showModal.value = false;
        selectedProduct.value = null;
    }

   function deleteProduct(product) {
    if (!confirm(`¿Eliminar el producto "${product.name}"?`)) return;

    router.delete(
        route("inventory.branches.products.destroy", {
            branch: product.branch_slug,
            product: product.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                console.log("Producto eliminado");
            },
        }
    );
}

    return {
        showModal,
        modalMode,
        selectedProduct,
        openCreateModal,
        openViewModal,
        openEditModal,
        closeModal,
        deleteProduct,
    };
}
