<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageLayout from "@/Layouts/PageLayout.vue";
import { GlobalToolbar } from "@/Components/Toolbars";
import { GlobalTable } from "@/Components/Tables";
import { GlobalModal, confirmModalAction } from "@/Components/Modales";
import { ErrorAlert, ToastAlert } from "@/Components/Modales/UniversalActionModal";
import GlobalCard from "@/Components/Cards/GlobalCard.vue";
import FormPanel from "@/Components/Cards/FormPanel.vue";
import MetricCard from "@/Components/Cards/MetricCard.vue";
import InputField from "@/Components/Forms/InputField.vue";
import TextareaField from "@/Components/Forms/TextareaField.vue";
import { usePermissions } from "@/Composables/usePermissions";

defineOptions({
  layout: AdminLayout,
});

const { can } = usePermissions();
const props = defineProps({
  selectorMode: { type: Boolean, default: false },
  currentBranch: { type: Object, default: null },
  branchesDB: { type: Array, default: () => [] },
  closures: { type: Object, required: true },
  summary: { type: Object, required: true },
});

const rows = computed(() => props.closures?.data || []);
const selectedClosure = ref(null);
const modalMode = ref("view");
const showClosureModal = ref(false);

const form = useForm({
  counted_cash: 0,
  cash_left: 0,
  counted_card: 0,
  notes: "",
});

const isSelectedBalanced = computed(() => {
  if (!selectedClosure.value) return false;

  return (
    Math.abs(Number(editableCashDifference.value || 0)) < 0.01
    && Math.abs(Number(editableCardDifference.value || 0)) < 0.01
  );
});

const operationDetails = computed(() => {
  const closure = selectedClosure.value;

  if (!closure) return [];

  return [
    ["Sucursal", closure.branch],
    ["Usuario", closure.user],
    ["Caja", closure.cash_box_number || "-"],
    ["Periodo", closure.period],
    ["Ventas", closure.sales_count],
  ];
});

const moneyDetails = computed(() => {
  const closure = selectedClosure.value;

  if (!closure) return [];

  return [
    ["Venta total", money(closure.sales_total)],
    ["Efectivo ventas", money(closure.expected_cash)],
    ["Efectivo contado", money(editableCountedCash.value)],
    ["Tarjeta", money(closure.card_total)],
    ["Total en tarjeta capturado", money(editableCountedCard.value)],
    ["Se dejo en caja", money(editableCashLeft.value)],
    ["Diferencia efectivo", money(editableCashDifference.value)],
    ["Diferencia tarjeta", money(editableCardDifference.value)],
  ];
});

const editableCountedCash = computed(() => (
  modalMode.value === "edit" ? Number(form.counted_cash || 0) : Number(selectedClosure.value?.counted_cash || 0)
));

const editableCountedCard = computed(() => (
  modalMode.value === "edit" ? Number(form.counted_card || 0) : Number(selectedClosure.value?.counted_card || 0)
));

const editableCashLeft = computed(() => (
  modalMode.value === "edit" ? Number(form.cash_left || 0) : Number(selectedClosure.value?.cash_left || 0)
));

const editableCashDifference = computed(() => (
  editableCountedCash.value - Number(selectedClosure.value?.expected_drawer_cash || 0)
));

const editableCardDifference = computed(() => (
  editableCountedCard.value - Number(selectedClosure.value?.card_total || 0)
));

const editableWithdrawal = computed(() => (
  Math.max(0, editableCountedCash.value - editableCashLeft.value)
));

const systemMoneyCards = computed(() => {
  const closure = selectedClosure.value;

  if (!closure) return [];

  return [
    { label: "Venta total", value: money(closure.sales_total), tone: "dark" },
    { label: "Efectivo sistema", value: money(closure.expected_cash), tone: "neutral" },
    { label: "Tarjeta sistema", value: money(closure.card_total), tone: "neutral" },
  ];
});

const capturedMoneyCards = computed(() => {
  const closure = selectedClosure.value;

  if (!closure) return [];

  return [
    {
      label: "Efectivo contado",
      value: money(editableCountedCash.value),
      tone: Math.abs(editableCashDifference.value) < 0.01 ? "success" : "danger",
    },
    {
      label: "Total en tarjeta capturado",
      value: money(editableCountedCard.value),
      tone: Math.abs(editableCardDifference.value) < 0.01 ? "success" : "danger",
    },
    { label: "Se deja en caja", value: money(editableCashLeft.value), tone: "neutral" },
    { label: "Retiro efectivo", value: money(editableWithdrawal.value), tone: "neutral" },
  ];
});

const differenceMoneyCards = computed(() => {
  if (!selectedClosure.value) return [];

  return [
    {
      label: "Diferencia efectivo",
      value: money(editableCashDifference.value),
      tone: Math.abs(editableCashDifference.value) < 0.01 ? "success" : "danger",
    },
    {
      label: "Diferencia tarjeta",
      value: money(editableCardDifference.value),
      tone: Math.abs(editableCardDifference.value) < 0.01 ? "success" : "danger",
    },
  ];
});

const columns = [
  { key: "folio", label: "Folio" },
  { key: "user", label: "Usuario" },
  {
    key: "status",
    label: "Estado",
    format: "badge",
    formatOptions: {
      statusMap: {
        Cuadrado: "green",
        Diferencia: "amber",
      },
    },
  },
];

const closureActions = computed(() => [
  {
    id: "view",
    label: "Ver",
    icon: "visibility",
    variant: "blue",
    permission: "sales.cash-closures.view",
  },
  {
    id: "edit",
    label: "Editar",
    icon: "edit",
    variant: "amber",
    permission: "sales.cash-closures.update",
  },
  {
    id: "delete",
    label: "Eliminar",
    icon: "delete",
    variant: "red",
    permission: "sales.cash-closures.delete",
  },
].map((action) => ({
  ...action,
  hidden: () => action.permission && !can(action.permission),
})));

function money(value) {
  return new Intl.NumberFormat("es-MX", {
    style: "currency",
    currency: "MXN",
  }).format(Number(value || 0));
}

function handlePageChange(url) {
  router.visit(url, { preserveScroll: true, preserveState: true });
}

function goToCut() {
  router.visit(route("ventas.cash-closures.index"));
}

function openBranchReport(branch) {
  router.visit(route("inventory.branches.reports.cash-closures", { branch: branch.id }));
}

function openClosure(row, mode = "view") {
  selectedClosure.value = row;
  modalMode.value = mode;
  form.counted_cash = Number(row.counted_cash || 0);
  form.cash_left = Number(row.cash_left || 0);
  form.counted_card = Number(row.counted_card || 0);
  form.notes = row.notes || "";
  form.clearErrors();
  showClosureModal.value = true;
}

function closeClosureModal() {
  showClosureModal.value = false;
  selectedClosure.value = null;
  form.clearErrors();
}

function handleTableAction({ action, row }) {
  if (action === "view" && can("sales.cash-closures.view")) {
    openClosure(row, "view");
  }

  if (action === "edit" && can("sales.cash-closures.update")) {
    openClosure(row, "edit");
  }

  if (action === "delete" && can("sales.cash-closures.delete")) {
    deleteClosure(row);
  }
}

function showActionSuccess(title) {
  setTimeout(() => {
    ToastAlert({ title });
  }, 120);
}

function showActionError(title, message) {
  ErrorAlert({ title, message });
}

async function submitClosure() {
  if (!selectedClosure.value?.id || modalMode.value !== "edit") {
    closeClosureModal();
    return;
  }

  const result = await confirmModalAction({
    mode: "edit",
    entityName: "corte",
    title: "Guardar cambios",
    message: `Deseas guardar los cambios del corte ${selectedClosure.value.folio}`,
    confirmText: "Si, guardar",
    confirmButtonColor: "#e60012",
  });

  if (!result.isConfirmed) return;

  form.put(route("ventas.cash-closures.update", selectedClosure.value.id), {
    preserveScroll: true,
    onSuccess: () => {
      closeClosureModal();
      showActionSuccess("Corte actualizado correctamente");
    },
    onError: () => {
      showActionError("Error al actualizar corte", "No fue posible actualizar el corte. Revisa los campos e intenta nuevamente.");
    },
  });
}

async function deleteClosure(row) {
  const result = await confirmModalAction({
    mode: "delete",
    entityName: "corte",
    title: "Eliminar corte",
    message: `Deseas eliminar el corte ${row.folio}`,
    confirmText: "Si, eliminar",
  });

  if (!result.isConfirmed) return;

  form.delete(route("ventas.cash-closures.destroy", row.id), {
    preserveScroll: true,
    onSuccess: () => {
      if (selectedClosure.value?.id === row.id) {
        closeClosureModal();
      }

      showActionSuccess("Corte eliminado correctamente");
    },
    onError: () => {
      showActionError("Error al eliminar corte", "No fue posible eliminar el corte.");
    },
  });
}

function backToReportsCenter() {
  if (props.currentBranch?.id && props.branchesDB.length > 1) {
    router.visit(route("ventas.cash-closures.reports"));
    return;
  }

  if (props.currentBranch?.id) {
    router.visit(route("inventory.branches.reports", { branch: props.currentBranch.id }));
    return;
  }

  const firstBranch = props.branchesDB[0];

  if (firstBranch?.id) {
    router.visit(route("inventory.branches.reports", { branch: firstBranch.id }));
    return;
  }

  router.visit(route("dashboard"));
}
</script>

<template>
  <Head title="Reportes de cortes" />

  <PageLayout>
    <div class="space-y-6">
      <GlobalToolbar
        title="Reportes de corte de cajas"
        :subtitle="selectorMode ? 'Selecciona una sucursal para consultar su historial de cortes.' : currentBranch ? `Historial de cortes de ${currentBranch.name}` : 'Historial de cortes registrados por sucursal y usuario'"
        back-button
        :back-label="currentBranch && branchesDB.length > 1 ? 'Sucursales' : 'Centro de reportes'"
        :show-search="false"
        :show-records-per-page="false"
        :show-counter="false"
        :actions="[
          {
            id: 'new-cut',
            label: 'Nuevo corte',
            icon: 'payments',
            variant: 'primary',
          },
        ]"
        @back="backToReportsCenter"
        @action="goToCut"
      />

      <section
        v-if="selectorMode"
        class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4"
      >
        <GlobalCard
          v-for="branch in branchesDB"
          :key="branch.id"
          :title="branch.name"
          subtitle="Sucursal"
          description="Consulta el historial de cortes de caja de esta sucursal."
          icon="history"
          class="min-h-32"
          @click="openBranchReport(branch)"
        />
      </section>

      <template v-else>
      <div class="rounded-2xl border border-secondary bg-background p-4">
        <GlobalTable
          :items="rows"
          :columns="columns"
          :actions="closureActions"
          row-key="id"
          mobile-card-header-field="folio"
          :pagination="closures"
          no-data-message="Todavia no hay cortes registrados."
          @page-change="handlePageChange"
          @action="handleTableAction"
        />
      </div>
      </template>

      <GlobalModal
        v-if="showClosureModal"
        :title="modalMode === 'edit' ? 'Editar corte de caja' : 'Detalle del corte de caja'"
        :subtitle="selectedClosure?.folio || ''"
        :mode="modalMode"
        :columns="1"
        size="2xl"
        :processing="form.processing"
        :show-save="modalMode === 'edit'"
        save-button-text="Guardar cambios"
        close-button-text="Cerrar"
        @save="submitClosure"
        @close="closeClosureModal"
      >
        <div v-if="selectedClosure" class="space-y-5">
          <section
            class="rounded-2xl border p-4"
            :class="isSelectedBalanced ? 'border-accent bg-secondary' : 'border-primary bg-secondary'"
          >
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
              <div>
                <p class="text-xs font-black uppercase tracking-[0.18em]" :class="isSelectedBalanced ? 'text-accent' : 'text-primary'">
                  {{ isSelectedBalanced ? 'Corte cuadrado' : 'Corte con diferencia' }}
                </p>
                <h3 class="mt-1 text-2xl font-black text-text">
                  {{ selectedClosure.folio }}
                </h3>
                <p class="mt-1 text-sm text-text opacity-70">
                  {{ selectedClosure.period }}
                </p>
              </div>

              <div
                class="inline-flex w-fit items-center gap-2 rounded-full px-4 py-2 text-sm font-black"
                :class="isSelectedBalanced ? 'bg-accent/10 text-accent' : 'bg-primary/10 text-primary'"
              >
                <span class="material-symbols-outlined text-[18px]">
                  {{ isSelectedBalanced ? 'check_circle' : 'error' }}
                </span>
                {{ selectedClosure.status }}
              </div>
            </div>
          </section>

          <div class="grid grid-cols-1 gap-4 xl:grid-cols-[0.85fr_1.15fr]">
            <FormPanel
              title="Datos del corte"
              description="Informacion general registrada para esta caja."
              body-class="space-y-3"
            >
              <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div
                  v-for="item in operationDetails"
                  :key="item[0]"
                  class="rounded-xl border border-secondary bg-background px-4 py-3"
                >
                  <p class="text-[10px] font-black uppercase tracking-[0.16em] text-text opacity-50">
                    {{ item[0] }}
                  </p>
                  <p class="mt-1 text-sm font-black text-text">
                    {{ item[1] }}
                  </p>
                </div>
              </div>

              <TextareaField
                v-model="form.notes"
                label="Observaciones"
                field="notes"
                :rows="3"
                :max-height="92"
                :readonly="modalMode !== 'edit'"
                placeholder="Notas del corte"
                :error="form.errors.notes"
              />
            </FormPanel>

            <FormPanel
              title="Cuadre de dinero"
              description="Montos del sistema contra lo capturado en el corte."
              body-class="space-y-4"
            >
              <div
                v-if="modalMode === 'edit'"
                class="grid grid-cols-1 gap-3 md:grid-cols-3"
              >
                <InputField
                  v-model="form.counted_cash"
                  label="Efectivo contado"
                  field="counted_cash"
                  type="number"
                  min="0"
                  step="0.01"
                  prefix="$"
                  :error="form.errors.counted_cash"
                />
                <InputField
                  v-model="form.counted_card"
                  label="Total en tarjeta capturado"
                  field="counted_card"
                  type="number"
                  min="0"
                  step="0.01"
                  prefix="$"
                  :error="form.errors.counted_card"
                />
                <InputField
                  v-model="form.cash_left"
                  label="Se deja en caja"
                  field="cash_left"
                  type="number"
                  min="0"
                  step="0.01"
                  prefix="$"
                  :error="form.errors.cash_left"
                />
              </div>

              <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <section class="rounded-2xl border border-secondary bg-background p-3">
                  <div class="mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px] text-text opacity-60">dns</span>
                    <div>
                      <h4 class="text-sm font-black text-text">
                        Sistema
                      </h4>
                      <p class="text-xs text-text opacity-60">
                        Totales registrados por ventas.
                      </p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 gap-3">
                    <MetricCard
                      v-for="card in systemMoneyCards"
                      :key="card.label"
                      :label="card.label"
                      :value="card.value"
                      :tone="card.tone"
                      size="sm"
                    />
                  </div>
                </section>

                <section class="rounded-2xl border border-secondary bg-background p-3">
                  <div class="mb-3 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px] text-primary">edit_note</span>
                    <div>
                      <h4 class="text-sm font-black text-text">
                        Capturado en corte
                      </h4>
                      <p class="text-xs text-text opacity-60">
                        Montos que ingreso el usuario.
                      </p>
                    </div>
                  </div>

                  <div class="grid grid-cols-1 gap-3">
                    <MetricCard
                      v-for="card in capturedMoneyCards"
                      :key="card.label"
                      :label="card.label"
                      :value="card.value"
                      :tone="card.tone"
                      size="sm"
                    />
                  </div>
                </section>
              </div>

              <section class="rounded-2xl border border-secondary bg-background p-3">
                <div class="mb-3 flex items-center gap-2">
                  <span class="material-symbols-outlined text-[20px]" :class="isSelectedBalanced ? 'text-accent' : 'text-primary'">
                    {{ isSelectedBalanced ? 'check_circle' : 'warning' }}
                  </span>
                  <div>
                    <h4 class="text-sm font-black text-text">
                      Resultado del cuadre
                    </h4>
                    <p class="text-xs text-text opacity-60">
                      Diferencia entre sistema y captura.
                    </p>
                  </div>
                </div>

                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                  <MetricCard
                    v-for="card in differenceMoneyCards"
                    :key="card.label"
                    :label="card.label"
                    :value="card.value"
                    :tone="card.tone"
                    size="sm"
                  />
                </div>
              </section>
            </FormPanel>
          </div>

        </div>
      </GlobalModal>
    </div>
  </PageLayout>
</template>
