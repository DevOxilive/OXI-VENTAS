<script setup>
import { computed, ref } from "vue";
import { Head } from "@inertiajs/vue3";

import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import FormPanel from "@/Components/Cards/FormPanel.vue";
import InputField from "@/Components/Forms/InputField.vue";
import SelectField from "@/Components/Forms/SelectField.vue";
import TextareaField from "@/Components/Forms/TextareaField.vue";
import ToggleSwitch from "@/Components/Forms/ToggleSwitch.vue";
import GlobalModal from "@/Components/Modales/GlobalModal.vue";
import GlobalToolbar from "@/Components/Toolbars/GlobalToolbar.vue";
import GlobalTable from "@/Components/Tables/GlobalTable.vue";
import { usePermissions } from "@/Composables/usePermissions";
import { useOrganizationStructure } from "@/Composables/HumanResources/useOrganizationStructure";
import {
    departmentTableConfig,
    positionTableConfig,
} from "@/config/TableConfigs/organizationStructureTableConfig";

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
const activeSection = ref(can("departments.view") ? "departments" : "positions");
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

const isDepartmentSection = computed(() => activeSection.value === "departments");
const sectionTabs = computed(() => [
    can("departments.view") && {
        key: "departments",
        label: "Departamentos",
        icon: "domain",
    },
    can("positions.view") && {
        key: "positions",
        label: "Puestos",
        icon: "badge",
    },
].filter(Boolean));
const activeRows = computed(() => (
    isDepartmentSection.value ? filteredDepartments.value : filteredPositions.value
));
const activeTotalRecords = computed(() => (
    isDepartmentSection.value ? props.departments.length : props.positions.length
));
const activeTableConfig = computed(() => (
    isDepartmentSection.value ? departmentTableConfig : positionTableConfig
));
const activeTableActions = computed(() => (
    isDepartmentSection.value ? departmentActions.value : positionActions.value
));
const activeSearch = computed(() => (
    isDepartmentSection.value ? departmentSearch.value : positionSearch.value
));
const activeCreatePermission = computed(() => (
    isDepartmentSection.value ? can("departments.create") : can("positions.create")
));
const toolbarConfig = computed(() => ({
    icon: "account_tree",
    title: "Registro de Departamentos",
    subtitle: isDepartmentSection.value
        ? "Administra los departamentos internos de Capital Humano."
        : "Administra los puestos disponibles y su relación con cada departamento.",
    search: activeSearch.value,
    searchPlaceholder: isDepartmentSection.value
        ? "Buscar departamento..."
        : "Buscar puesto o departamento...",
    showSearch: true,
    compactFilters: true,
    filters: [],
    actions: [
        {
            id: "create",
            label: isDepartmentSection.value ? "Dar de alta departamento" : "Dar de alta puesto",
            icon: isDepartmentSection.value ? "domain_add" : "person_add",
            variant: "primary",
            hidden: () => !activeCreatePermission.value,
        },
    ],
    tabs: sectionTabs.value,
    activeTab: activeSection.value,
    showRecordsPerPage: false,
    totalRecords: activeTotalRecords.value,
    filteredRecords: activeRows.value.length,
    showCounter: true,
}));

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

function updateSection(section) {
    if (!sectionTabs.value.some((tab) => tab.key === section)) return;
    activeSection.value = section;
}

function updateSearch(value) {
    if (isDepartmentSection.value) {
        departmentSearch.value = value;
        return;
    }

    positionSearch.value = value;
}

function handleToolbarAction(action) {
    if (action !== "create") return;
    openModal(isDepartmentSection.value ? "department" : "position", "create");
}

function handleActiveTableAction(event) {
    handleTableAction(isDepartmentSection.value ? "department" : "position", event);
}
</script>

<template>
    <Head title="Registro de Departamentos" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar
                v-bind="toolbarConfig"
                @update:search="updateSearch"
                @update:active-tab="updateSection"
                @action="handleToolbarAction"
            />
        </template>

        <section class="space-y-5">
            <GlobalTable
                :items="activeRows"
                :columns="activeTableConfig.columns"
                :actions="activeTableActions"
                :row-key="activeTableConfig.rowKey"
                :no-data-message="activeTableConfig.noDataMessage"
                :mobile-card-header-field="activeTableConfig.mobileCardHeaderField"
                :show-pagination="false"
                @action="handleActiveTableAction"
            />
        </section>

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
