import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { usePermissions } from "@/Composables/usePermissions";
import {
    UniversalActionModal,
    ToastAlert,
    ErrorAlert,
} from "@/Components/Modales/UniversalActionModal";

export function useEmployeeActions() {
    const { can } = usePermissions();

    const showModal = ref(false);
    const modalMode = ref("create");
    const selectedEmployee = ref(null);

    function openCreateModal() {
        if (!can("employees.create")) return;

        modalMode.value = "create";
        selectedEmployee.value = null;
        showModal.value = true;
    }

    function openEditModal(employee) {
        if (!can("employees.update")) return;

        modalMode.value = "edit";
        selectedEmployee.value = employee;
        showModal.value = true;
    }

    function openViewModal(employee) {
        if (!can("employees.view")) return;

        modalMode.value = "view";
        selectedEmployee.value = employee;
        showModal.value = true;
    }

    function closeModal() {
        showModal.value = false;
    }

    function deleteEmployee(employee) {
        if (!can("employees.delete") && !can("employees.delete")) return;

        UniversalActionModal({
            title: "Confirmar eliminación",
            message: "¿Deseas eliminar permanentemente a",
            itemName: `${employee.firstName} ${employee.lastName}`,
            confirmText: "Sí, eliminar",
        }).then((result) => {
            if (!result.isConfirmed) return;

            router.delete(route("rh.empleados.destroy", employee.id), {
                onSuccess: () => {
                    ToastAlert({
                        icon: "success",
                        title: "Empleado eliminado correctamente",
                    });
                },

                onError: () => {
                    ErrorAlert({
                        title: "Error al eliminar",
                        message: "No fue posible eliminar el empleado",
                    });
                },
            });
        });
    }

    return {
        showModal,
        modalMode,
        selectedEmployee,
        openViewModal,
        openCreateModal,
        openEditModal,
        deleteEmployee,
        closeModal,
    };
}
