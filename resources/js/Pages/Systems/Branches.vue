<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue"
import { router, useForm } from "@inertiajs/vue3"

import AdminLayout from "@/Layouts/AdminLayout.vue"
import PageLayout from "@/Layouts/PageLayout.vue"
import { GlobalModal, confirmModalAction, getModalRequestOptions } from "@/Components/Modales"
import { GlobalTable } from "@/Components/Tables"
import { GlobalToolbar } from "@/Components/Toolbars"
import FormPanel from "@/Components/Cards/FormPanel.vue"
import ColorField from "@/Components/Forms/ColorField.vue"
import InputField from "@/Components/Forms/InputField.vue"
import { usePermissions } from "@/Composables/usePermissions"
import { getBranchModalConfig } from "@/config/ModalConfigs/branchModalConfig"
import { branchTableConfig } from "@/config/TableConfigs/branchTableConfig"
import { getBranchToolbarConfig } from "@/config/ToolbarConfigs/branchToolbarConfig"

const props = defineProps({
  branches: {
    type: Array,
    default: () => [],
  },
})

defineOptions({
  layout: AdminLayout,
})

const { can } = usePermissions()

const search = ref("")
const selectedBranch = ref(null)
const modalMode = ref("create")
const showCreateModal = ref(false)
let systemsChannel = null

const form = useForm({
  name: "",
  color: "#facc15",
})

const toolbarConfig = computed(() =>
  getBranchToolbarConfig({
    canCreate: can("branches.create"),
  }),
)

const normalizedBranches = computed(() =>
  props.branches.map((branch) => ({
    ...branch,
    color: branch.color || "",
  })),
)

const filteredBranches = computed(() => {
  const term = search.value.trim().toLowerCase()

  if (!term) {
    return normalizedBranches.value
  }

  return normalizedBranches.value.filter((branch) => {
    return [branch.name, branch.slug, branch.color]
      .filter(Boolean)
      .some((value) => String(value).toLowerCase().includes(term))
  })
})

const modalConfig = computed(() =>
  getBranchModalConfig({
    mode: modalMode.value,
    totalErrors: Object.keys(form.errors || {}).length,
    processing: Boolean(form.processing),
  }),
)

const branchActions = computed(() =>
  branchTableConfig.actions.map((action) => ({
    ...action,
    hidden: (row) => {
      if (typeof action.hidden === "function" && action.hidden(row)) {
        return true
      }

      if (Array.isArray(action.permission)) {
        return !action.permission.some((permission) => can(permission))
      }

      if (action.permission) {
        return !can(action.permission)
      }

      return false
    },
  })),
)

function resetForm() {
  form.reset()
  form.clearErrors()
  form.color = "#facc15"
}

function openCreateModal() {
  selectedBranch.value = null
  modalMode.value = "create"
  resetForm()
  showCreateModal.value = true
}

function viewBranch(branch) {
  selectedBranch.value = branch
  modalMode.value = "view"
  form.name = branch.name
  form.color = branch.color || "#facc15"
  showCreateModal.value = true
}

function editBranch(branch) {
  selectedBranch.value = branch
  modalMode.value = "edit"
  form.name = branch.name
  form.color = branch.color || "#facc15"
  showCreateModal.value = true
}

async function deleteBranch(branch) {
  const result = await confirmModalAction({
    mode: "delete",
    entityName: "sucursal",
    title: "Eliminar sucursal",
    message: `¿Deseas eliminar ${branch.name}?`,
    confirmText: "Sí, eliminar",
  })

  if (!result.isConfirmed) return

  form.delete(route("branches.destroy", branch.id), getModalRequestOptions({
    mode: "delete",
    entityName: "Sucursal",
    successTitle: "Sucursal eliminada correctamente",
    errorTitle: "Error al eliminar sucursal",
    errorMessage: "No fue posible eliminar la sucursal.",
  }))
}

function closeCreateModal() {
  showCreateModal.value = false
  form.clearErrors()
}

function reloadBranches(event = null) {
  if (
    event?.action === "deleted" &&
    Number(event.branchId) === Number(selectedBranch.value?.id)
  ) {
    closeCreateModal()
    selectedBranch.value = null
  }

  router.reload({
    only: ["branches"],
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      if (!selectedBranch.value?.id || modalMode.value === "create") return

      const updatedBranch = normalizedBranches.value.find((branch) => {
        return branch.id === selectedBranch.value.id
      })

      if (!updatedBranch) return

      selectedBranch.value = updatedBranch
      form.name = updatedBranch.name
      form.color = updatedBranch.color || "#facc15"
    },
  })
}

function submit() {
  if (modalMode.value === "view") {
    closeCreateModal()
    return
  }

  if (modalMode.value === "edit") {
    form.put(route("branches.update", selectedBranch.value.id), getModalRequestOptions({
      mode: "edit",
      entityName: "Sucursal",
      close: closeCreateModal,
      successTitle: "Sucursal actualizada correctamente",
      errorTitle: "Error al actualizar sucursal",
      errorMessage: "No fue posible actualizar la sucursal.",
    }))

    return
  }

  form.post(route("branches.store"), getModalRequestOptions({
    mode: "create",
    entityName: "Sucursal",
    close: closeCreateModal,
    successTitle: "Sucursal creada correctamente",
    errorTitle: "Error al crear sucursal",
    errorMessage: "Revisa los datos capturados.",
    onSuccess: () => {
      form.reset("name")
      form.color = "#facc15"
    },
  }))
}

function handleToolbarAction(action) {
  if (action === "create") {
    openCreateModal()
  }
}

function handleTableAction({ action, row }) {
  if (action === "view" && can("branches.view")) {
    viewBranch(row)
  }

  if (action === "edit" && can("branches.update")) {
    editBranch(row)
  }

  if (action === "delete" && can("branches.delete")) {
    deleteBranch(row)
  }
}

function handleRowClick(branch) {
  if (can("branches.view")) {
    viewBranch(branch)
  }
}

onMounted(() => {
  if (!window.Echo) return

  systemsChannel = window.Echo.channel("systems")
    .listen(".branch.changed", reloadBranches)
})

onBeforeUnmount(() => {
  if (!systemsChannel) return

  systemsChannel.stopListening(".branch.changed")
})
</script>

<template>
  <PageLayout>
    <template #toolbar>
      <GlobalToolbar
        :title="toolbarConfig.title"
        :subtitle="toolbarConfig.subtitle"
        :search="search"
        :search-placeholder="toolbarConfig.searchPlaceholder"
        :show-search="toolbarConfig.showSearch"
        :actions="toolbarConfig.actions"
        :show-records-per-page="toolbarConfig.showRecordsPerPage"
        :total-records="normalizedBranches.length"
        :filtered-records="filteredBranches.length"
        @update:search="search = $event"
        @action="handleToolbarAction"
      />
    </template>

    <GlobalTable
      :items="filteredBranches"
      :columns="branchTableConfig.columns"
      :actions="branchActions"
      :row-key="branchTableConfig.rowKey"
      :no-data-message="branchTableConfig.noDataMessage"
      :mobile-card-header-field="branchTableConfig.mobileCardHeaderField"
      @action="handleTableAction"
      @row-click="handleRowClick"
    />

    <GlobalModal
      v-if="showCreateModal"
      v-bind="modalConfig"
      @save="submit"
      @close="closeCreateModal"
    >
      <form @submit.prevent="submit">
        <FormPanel
          title="Datos de la sucursal"
          description="Captura el nombre y el color que identificara visualmente a la sucursal."
          :heading-border="true"
          body-class="space-y-5"
        >
          <InputField
            v-model="form.name"
            label="Nombre de sucursal"
            field="name"
            :readonly="modalMode === 'view' || (modalMode === 'edit' && !can('branches.update'))"
            placeholder="Ej. Ajusco"
            :error="form.errors.name"
          />

          <ColorField
            v-model="form.color"
            label="Color"
            field="color"
            :disabled="modalMode === 'view' || (modalMode === 'edit' && !can('branches.update'))"
            :error="form.errors.color"
          />
        </FormPanel>
      </form>
    </GlobalModal>
  </PageLayout>
</template>
