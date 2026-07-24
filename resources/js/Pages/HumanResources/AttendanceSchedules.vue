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
import SelectField from '@/Components/Forms/SelectField.vue'
import TimeField from '@/Components/Forms/TimeField.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import { usePermissions } from '@/Composables/usePermissions'
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribePrivateRealtime } from '@/realtime'

defineOptions({ layout: AdminLayout })

defineProps({ schedules: { type: Array, default: () => [] } })
const page = usePage()
const { can } = usePermissions()
let unsubscribeSchedules = null

const showModal = ref(false)
const modalMode = ref('create')
const selectedSchedule = ref(null)

const days = [{ key: 'monday', label: 'Lunes' }, { key: 'tuesday', label: 'Martes' }, { key: 'wednesday', label: 'Miercoles' }, { key: 'thursday', label: 'Jueves' }, { key: 'friday', label: 'Viernes' }, { key: 'saturday', label: 'Sabado' }, { key: 'sunday', label: 'Domingo' }]
const daily = () => ({})
const showDayOverrides = ref(false)
const selectedOverrideDay = ref('')
const form = useForm({
  name: '',
  description: '',
  active: true,
  check_in_at: '08:00',
  check_out_at: '17:00',
  meal_start_at: '14:00',
  meal_end_at: '15:00',
  maximum_meal_minutes: 60,
  expected_work_minutes: 480,
  minimum_work_minutes: 420,
  tolerances: { on_time_minutes: 10 },
  daily_schedule: daily(),
})

const columns = [
  { key: 'name', label: 'Horario' },
  { key: 'check_in_at', label: 'Entrada', format: 'time' },
  { key: 'check_out_at', label: 'Salida', format: 'time' },
  { key: 'active', label: 'Estado', format: 'badge', formatOptions: { labelMap: { true: 'Activo', false: 'Inactivo' }, colorMap: { true: 'green', false: 'slate' } } },
]

const actions = [
  { id: 'view', label: 'Ver', icon: 'visibility', variant: 'blue', mobile: 'button', permission: 'attendance.schedules.view' },
  { id: 'edit', label: 'Editar', icon: 'edit', variant: 'amber', mobile: 'button', permission: 'attendance.schedules.update' },
  { id: 'delete', label: 'Eliminar', icon: 'delete', variant: 'red', mobile: 'button', permission: 'attendance.schedules.delete' },
]

const modalTitle = computed(() => ({ create: 'Crear horario', edit: 'Editar horario', view: 'Detalle del horario' }[modalMode.value]))
const saveButtonText = computed(() => modalMode.value === 'edit' ? 'Guardar cambios' : 'Crear horario')

function resetForm() {
  form.reset()
  form.clearErrors()
  form.name = ''
  form.active = true
  form.check_in_at = '08:00'
  form.check_out_at = '17:00'
  form.meal_start_at = '14:00'
  form.meal_end_at = '15:00'
  form.tolerances = { on_time_minutes: 10 }
  form.daily_schedule = daily()
}

function openCreateModal() {
  selectedSchedule.value = null
  modalMode.value = 'create'
  resetForm()
  showModal.value = true
  showDayOverrides.value = false
}

function loadSchedule(schedule, mode) {
  selectedSchedule.value = schedule
  modalMode.value = mode
  form.clearErrors()
  form.name = schedule.name
  form.description = schedule.description || ''
  form.active = Boolean(schedule.active)
  form.check_in_at = String(schedule.check_in_at || '08:00').slice(0, 5)
  form.check_out_at = String(schedule.check_out_at || '17:00').slice(0, 5)
  form.meal_start_at = String(schedule.meal_start_at || '14:00').slice(0, 5)
  form.meal_end_at = String(schedule.meal_end_at || '15:00').slice(0, 5)
  form.maximum_meal_minutes = schedule.maximum_meal_minutes ?? 60
  form.expected_work_minutes = schedule.expected_work_minutes ?? 480
  form.minimum_work_minutes = schedule.minimum_work_minutes ?? 420
  form.tolerances = { on_time_minutes: schedule.tolerances?.on_time_minutes ?? 10 }
  form.daily_schedule = schedule.daily_schedule || daily()
  showDayOverrides.value = Object.keys(form.daily_schedule).length > 0
  showModal.value = true
}

function openViewModal(schedule) { loadSchedule(schedule, 'view') }
function openEditModal(schedule) { loadSchedule(schedule, 'edit') }

function closeModal() {
  showModal.value = false
  form.clearErrors()
}

function toggleDay(day) {
  const current = form.daily_schedule || {}
  if (current[day]) { const next = { ...current }; delete next[day]; form.daily_schedule = next; return }
  form.daily_schedule = { ...current, [day]: { check_in_at: form.check_in_at, check_out_at: form.check_out_at, meal_start_at: form.meal_start_at, meal_end_at: form.meal_end_at } }
}

function addDayOverride() {
  if (!selectedOverrideDay.value) return
  if (!form.daily_schedule?.[selectedOverrideDay.value]) toggleDay(selectedOverrideDay.value)
  selectedOverrideDay.value = ''
}

function submit() {
  if (modalMode.value === 'view') {
    closeModal()
    return
  }

  const options = getModalRequestOptions({
    mode: modalMode.value === 'edit' ? 'edit' : 'create',
    entityName: 'Horario',
    close: closeModal,
  })

  if (modalMode.value === 'edit') {
    form.put(route('human-resources.attendance-schedules.update', selectedSchedule.value.id), options)
    return
  }

  form.post(route('human-resources.attendance-schedules.store'), options)
}

async function deleteSchedule(schedule) {
  const result = await confirmModalAction({
    mode: 'delete',
    entityName: 'horario',
    title: 'Eliminar horario',
    message: `¿Deseas eliminar el horario “${schedule.name}”?`,
    confirmText: 'Sí, eliminar',
  })

  if (!result.isConfirmed) return

  form.delete(route('human-resources.attendance-schedules.destroy', schedule.id), getModalRequestOptions({
    mode: 'delete',
    entityName: 'Horario',
  }))
}

function handleTableAction({ action, row }) {
  if (action === 'view') openViewModal(row)
  if (action === 'edit') openEditModal(row)
  if (action === 'delete') deleteSchedule(row)
}

onMounted(() => {
  unsubscribeSchedules = subscribePrivateRealtime(
    REALTIME_CHANNELS.user(page.props.auth.user.id),
    REALTIME_EVENTS.attendanceChanged,
    ({ action }) => {
      if (['schedule_created', 'schedule_updated', 'schedule_deleted'].includes(action)) {
        router.reload({ only: ['schedules'], preserveScroll: true, preserveState: true })
      }
    },
  )
})

onBeforeUnmount(() => unsubscribeSchedules?.())
</script>

<template>
  <PageLayout>
    <GlobalCard title="Horarios" icon="schedule" :clickable="false" class="p-5 md:p-6">
      <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-text opacity-70">Configura jornadas y tolerancias para el registro de asistencia.</p>
        <AppButton v-if="can('attendance.schedules.create')" class="shrink-0" @click="openCreateModal">
          <span class="material-symbols-outlined mr-2 text-[18px]">add</span>
          Crear horario
        </AppButton>
      </div>
    </GlobalCard>

    <GlobalTable
      :items="schedules"
      :columns="columns"
      :actions="actions"
      mobile-card-header-field="name"
      no-data-message="No hay horarios configurados."
      @action="handleTableAction"
    />

    <GlobalModal
      v-if="showModal"
      :title="modalTitle"
      subtitle="Define los horarios y la tolerancia de entrada."
      :mode="modalMode"
      :total-errors="Object.keys(form.errors).length"
      :processing="form.processing"
      :save-button-text="saveButtonText"
      :close-button-text="modalMode === 'view' ? 'Cerrar' : 'Cancelar'"
      :show-save="modalMode !== 'view'"
      size="sm"
      height="auto"
      :columns="1"
      @save="submit"
      @close="closeModal"
    >
      <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
        <InputField v-model="form.name" label="Nombre" field="schedule-name" :readonly="modalMode === 'view'" :error="form.errors.name" />
        <InputField v-model="form.tolerances.on_time_minutes" label="Tolerancia de entrada (min)" field="schedule-tolerance" type="number" min="0" :readonly="modalMode === 'view'" />
        <TimeField v-model="form.check_in_at" label="Hora de entrada" field="schedule-in" :readonly="modalMode === 'view'" :error="form.errors.check_in_at" />
        <TimeField v-model="form.check_out_at" label="Hora de salida" field="schedule-out" :readonly="modalMode === 'view'" :error="form.errors.check_out_at" />
        <TimeField v-model="form.meal_start_at" label="Inicio de comida" field="schedule-meal-start" :readonly="modalMode === 'view'" :error="form.errors.meal_start_at" />
        <TimeField v-model="form.meal_end_at" label="Fin de comida" field="schedule-meal-end" :readonly="modalMode === 'view'" :error="form.errors.meal_end_at" />
        <div class="sm:col-span-2 border-t border-secondary pt-4">
          <button type="button" class="flex w-full items-center justify-between rounded-2xl border border-secondary bg-secondary/60 px-4 py-3.5 text-left transition hover:border-primary" @click="showDayOverrides = !showDayOverrides"><span class="flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-xl bg-primary/10 text-primary"><span class="material-symbols-outlined text-[20px]">event_repeat</span></span><span><span class="block text-sm font-semibold text-text">Horario especial por día</span><span class="block text-xs text-text opacity-60">Agrega solo las excepciones necesarias.</span></span></span><span class="material-symbols-outlined text-text opacity-70">{{ showDayOverrides ? 'expand_less' : 'expand_more' }}</span></button>
          <div v-if="showDayOverrides" class="mt-3 rounded-2xl border border-secondary bg-secondary/35 p-4">
            <div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-3"><SelectField v-model="selectedOverrideDay" label="Día a modificar" field="schedule-override-day" :options="days.map(day => ({ value: day.key, label: day.label }))" class="min-w-0" /><AppButton type="button" class="shrink-0" @click="addDayOverride">Agregar</AppButton></div>
            <div v-for="day in days.filter(item => form.daily_schedule?.[item.key])" :key="`${day.key}-hours`" class="mt-3 rounded-xl border border-primary/30 bg-background p-3"><div class="mb-3 flex items-center"><p class="text-sm font-semibold text-text">{{ day.label }} · horario especial</p><button v-if="modalMode !== 'view'" type="button" class="ml-auto text-xs font-semibold text-primary" @click="toggleDay(day.key)">Quitar</button></div><div class="grid grid-cols-2 gap-3"><TimeField v-model="form.daily_schedule[day.key].check_in_at" compact label="Entrada" :field="`${day.key}-in`" :readonly="modalMode === 'view'" /><TimeField v-model="form.daily_schedule[day.key].check_out_at" compact label="Salida" :field="`${day.key}-out`" :readonly="modalMode === 'view'" /><TimeField v-model="form.daily_schedule[day.key].meal_start_at" compact label="Inicio de comida" :field="`${day.key}-meal-start`" :readonly="modalMode === 'view'" /><TimeField v-model="form.daily_schedule[day.key].meal_end_at" compact label="Fin de comida" :field="`${day.key}-meal-end`" :readonly="modalMode === 'view'" /></div></div>
          </div>
        </div>
      </form>
    </GlobalModal>
  </PageLayout>
</template>
