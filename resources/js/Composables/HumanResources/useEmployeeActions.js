import { ref } from "vue";
import { router } from "@inertiajs/vue3";
import { usePermissions } from "@/Composables/usePermissions";
import { confirmModalAction, getModalRequestOptions } from "@/Components/Modales/useModalConfig";
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
        if (!can("employees.delete")) return;

        confirmModalAction({
            mode: "delete",
            entityName: "empleado",
            title: "Confirmar eliminación",
            message: `?Deseas eliminar permanentemente a ${employee.firstName} ${employee.lastName}?`,
            confirmText: "S?, eliminar",
        }).then((result) => {
            if (!result.isConfirmed) return;

            router.delete(
                route("human-resources.employees.destroy", employee.id),
                getModalRequestOptions({
                    mode: "delete",
                    entityName: "Empleado",
                    successTitle: "Empleado eliminado correctamente",
                    errorTitle: "Error al eliminar",
                    errorMessage: "No fue posible eliminar el empleado",
                }),
            );
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
