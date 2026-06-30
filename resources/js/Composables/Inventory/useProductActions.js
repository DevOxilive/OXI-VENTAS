import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { confirmModalAction, getModalRequestOptions } from "@/Components/Modales/useModalConfig";
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

    async function deleteProduct(product) {
        const result = await confirmModalAction({
            mode: "delete",
            entityName: "producto",
            title: "Eliminar producto",
            message: `?Deseas eliminar ${product.name}?`,
            confirmText: "S?, eliminar",
        });

        if (!result.isConfirmed) return;

        router.delete(
            route("inventory.branches.products.destroy", {
                branch: product.branch_slug ?? product.branch?.slug,
                product: product.id,
            }),
            getModalRequestOptions({
                mode: "delete",
                entityName: "Producto",
                successTitle: "Producto eliminado correctamente",
                errorTitle: "Error al eliminar",
                errorMessage: "No fue posible eliminar el producto.",
            }),
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
