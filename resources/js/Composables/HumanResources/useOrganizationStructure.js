import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import {
    confirmModalAction,
    getModalRequestOptions,
} from "@/Components/Modales/useModalConfig";
import { getOrganizationStructureModalConfig } from "@/config/ModalConfigs/organizationStructureModalConfig";
import {
    REALTIME_CHANNELS,
    REALTIME_EVENTS,
    subscribeRealtime,
} from "@/realtime";

export function useOrganizationStructure(props) {
    const showModal = ref(false);
    const modalMode = ref("create");
    const entity = ref("department");
    const selectedRecord = ref(null);
    let unsubscribeStructureChanged = null;

    const departmentForm = useForm({
        name: "",
    });

    const positionForm = useForm({
        name: "",
        description: "",
        departmentId: "",
        active: true,
    });

    const activeForm = computed(() => (
        entity.value === "department" ? departmentForm : positionForm
    ));

    const modalConfig = computed(() => getOrganizationStructureModalConfig({
        entity: entity.value,
        mode: modalMode.value,
        processing: Boolean(activeForm.value.processing),
        totalErrors: Object.keys(activeForm.value.errors || {}).length,
    }));

    function resetForm(targetEntity) {
        const form = targetEntity === "department" ? departmentForm : positionForm;
        form.reset();
        form.clearErrors();
        if (targetEntity === "position") form.active = true;
    }

    function fillForm(targetEntity, record) {
        if (targetEntity === "department") {
            departmentForm.name = record?.name || "";
            return;
        }

        positionForm.name = record?.name || "";
        positionForm.description = record?.description || "";
        positionForm.departmentId = record?.departmentId || "";
        positionForm.active = record?.active ?? true;
    }

    function openModal(targetEntity, mode, record = null) {
        entity.value = targetEntity;
        modalMode.value = mode;
        selectedRecord.value = record;
        resetForm(targetEntity);
        fillForm(targetEntity, record);
        showModal.value = true;
    }

    function closeModal() {
        showModal.value = false;
        activeForm.value.clearErrors();
    }

    function submit() {
        const isDepartment = entity.value === "department";
        const form = activeForm.value;
        const isEditing = modalMode.value === "edit";
        const routeName = isDepartment
            ? (isEditing ? "human-resources.departments.update" : "human-resources.departments.store")
            : (isEditing ? "human-resources.positions.update" : "human-resources.positions.store");
        const routeParams = isEditing ? selectedRecord.value.id : undefined;
        const entityName = isDepartment ? "Departamento" : "Puesto";
        const options = getModalRequestOptions({
            mode: isEditing ? "edit" : "create",
            entityName,
            close: closeModal,
            errorMessage: isDepartment
                ? "Revisa el nombre del departamento."
                : "Revisa el nombre y el departamento seleccionado.",
        });

        if (isEditing) {
            form.put(route(routeName, routeParams), options);
            return;
        }

        form.post(route(routeName), options);
    }

    async function deleteRecord(targetEntity, record) {
        const isDepartment = targetEntity === "department";
        const entityName = isDepartment ? "departamento" : "puesto";
        const result = await confirmModalAction({
            mode: "delete",
            entityName,
            title: `Eliminar ${entityName}`,
            message: `¿Deseas eliminar ${record.name}?`,
            confirmText: "Sí, eliminar",
        });

        if (!result.isConfirmed) return;

        router.delete(
            route(
                isDepartment
                    ? "human-resources.departments.destroy"
                    : "human-resources.positions.destroy",
                record.id,
            ),
            getModalRequestOptions({
                mode: "delete",
                entityName: isDepartment ? "Departamento" : "Puesto",
                errorMessage: isDepartment
                    ? "El departamento debe quedar sin puestos antes de eliminarlo."
                    : "El puesto debe quedar sin empleados antes de eliminarlo.",
            }),
        );
    }

    function refreshStructure(event = null) {
        if (
            event?.action === "deleted"
            && event?.entity === entity.value
            && Number(event?.entityId) === Number(selectedRecord.value?.id)
        ) {
            closeModal();
            selectedRecord.value = null;
        }

        router.reload({
            only: ["departments", "positions"],
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (!showModal.value || !selectedRecord.value?.id) return;

                const records = entity.value === "department"
                    ? props.departments
                    : props.positions;
                const updatedRecord = records.find((record) => (
                    Number(record.id) === Number(selectedRecord.value.id)
                ));

                if (!updatedRecord) return;

                selectedRecord.value = updatedRecord;
                fillForm(entity.value, updatedRecord);
            },
        });
    }

    onMounted(() => {
        unsubscribeStructureChanged = subscribeRealtime(
            REALTIME_CHANNELS.systems,
            REALTIME_EVENTS.organizationStructureChanged,
            refreshStructure,
        );
    });

    onBeforeUnmount(() => {
        unsubscribeStructureChanged?.();
    });

    return {
        showModal,
        modalMode,
        entity,
        selectedRecord,
        departmentForm,
        positionForm,
        modalConfig,
        openModal,
        closeModal,
        submit,
        deleteRecord,
    };
}
