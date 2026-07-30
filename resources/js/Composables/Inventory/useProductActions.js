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
        const branchName = product.branch_name ?? product.branch?.name ?? "esta sucursal";
        const result = await confirmModalAction({
            showDenyButton: true,
            denyButtonText: "De todas las sucursales",
            denyButtonColor: "#ef4444",
            mode: "delete",
            entityName: "producto",
            title: "Eliminar producto",
            message: `Selecciona cómo deseas retirar ${product.name}.`,
            confirmText: `Solo de ${branchName}`,
        });

        if (!result.isConfirmed && !result.isDenied) return;

        const deleteGlobally = result.isDenied;

        router.delete(
            route("inventory.branches.products.destroy", {
                branch: product.branch_slug ?? product.branch?.slug,
                product: product.id,
            }),
            getModalRequestOptions({
                data: {
                    delete_globally: deleteGlobally,
                },
                mode: "delete",
                entityName: "Producto",
                successTitle: deleteGlobally
                    ? "Producto eliminado de todas las sucursales"
                    : `Producto retirado de ${branchName}`,
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
