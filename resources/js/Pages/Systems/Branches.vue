<script setup>
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { computed, ref } from "vue";
import { useForm } from "@inertiajs/vue3";
import ActionIconButton from "@/Components/Forms/ActionIconButton.vue";
import {
  GlobalModal,
  confirmModalAction,
  getModalRequestOptions,
} from "@/Components/Modales";
import { usePermissions } from "@/Composables/usePermissions";
import { getBranchModalConfig } from "@/config/ModalConfigs/branchModalConfig";

const props = defineProps({
  branches: {
    type: Array,
    default: () => [],
  },
});

defineOptions({
  layout: AdminLayout,
});

const { can } = usePermissions();

const selectedBranch = ref(null);
const modalMode = ref("create");
const showCreateModal = ref(false);

const form = useForm({
  name: "",
  color: "#facc15",
});

const modalConfig = computed(() => getBranchModalConfig({
  mode: modalMode.value,
  totalErrors: Object.keys(form.errors || {}).length,
  processing: Boolean(form.processing),
}));

function viewBranch(branch) {
  selectedBranch.value = branch;
  modalMode.value = "view";

  form.name = branch.name;
  form.color = branch.color;

  showCreateModal.value = true;
}

function editBranch(branch) {
  selectedBranch.value = branch;
  modalMode.value = "edit";

  form.name = branch.name;
  form.color = branch.color;

  showCreateModal.value = true;
}

async function deleteBranch(branch) {
  const result = await confirmModalAction({
    mode: "delete",
    entityName: "sucursal",
    title: "Eliminar sucursal",
    message: `¿Deseas eliminar ${branch.name}?`,
    confirmText: "Sí, eliminar",
  });

  if (!result.isConfirmed) return;

  form.delete(route("branches.destroy", branch.id), getModalRequestOptions({
    mode: "delete",
    entityName: "Sucursal",
    successTitle: "Sucursal eliminada correctamente",
    errorTitle: "Error al eliminar sucursal",
    errorMessage: "No fue posible eliminar la sucursal.",
  }));
}

function openCreateModal() {
  selectedBranch.value = null;
  modalMode.value = "create";

  form.reset();
  form.clearErrors();
  form.color = "#facc15";

  showCreateModal.value = true;
}

function closeCreateModal() {
  showCreateModal.value = false;
  form.clearErrors();
}

function submit() {
  if (modalMode.value === "view") {
    closeCreateModal();
    return;
  }

  if (modalMode.value === "edit") {
    form.put(route("branches.update", selectedBranch.value.id), getModalRequestOptions({
      mode: "edit",
      entityName: "Sucursal",
      close: closeCreateModal,
      successTitle: "Sucursal actualizada correctamente",
      errorTitle: "Error al actualizar sucursal",
      errorMessage: "No fue posible actualizar la sucursal.",
    }));

    return;
  }

  form.post(route("branches.store"), getModalRequestOptions({
    mode: "create",
    entityName: "Sucursal",
    close: closeCreateModal,
    successTitle: "Sucursal creada correctamente",
    errorTitle: "Error al crear sucursal",
    errorMessage: "Revisa los datos capturados.",
    onSuccess: () => {
      form.reset("name");
      form.color = "#facc15";
    },
  }));
}

function updateColor(branch, color) {
  if (!can('branches.update')) return

  const updateForm = useForm({
    name: branch.name,
    color,
    active: branch.active ?? true,
  })

  updateForm.put(route("branches.update", branch.id), getModalRequestOptions({
    mode: "edit",
    entityName: "Sucursal",
    successTitle: "Color actualizado correctamente",
    errorTitle: "Error al actualizar color",
    errorMessage: "No fue posible actualizar el color de la sucursal.",
  }))
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between rounded-2xl border bg-white p-5 shadow-sm md:p-8">
      <div>
        <h1 class="text-xl font-bold text-slate-700 md:text-3xl">
          Registro de Sucursales
        </h1>

        <p class="mt-2 text-sm text-slate-500">
          Administra las sucursales y asigna un color de identificacion.
        </p>
      </div>

      <button
        v-if="can('branches.create')"
        type="button"
        class="rounded-2xl bg-black px-5 py-3 font-semibold text-white transition hover:bg-slate-800"
        @click="openCreateModal"
      >
        + Agregar sucursal
      </button>
    </div>

    <div class="rounded-2xl border bg-white p-5 shadow-sm md:p-6">
      <h2 class="mb-4 text-lg font-semibold text-slate-700">
        Sucursales registradas
      </h2>

      <div class="space-y-3">
        <div
          v-for="branch in branches"
          :key="branch.id"
          class="grid grid-cols-[1fr_auto] items-center gap-4 rounded-xl border p-4"
        >
          <div class="flex items-center gap-3">
            <div
              class="h-5 w-5 rounded-full border"
              :style="{ backgroundColor: branch.color || '#e5e7eb' }"
            />

            <div>
              <p class="font-semibold text-slate-700">
                {{ branch.name }}
              </p>

              <p class="text-xs text-slate-400">
                {{ branch.slug }}
              </p>
            </div>
          </div>

          <div class="flex items-center justify-end gap-2">
            <ActionIconButton
              v-if="can('branches.view')"
              icon="visibility"
              title="Ver sucursal"
              variant="blue"
              @click.stop="viewBranch(branch)"
            />

            <ActionIconButton
              v-if="can('branches.update')"
              icon="edit"
              title="Editar sucursal"
              variant="amber"
              @click.stop="editBranch(branch)"
            />

            <ActionIconButton
              v-if="can('branches.delete')"
              icon="delete"
              title="Eliminar sucursal"
              variant="red"
              @click.stop="deleteBranch(branch)"
            />
          </div>
        </div>

        <div v-if="!branches.length" class="py-10 text-center text-slate-400">
          No hay sucursales registradas.
        </div>
      </div>
    </div>

    <GlobalModal
      v-if="showCreateModal"
      v-bind="modalConfig"
      @save="submit"
      @close="closeCreateModal"
    >
      <form class="space-y-5" @submit.prevent="submit">
        <div>
          <label class="mb-2 block text-sm font-semibold text-slate-600">
            Nombre de sucursal
          </label>

          <input
            v-model="form.name"
            :disabled="modalMode === 'view' || (modalMode === 'edit' && !can('branches.update'))"
            type="text"
            class="w-full rounded-xl border-slate-300"
            placeholder="Ej. Ajusco"
          >

          <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">
            {{ form.errors.name }}
          </p>
        </div>

        <div>
          <label class="mb-2 block text-sm font-semibold text-slate-600">
            Color
          </label>

          <input
            v-model="form.color"
            :disabled="modalMode === 'view' || (modalMode === 'edit' && !can('branches.update'))"
            type="color"
            class="h-12 w-24 cursor-pointer rounded-lg border"
          >
        </div>
      </form>
    </GlobalModal>
  </div>
</template>
