<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalCard from '@/Components/Cards/GlobalCard.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales'
import InputField from '@/Components/Forms/InputField.vue'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import { usePermissions } from '@/Composables/usePermissions'
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribePrivateRealtime } from '@/realtime'

defineOptions({ layout: AdminLayout })
const props = defineProps({ assignments: { type: Object, default: () => ({ data: [] }) }, employees: { type: Array, default: () => [] }, schedules: { type: Array, default: () => [] } })
const page = usePage()
const { can } = usePermissions()
let unsubscribeAssignments = null
const defaultDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
const days = [{ key: 'monday', label: 'Lunes' }, { key: 'tuesday', label: 'Martes' }, { key: 'wednesday', label: 'Miércoles' }, { key: 'thursday', label: 'Jueves' }, { key: 'friday', label: 'Viernes' }, { key: 'saturday', label: 'Sábado' }, { key: 'sunday', label: 'Domingo' }]
const showModal = ref(false)
const mode = ref('create')
const selected = ref(null)
const form = useForm({ employee_id: '', attendance_schedule_id: '', effective_from: new Date().toISOString().slice(0, 10), effective_to: '', active: true, observations: '', working_days: [...defaultDays] })
const columns = [{ key: 'employee', label: 'Empleado' }, { key: 'department', label: 'Departamento' }, { key: 'position', label: 'Puesto' }, { key: 'schedule', label: 'Horario asignado' }, { key: 'effective_from', label: 'Inicio', format: 'date' }, { key: 'effective_to', label: 'Término', format: 'date', formatOptions: { fallback: 'Vigente' } }, { key: 'active', label: 'Estado', format: 'badge', formatOptions: { labelMap: { true: 'Activa', false: 'Inactiva' }, colorMap: { true: 'green', false: 'slate' } } }]
const actions = [
  { id: 'view', label: 'Ver', icon: 'visibility', variant: 'blue', mobile: 'button', permission: 'attendance.schedule-assignments.view' },
  { id: 'edit', label: 'Editar', icon: 'edit', variant: 'amber', mobile: 'button', permission: 'attendance.schedule-assignments.update' },
  { id: 'delete', label: 'Desactivar', icon: 'delete', variant: 'red', mobile: 'button', permission: 'attendance.schedule-assignments.delete' },
]
const title = computed(() => ({ create: 'Nueva asignación', edit: 'Editar asignación', view: 'Detalle de asignación' }[mode.value]))

function reset() { form.reset(); form.clearErrors(); form.effective_from = new Date().toISOString().slice(0, 10); form.active = true; form.working_days = [...defaultDays] }
function openCreate() { mode.value = 'create'; selected.value = null; reset(); showModal.value = true }
function load(row, nextMode) { selected.value = row; mode.value = nextMode; form.clearErrors(); Object.assign(form, { employee_id: String(row.employee_id), attendance_schedule_id: String(row.attendance_schedule_id), effective_from: row.effective_from || '', effective_to: row.effective_to || '', active: Boolean(row.active), observations: row.observations || '', working_days: [...(row.working_days || defaultDays)] }); showModal.value = true }
function close() { showModal.value = false; form.clearErrors() }
function submit() { if (mode.value === 'view') return close(); const options = getModalRequestOptions({ mode: mode.value === 'edit' ? 'edit' : 'create', entityName: 'Asignación', close }); if (mode.value === 'edit') form.put(route('human-resources.attendance-schedule-assignments.update', selected.value.id), options); else form.post(route('human-resources.attendance-schedule-assignments.store'), options) }
async function remove(row) { const result = await confirmModalAction({ mode: 'delete', entityName: 'asignación', title: 'Desactivar asignación', message: `¿Deseas desactivar la asignación de ${row.employee}?`, confirmText: 'Sí, desactivar' }); if (result.isConfirmed) form.delete(route('human-resources.attendance-schedule-assignments.destroy', row.id), getModalRequestOptions({ mode: 'delete', entityName: 'Asignación' })) }
function action({ action, row }) { if (action === 'view') load(row, 'view'); if (action === 'edit') load(row, 'edit'); if (action === 'delete') remove(row) }
function toggleWorkingDay(day) { form.working_days = form.working_days.includes(day) ? form.working_days.filter((value) => value !== day) : [...form.working_days, day] }

onMounted(() => {
  unsubscribeAssignments = subscribePrivateRealtime(
    REALTIME_CHANNELS.user(page.props.auth.user.id),
    REALTIME_EVENTS.attendanceChanged,
    ({ action: realtimeAction }) => {
      if (realtimeAction?.startsWith('schedule_')) {
        router.reload({ only: ['assignments', 'employees', 'schedules'], preserveScroll: true, preserveState: true })
      }
    },
  )
})

onBeforeUnmount(() => unsubscribeAssignments?.())
</script>

<template>
  <PageLayout>
    <GlobalCard title="Asignación de horarios" icon="assignment_ind" :clickable="false" class="p-5 md:p-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><p class="text-sm text-text opacity-70">Asigna un horario y define los días laborables de cada empleado.</p><AppButton v-if="can('attendance.schedule-assignments.create')" @click="openCreate"><span class="material-symbols-outlined mr-2 text-[18px]">add</span>Nueva asignación</AppButton></div>
    </GlobalCard>
    <GlobalTable :items="assignments.data || []" :columns="columns" :actions="actions" :pagination="assignments" mobile-card-header-field="employee" no-data-message="No hay asignaciones registradas." @action="action" />
    <GlobalModal v-if="showModal" :title="title" subtitle="Define horario, vigencia y días laborables." :mode="mode" :total-errors="Object.keys(form.errors).length" :processing="form.processing" :show-save="mode !== 'view'" :save-button-text="mode === 'edit' ? 'Guardar cambios' : 'Guardar asignación'" :close-button-text="mode === 'view' ? 'Cerrar' : 'Cancelar'" size="md" height="auto" :columns="1" @save="submit" @close="close">
      <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
        <SelectField v-model="form.employee_id" label="Empleado" field="assignment-employee" :options="employees" :disabled="mode === 'view'" :error="form.errors.employee_id" />
        <SelectField v-model="form.attendance_schedule_id" label="Horario" field="assignment-schedule" :options="schedules" :disabled="mode === 'view'" :error="form.errors.attendance_schedule_id" />
        <div class="sm:col-span-2"><p class="mb-2 text-sm font-semibold text-text">Días laborables</p><div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4"><SelectionCheckboxCard v-for="day in days" :key="day.key" compact :title="day.label" :description="form.working_days.includes(day.key) ? 'Laboral' : 'Descanso'" :checked="form.working_days.includes(day.key)" :disabled="mode === 'view'" @toggle="toggleWorkingDay(day.key)" /></div><p v-if="form.errors.working_days" class="mt-1 text-xs text-primary">{{ form.errors.working_days }}</p><p class="mt-2 text-xs text-text opacity-60">Los días no seleccionados se consideran días de descanso.</p></div>
        <TextareaField v-model="form.observations" label="Observaciones" field="assignment-notes" class="sm:col-span-2" :readonly="mode === 'view'" :error="form.errors.observations" :rows="3" placeholder="Agrega una nota relevante para esta asignación, si aplica." />
      </form>
    </GlobalModal>
  </PageLayout>
</template>
