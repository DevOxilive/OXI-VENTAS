<script setup>
import { computed, ref } from "vue";

import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import FormPanel from "@/Components/Cards/FormPanel.vue";
import InputField from "@/Components/Forms/InputField.vue";
import SelectField from "@/Components/Forms/SelectField.vue";
import TextareaField from "@/Components/Forms/TextareaField.vue";
import ToggleSwitch from "@/Components/Forms/ToggleSwitch.vue";
import GlobalModal from "@/Components/Modales/GlobalModal.vue";
import GlobalToolbar from "@/Components/Toolbars/GlobalToolbar.vue";
import OrganizationTablePanel from "@/Components/HumanResourses/OrganizationTablePanel.vue";
import { usePermissions } from "@/Composables/usePermissions";
import { useOrganizationStructure } from "@/Composables/HumanResources/useOrganizationStructure";
import {
    departmentTableConfig,
    positionTableConfig,
} from "@/config/TableConfigs/organizationStructureTableConfig";
import { getOrganizationTableToolbarConfig } from "@/config/ToolbarConfigs/organizationStructureToolbarConfig";

defineOptions({ layout: AdminLayout });

const props = defineProps({
    departments: {
        type: Array,
        default: () => [],
    },
    positions: {
        type: Array,
        default: () => [],
    },
});

const { can } = usePermissions();
const departmentSearch = ref("");
const positionSearch = ref("");

const {
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
} = useOrganizationStructure(props);

const departmentToolbarConfig = computed(() => getOrganizationTableToolbarConfig({
    entity: "department",
    canCreate: can("departments.create"),
}));

const positionToolbarConfig = computed(() => getOrganizationTableToolbarConfig({
    entity: "position",
    canCreate: can("positions.create"),
}));

function visibleActions(config) {
    return config.actions.map((action) => ({
        ...action,
        hidden: () => !can(action.permission),
    }));
}

const departmentActions = computed(() => visibleActions(departmentTableConfig));
const positionActions = computed(() => visibleActions(positionTableConfig));

const filteredDepartments = computed(() => {
    const term = departmentSearch.value.trim().toLowerCase();
    if (!term) return props.departments;

    return props.departments.filter((department) => (
        [department.name]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(term))
    ));
});

const filteredPositions = computed(() => {
    const term = positionSearch.value.trim().toLowerCase();
    if (!term) return props.positions;

    return props.positions.filter((position) => (
        [position.name, position.departmentName, position.description, position.status]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(term))
    ));
});

const departmentOptions = computed(() => props.departments.map((department) => ({
    value: department.id,
    label: department.active ? department.name : `${department.name} (Inactivo)`,
})));

const isReadOnly = computed(() => modalMode.value === "view");
const departmentPositions = computed(() => selectedRecord.value?.positions || []);

const modalDescription = computed(() => {
    if (entity.value === "position") {
        return "Asigna el puesto a un departamento y describe su responsabilidad.";
    }

    if (modalMode.value === "view") {
        return "Consulta el nombre del departamento y los puestos que le pertenecen.";
    }

    return modalMode.value === "edit"
        ? "Modifica únicamente el nombre del departamento."
        : "Registra el nombre del nuevo departamento.";
});

function handleTableAction(targetEntity, { action, row }) {
    const permission = `${targetEntity === "department" ? "departments" : "positions"}.${
        action === "edit" ? "update" : action
    }`;

    if (!can(permission)) return;

    if (action === "delete") {
        deleteRecord(targetEntity, row);
        return;
    }

    openModal(targetEntity, action, row);
}
</script>

<template>
    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                title="Registro de Departamentos"
                subtitle="Administra departamentos y los puestos que pertenecen a cada uno"
                :show-search="false"
                :show-records-per-page="false"
                :show-counter="false"
            />
        </template>

        <div class="grid items-start gap-5 2xl:grid-cols-2">
            <OrganizationTablePanel
                :title="departmentToolbarConfig.title"
                :subtitle="departmentToolbarConfig.subtitle"
                :search-placeholder="departmentToolbarConfig.searchPlaceholder"
                :search="departmentSearch"
                :items="filteredDepartments"
                :total-records="departments.length"
                :table-config="departmentTableConfig"
                :table-actions="departmentActions"
                :toolbar-actions="departmentToolbarConfig.actions"
                @update:search="departmentSearch = $event"
                @create="openModal('department', 'create')"
                @action="handleTableAction('department', $event)"
            />

            <OrganizationTablePanel
                :title="positionToolbarConfig.title"
                :subtitle="positionToolbarConfig.subtitle"
                :search-placeholder="positionToolbarConfig.searchPlaceholder"
                :search="positionSearch"
                :items="filteredPositions"
                :total-records="positions.length"
                :table-config="positionTableConfig"
                :table-actions="positionActions"
                :toolbar-actions="positionToolbarConfig.actions"
                @update:search="positionSearch = $event"
                @create="openModal('position', 'create')"
                @action="handleTableAction('position', $event)"
            />
        </div>

        <GlobalModal
            v-if="showModal"
            v-bind="modalConfig"
            @save="submit"
            @close="closeModal"
        >
            <FormPanel
                :title="entity === 'department' ? 'Datos del departamento' : 'Datos del puesto'"
                :description="modalDescription"
                :heading-border="true"
                body-class="space-y-5"
            >
                <template v-if="entity === 'department'">
                    <InputField
                        v-model="departmentForm.name"
                        label="Nombre del departamento"
                        field="name"
                        placeholder="Ej. Capital Humano"
                        :readonly="isReadOnly"
                        :error="departmentForm.errors.name"
                    />

                    <div
                        v-if="isReadOnly"
                        class="rounded-2xl border border-secondary bg-secondary p-4"
                    >
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-semibold text-text">Puestos relacionados</p>
                                <p class="text-xs text-text opacity-65">Puestos que pertenecen a este departamento.</p>
                            </div>
                            <span class="rounded-full bg-background px-3 py-1 text-xs font-bold text-text">
                                {{ departmentPositions.length }}
                            </span>
                        </div>

                        <div v-if="departmentPositions.length" class="mt-4 grid gap-2 sm:grid-cols-2">
                            <div
                                v-for="position in departmentPositions"
                                :key="position.id"
                                class="rounded-xl border border-secondary bg-background px-3 py-2 text-sm font-semibold text-text"
                            >
                                {{ position.name }}
                            </div>
                        </div>
                        <p v-else class="mt-4 rounded-xl border border-dashed border-secondary bg-background px-3 py-4 text-center text-sm text-text opacity-65">
                            Este departamento todavía no tiene puestos registrados.
                        </p>
                    </div>
                </template>

                <template v-else>
                    <InputField
                        v-model="positionForm.name"
                        label="Nombre del puesto"
                        field="name"
                        placeholder="Ej. Auxiliar de Capital Humano"
                        :readonly="isReadOnly"
                        :error="positionForm.errors.name"
                    />

                    <SelectField
                        v-model="positionForm.departmentId"
                        label="Departamento"
                        field="departmentId"
                        :options="departmentOptions"
                        :disabled="isReadOnly"
                        :error="positionForm.errors.departmentId"
                    />

                    <TextareaField
                        v-model="positionForm.description"
                        label="Descripción"
                        field="description"
                        placeholder="Responsabilidades principales del puesto"
                        :readonly="isReadOnly"
                        :error="positionForm.errors.description"
                    />

                    <div class="flex items-center justify-between gap-4 rounded-2xl border border-secondary bg-secondary p-4">
                        <div>
                            <p class="text-sm font-semibold text-text">Puesto activo</p>
                            <p class="text-xs text-text opacity-65">Los puestos inactivos no se ofrecen en nuevos registros.</p>
                        </div>
                        <ToggleSwitch v-model="positionForm.active" :disabled="isReadOnly" />
                    </div>
                </template>
            </FormPanel>
        </GlobalModal>
    </PageLayout>
</template>
