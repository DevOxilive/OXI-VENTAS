import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import {
    UniversalActionModal,
    ToastAlert,
    ErrorAlert,
} from "@/Components/Modales/UniversalActionModal";

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
        const result = await UniversalActionModal({
            title: "Eliminar producto",
            message: "¿Deseas eliminar",
            itemName: product.name,
            confirmText: "Sí, eliminar",
            icon: "warning",
        });

        if (!result.isConfirmed) return;

        router.delete(
            route("inventory.branches.products.destroy", {
                branch: product.branch_slug ?? product.branch_id,
                product: product.id,
            }),
            {
                preserveScroll: true,

                onSuccess: () => {
                    ToastAlert({
                        title: "Producto eliminado correctamente",
                    });
                },

                onError: () => {
                    ErrorAlert({
                        title: "Error al eliminar",
                        message: "No fue posible eliminar el producto.",
                    });
                },
            },
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
