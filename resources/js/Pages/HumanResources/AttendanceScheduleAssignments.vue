<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import { GlobalToolbar } from '@/Components/Toolbars'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales'
import TextareaField from '@/Components/Forms/TextareaField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue'
import { usePermissions } from '@/Composables/usePermissions'
import { REALTIME_CHANNELS, REALTIME_EVENTS, refreshRealtimeProps, subscribePrivateRealtime } from '@/realtime'
import { getAttendanceScheduleAssignmentsToolbarConfig } from '@/config/ToolbarConfigs/attendanceScheduleAssignmentsToolbarConfig'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'

defineOptions({ layout: AdminLayout })

const props = defineProps({ assignments: { type: Object, default: () => ({ data: [] }) }, employees: { type: Array, default: () => [] }, schedules: { type: Array, default: () => [] }, filters: { type: Object, default: () => ({}) } })
const page = usePage()
const { can } = usePermissions()
const { handlePageChange } = useGlobalTablePagination()
const defaultDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
const days = [{ key: 'monday', label: 'Lunes' }, { key: 'tuesday', label: 'Martes' }, { key: 'wednesday', label: 'Miercoles' }, { key: 'thursday', label: 'Jueves' }, { key: 'friday', label: 'Viernes' }, { key: 'saturday', label: 'Sabado' }, { key: 'sunday', label: 'Domingo' }]
const showModal = ref(false)
const mode = ref('create')
const selected = ref(null)
const perPage = ref(Number(props.filters?.per_page ?? 30))
const form = useForm({ employee_id: '', attendance_schedule_ids: [], effective_from: new Date().toISOString().slice(0, 10), effective_to: '', active: true, observations: '', working_days: [...defaultDays] })
let unsubscribeAssignments = null

const toolbarConfig = computed(() => getAttendanceScheduleAssignmentsToolbarConfig({ canCreate: can('attendance.schedule-assignments.create'), perPage: perPage.value, total: props.assignments?.total ?? 0 }))
const columns = [{ key: 'employee', label: 'Empleado' }, { key: 'department', label: 'Departamento' }, { key: 'position', label: 'Puesto' }, { key: 'schedule', label: 'Horario asignado' }, { key: 'effective_from', label: 'Inicio', format: 'date' }, { key: 'effective_to', label: 'Termino', format: 'date', formatOptions: { fallback: 'Vigente' } }, { key: 'active', label: 'Estado', format: 'badge', formatOptions: { labelMap: { true: 'Activa', false: 'Inactiva' }, colorMap: { true: 'green', false: 'slate' } } }]
const actions = [{ id: 'view', label: 'Ver', icon: 'visibility', variant: 'blue', mobile: 'button', permission: 'attendance.schedule-assignments.view' }, { id: 'edit', label: 'Editar', icon: 'edit', variant: 'amber', mobile: 'button', permission: 'attendance.schedule-assignments.update' }, { id: 'delete', label: 'Eliminar', icon: 'delete', variant: 'red', mobile: 'button', permission: 'attendance.schedule-assignments.delete' }]
const title = computed(() => ({ create: 'Nueva asignacion', edit: 'Editar asignacion', view: 'Detalle de asignacion' }[mode.value]))

function reset() { form.reset(); form.clearErrors(); form.effective_from = new Date().toISOString().slice(0, 10); form.active = true; form.working_days = [...defaultDays] }
function openCreate() { mode.value = 'create'; selected.value = null; reset(); showModal.value = true }
function load(row, nextMode) { selected.value = row; mode.value = nextMode; form.clearErrors(); Object.assign(form, { employee_id: String(row.employee_id), attendance_schedule_ids: [Number(row.attendance_schedule_id)], effective_from: row.effective_from || '', effective_to: row.effective_to || '', active: Boolean(row.active), observations: row.observations || '', working_days: [...(row.working_days || defaultDays)] }); showModal.value = true }
function close() { showModal.value = false; form.clearErrors() }
function submit() { if (mode.value === 'view') return close(); const options = getModalRequestOptions({ mode: mode.value === 'edit' ? 'edit' : 'create', entityName: 'Asignacion', close }); if (mode.value === 'edit') form.put(route('human-resources.attendance-schedule-assignments.update', selected.value.id), options); else form.post(route('human-resources.attendance-schedule-assignments.store'), options) }
async function remove(row) { const result = await confirmModalAction({ mode: 'delete', entityName: 'asignacion', title: 'Eliminar asignacion', message: `Deseas eliminar unicamente la asignacion de horario de ${row.employee}? El empleado se conservara.`, confirmText: 'Si, eliminar' }); if (result.isConfirmed) form.delete(route('human-resources.attendance-schedule-assignments.destroy', row.id), getModalRequestOptions({ mode: 'delete', entityName: 'Asignacion' })) }
function action({ action, row }) { if (action === 'view') load(row, 'view'); if (action === 'edit') load(row, 'edit'); if (action === 'delete') remove(row) }
function handleToolbarAction(action) { if (action === 'create') openCreate() }
function updatePerPage(value) { perPage.value = Number(value); router.get(route('human-resources.attendance-schedule-assignments.index'), { per_page: perPage.value }, { preserveState: true, preserveScroll: true, replace: true }) }
function toggleWorkingDay(day) { form.working_days = form.working_days.includes(day) ? form.working_days.filter((value) => value !== day) : [...form.working_days, day] }
function toggleSchedule(scheduleId) { const id = Number(scheduleId); form.attendance_schedule_ids = form.attendance_schedule_ids.includes(id) ? form.attendance_schedule_ids.filter((value) => value !== id) : [...form.attendance_schedule_ids, id] }

function refreshAssignments(event) {
  if (!event?.action?.startsWith('schedule_')) return
  if (event.action === 'schedule_assignment_deleted' && Number(event.recordId) === Number(selected.value?.id)) { close(); selected.value = null }
  refreshRealtimeProps(page, ['assignments', 'employees', 'schedules'], { onSuccess: () => { if (!showModal.value || mode.value !== 'view' || !selected.value?.id) return; const updated = (props.assignments?.data || []).find((assignment) => Number(assignment.id) === Number(selected.value.id)); if (updated) load(updated, 'view') } })
}

onMounted(() => { unsubscribeAssignments = subscribePrivateRealtime(REALTIME_CHANNELS.user(page.props.auth.user.id), REALTIME_EVENTS.attendanceChanged, refreshAssignments) })
onBeforeUnmount(() => unsubscribeAssignments?.())
</script>

<template>
  <PageLayout>
    <GlobalToolbar v-bind="toolbarConfig" @action="handleToolbarAction" @update:records-per-page="updatePerPage" />
    <GlobalTable :items="assignments.data || []" :columns="columns" :actions="actions" :pagination="assignments" mobile-card-header-field="employee" no-data-message="No hay asignaciones registradas." @action="action" @page-change="handlePageChange" />
    <GlobalModal v-if="showModal" :title="title" subtitle="Selecciona uno o varios horarios, la vigencia y los dias laborables." :mode="mode" :total-errors="Object.keys(form.errors).length" :processing="form.processing" :show-save="mode !== 'view'" :save-button-text="mode === 'edit' ? 'Guardar cambios' : 'Guardar asignacion'" :close-button-text="mode === 'view' ? 'Cerrar' : 'Cancelar'" size="md" height="auto" :columns="1" @save="submit" @close="close">
      <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
        <SelectField v-model="form.employee_id" label="Empleado" field="assignment-employee" :options="employees" :disabled="mode === 'view'" :error="form.errors.employee_id" />
        <div class="sm:col-span-2"><p class="mb-2 text-sm font-semibold text-text">Horarios asignados</p><div class="grid grid-cols-1 gap-2 sm:grid-cols-2"><SelectionCheckboxCard v-for="schedule in schedules" :key="schedule.value" compact :title="schedule.label" :description="schedule.description || 'Horario configurado'" :checked="form.attendance_schedule_ids.includes(Number(schedule.value))" :disabled="mode === 'view'" @toggle="toggleSchedule(schedule.value)" /></div><p v-if="form.errors.attendance_schedule_ids" class="mt-1 text-xs text-primary">{{ form.errors.attendance_schedule_ids }}</p><p class="mt-2 text-xs text-text opacity-60">Puedes seleccionar varios horarios para permitir que el vendedor doble turno.</p></div>
        <div class="sm:col-span-2"><p class="mb-2 text-sm font-semibold text-text">Dias laborables</p><div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4"><SelectionCheckboxCard v-for="day in days" :key="day.key" compact :title="day.label" :description="form.working_days.includes(day.key) ? 'Laboral' : 'Descanso'" :checked="form.working_days.includes(day.key)" :disabled="mode === 'view'" @toggle="toggleWorkingDay(day.key)" /></div><p v-if="form.errors.working_days" class="mt-1 text-xs text-primary">{{ form.errors.working_days }}</p><p class="mt-2 text-xs text-text opacity-60">Los dias no seleccionados se consideran dias de descanso.</p></div>
        <TextareaField v-model="form.observations" label="Observaciones" field="assignment-notes" class="sm:col-span-2" :readonly="mode === 'view'" :error="form.errors.observations" :rows="3" placeholder="Agrega una nota relevante para esta asignacion, si aplica." />
      </form>
    </GlobalModal>
  </PageLayout>
</template>
