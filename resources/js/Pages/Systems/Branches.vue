<script setup>
import AdminLayout from "@/Layouts/AdminLayout.vue";
import { ref } from "vue";
import { useForm, router } from "@inertiajs/vue3";
import ActionIconButton from "@/Components/Forms/ActionIconButton.vue";
import { usePermissions } from "@/Composables/usePermissions";
import {
  UniversalActionModal,
  ToastAlert,
  ErrorAlert,
} from "@/Components/Modales/UniversalActionModal";
const { can } = usePermissions();

const selectedBranch = ref(null);
const modalMode = ref("create");

function viewBranch(branch) {
  selectedBranch.value = branch;
  modalMode.value = "view";
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
  const result = await UniversalActionModal({
    title: "Eliminar sucursal",
    message: "¿Deseas eliminar",
    itemName: branch.name,
    confirmText: "Sí, eliminar",
  });

  if (!result.isConfirmed) return;

  router.delete(route("systems.branches.destroy", branch.id), {
    preserveScroll: true,

    onSuccess: () => {
      ToastAlert({
        title: "Sucursal eliminada correctamente",
      });
    },

    onError: () => {
      ErrorAlert({
        title: "Error",
        message: "No fue posible eliminar la sucursal",
      });
    },
  });
}

const props = defineProps({
  branches: {
    type: Array,
    default: () => [],
  },
});

const form = useForm({
  name: "",
  color: "#facc15",
});
const showCreateModal = ref(false);

function openCreateModal() {
  selectedBranch.value = null;
  modalMode.value = "create";
  form.reset();
  form.color = "#facc15";
  showCreateModal.value = true;
}

function closeCreateModal() {
  showCreateModal.value = false;
}
function submit() {
  if (modalMode.value === "edit") {
    form.put(route("systems.branches.update", selectedBranch.value.id), {
      preserveScroll: true,

      onSuccess: () => {
        ToastAlert({
          title: "Sucursal actualizada correctamente",
        });

        closeCreateModal();
      },

      onError: () => {
        ErrorAlert({
          title: "Error al actualizar",
          message: "No fue posible actualizar la sucursal",
        });
      },
    });

    return;
  }

  form.post(route("systems.branches.store"), {
    preserveScroll: true,

    onSuccess: () => {
      ToastAlert({
        title: "Sucursal creada correctamente",
      });

      form.reset("name");
      form.color = "#facc15";

      closeCreateModal();
    },

    onError: () => {
      ErrorAlert({
        title: "Error al crear sucursal",
        message: "Revisa los datos capturados",
      });
    },
  });
}

function updateColor(branch, color) {
  const updateForm = useForm({
    name: branch.name,
    color,
    active: branch.active ?? true,
  });

  updateForm.put(route("systems.branches.update", branch.id), {
    preserveScroll: true,
  });
}
</script>
<template>
  <AdminLayout>
    <div class="space-y-6">
      <!-- Header -->
      <div
        class="bg-white p-5 md:p-8 rounded-2xl shadow-sm border flex items-center justify-between"
      >
        <div>
          <h1 class="text-xl md:text-3xl font-bold text-slate-700">
            Registro de Sucursales
          </h1>

          <p class="text-sm text-slate-500 mt-2">
            Administra las sucursales y asigna un color de identificación.
          </p>
        </div>

        <button
          type="button"
          @click="openCreateModal"
          class="px-5 py-3 rounded-2xl bg-black text-white font-semibold hover:bg-slate-800 transition"
        >
          + Agregar sucursal
        </button>
      </div>
      <!-- Tabla -->
      <div class="bg-white p-5 md:p-6 rounded-2xl shadow-sm border">
        <h2 class="text-lg font-semibold text-slate-700 mb-4">
          Sucursales registradas
        </h2>

        <div class="space-y-3">
          <div
            v-for="branch in branches"
            :key="branch.id"
            class="grid grid-cols-[1fr_auto] items-center border rounded-xl p-4 gap-4"
          >
            <div class="flex items-center gap-3">
              <div
                class="w-5 h-5 rounded-full border"
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

            <!-- ACCIONES -->
            <div class="flex items-center justify-end gap-2">
              <ActionIconButton
                icon="visibility"
                title="Ver sucursal"
                variant="blue"
                @click.stop="viewBranch(branch)"
              />

              <ActionIconButton
                icon="edit"
                title="Editar sucursal"
                variant="amber"
                @click.stop="editBranch(branch)"
              />

              <ActionIconButton
                icon="delete"
                title="Eliminar sucursal"
                variant="red"
                @click.stop="deleteBranch(branch)"
              />
            </div>
          </div>

          <div v-if="!branches.length" class="text-center text-slate-400 py-10">
            No hay sucursales registradas.
          </div>
        </div>
      </div>

      <!-- Acciones -->
      <!--  <div class="flex items-center justify-center gap-2">

                <ActionIconButton
                    v-if="can('sucursales.ver')"
                    icon="visibility"
                    title="Ver sucursal"
                    variant="blue"
                    @click.stop="viewBranch(branch)"
                />

                <ActionIconButton
                    v-if="can('sucursales.editar')"
                    icon="edit"
                    title="Editar sucursal"
                    variant="amber"
                    @click.stop="editBranch(branch)"
                />

                <ActionIconButton
                    v-if="can('sucursales.eliminar')"
                    icon="delete"
                    title="Eliminar sucursal"
                    variant="red"
                    @click.stop="deleteBranch(branch)"
                />-->

      <!-- Modal crear sucursal -->
      <div
        v-if="showCreateModal"
        class="fixed inset-0 z-[9999] bg-black/40 flex items-center justify-center p-4"
      >
        <div
          class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden"
        >
          <div class="flex items-center justify-between px-6 py-5 border-b">
            {{
              modalMode === "create"
                ? "Nueva sucursal"
                : modalMode === "edit"
                ? "Editar sucursal"
                : "Ver sucursal"
            }}

            <button
              type="button"
              @click="closeCreateModal"
              class="text-slate-400 hover:text-slate-700 text-2xl"
            >
              ×
            </button>
          </div>

          <form @submit.prevent="submit" class="p-6 space-y-5">
            <div>
              <label class="block text-sm font-semibold text-slate-600 mb-2">
                Nombre de sucursal
              </label>

              <input
                v-model="form.name"
                :disabled="modalMode === 'view'"
                type="text"
                class="w-full rounded-xl border-slate-300"
                placeholder="Ej. Ajusco"
              />

              <p v-if="form.errors.name" class="text-red-500 text-xs mt-1">
                {{ form.errors.name }}
              </p>
            </div>

            <div>
              <label class="block text-sm font-semibold text-slate-600 mb-2">
                Color
              </label>

              <input
                v-model="form.color"
                :disabled="modalMode === 'view'"
                type="color"
                class="h-12 w-24 cursor-pointer rounded-lg border"
              />
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t">
              <button
                type="button"
                @click="closeCreateModal"
                class="px-5 py-3 rounded-2xl bg-slate-200 text-slate-700 font-semibold"
              >
                Cancelar
              </button>

              <button
                v-if="modalMode !== 'view'"
                type="submit"
                :disabled="form.processing"
                class="px-5 py-3 rounded-2xl bg-slate-900 text-white font-semibold disabled:opacity-50"
              >
                {{
                  modalMode === "edit"
                    ? "Actualizar sucursal"
                    : "Guardar sucursal"
                }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>