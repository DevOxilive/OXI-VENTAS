<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import InputField from '@/Components/Forms/InputField.vue'
import TimeField from '@/Components/Forms/TimeField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import { GlobalToolbar } from '@/Components/Toolbars'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales'
import { REALTIME_CHANNELS, REALTIME_EVENTS, refreshRealtimeProps, subscribePrivateRealtime } from '@/realtime'
import { getAttendanceIncidentsToolbarConfig } from '@/config/ToolbarConfigs/attendanceIncidentsToolbarConfig'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'

defineOptions({ layout: AdminLayout })

const props = defineProps({
  incidents: { type: Object, default: () => ({ data: [] }) },
  employees: { type: Array, default: () => [] },
  filters: { type: Object, default: () => ({}) },
  statuses: { type: Array, default: () => [] },
  notificationSummary: { type: Object, default: () => ({ mode: 'submitted', count: 0, items: [] }) },
})

const page = usePage()
const { handlePageChange } = useGlobalTablePagination()
const filters = reactive({
  from: props.filters.from ?? '',
  to: props.filters.to ?? '',
  search: props.filters.search ?? '',
  status: props.filters.status ?? '',
  per_page: Number(props.filters.per_page ?? 25),
})
const form = useForm({
  employee_id: '',
  incident_date: todayDate(),
  incident_time: '',
  estimated_arrival_at: '08:00',
  rest_day_requested: false,
  rest_day_date: '',
  make_up_date: '',
  reason: '',
})
const modalOpen = ref(false)
const modalMode = ref('create')
const selectedIncident = ref(null)
const notificationPanelOpen = ref(false)
const dismissedNotificationKeys = ref(new Set())
let filterTimer = null
let unsubscribeIncidents = null

const statusLabels = {
  pending: 'Pendiente',
  approved: 'Aprobada',
  rejected: 'Denegada',
}

const columns = [
  { key: 'incident_date', label: 'Fecha de incidencia', format: 'date', mobileSecondary: true },
  { key: 'created_at', label: 'Registrada', format: 'date', formatOptions: { format: 'datetime' }, mobileDisplay: false },
  { key: 'employee_name', label: 'Empleado' },
  { key: 'reason', label: 'Motivo', formatOptions: { truncate: 80, multiline: true } },
  {
    key: 'status',
    label: 'Estado',
    format: 'badge',
    mobileBadge: true,
    formatOptions: {
      labelMap: statusLabels,
      statusMap: { pending: 'amber', approved: 'green', rejected: 'red' },
    },
  },
]

const actions = [
  { id: 'view', label: 'Ver', icon: 'visibility', variant: 'blue', mobile: 'button', permission: 'attendance.incidents.view' },
  { id: 'approve', label: 'Aprobar', icon: 'check_circle', variant: 'green', mobile: 'button', permission: 'attendance.incidents.approve', hidden: row => row.status !== 'pending' },
  { id: 'reject', label: 'Denegar', icon: 'cancel', variant: 'red', mobile: 'button', permission: 'attendance.incidents.reject', hidden: row => row.status !== 'pending' },
  { id: 'edit', label: 'Editar', icon: 'edit', variant: 'amber', mobile: 'button', permission: 'attendance.incidents.update', hidden: row => row.status !== 'pending' },
]

const modalTitle = computed(() => ({
  create: 'Nueva incidencia',
  edit: 'Editar incidencia',
  view: 'Detalle de incidencia',
}[modalMode.value]))

const modalSaveText = computed(() => modalMode.value === 'edit' ? 'Guardar cambios' : 'Enviar incidencia')
const totalErrors = computed(() => Object.keys(form.errors || {}).length)
const isReadonly = computed(() => modalMode.value === 'view')
const notificationStorageKey = computed(() => `attendance-incidents:dismissed:${page.props.auth.user.id}`)
const notificationItems = computed(() => (props.notificationSummary.items || []).filter((item) => {
  return !dismissedNotificationKeys.value.has(notificationItemKey(item))
}))
const notificationCount = computed(() => notificationItems.value.length)
const notificationMode = computed(() => props.notificationSummary.mode || 'submitted')
const notificationPanelTitle = computed(() => notificationMode.value === 'review'
  ? 'Incidencias pendientes'
  : 'Respuestas de incidencias')
const notificationPanelSubtitle = computed(() => notificationMode.value === 'review'
  ? 'Solicitudes que requieren aprobacion o rechazo.'
  : 'Ultimas incidencias aprobadas o denegadas.')
const toolbarActions = computed(() => [
  {
    id: 'notifications',
    label: 'Avisos',
    icon: 'notifications',
    variant: notificationCount.value ? 'amber' : 'slate',
    badge: notificationCount.value || '',
  },
  {
    id: 'create',
    label: 'Nueva incidencia',
    icon: 'add_circle',
    variant: 'primary',
    permission: 'attendance.incidents.create',
  },
])
const toolbarConfig = computed(() => getAttendanceIncidentsToolbarConfig({
  filters,
  statuses: props.statuses,
  total: props.incidents?.total ?? 0,
  actions: toolbarActions.value,
}))

watch(filters, () => {
  clearTimeout(filterTimer)
  filterTimer = setTimeout(applyFilters, 350)
}, { deep: true })

watch(() => form.incident_date, (date) => {
  if (form.rest_day_requested && !form.rest_day_date) {
    form.rest_day_date = date
  }
})

function cleanFilters() {
  return Object.fromEntries(
    Object.entries(filters).filter(([, value]) => value !== '' && value !== null && value !== undefined),
  )
}

function applyFilters() {
  router.get(route('human-resources.attendance-incidents.index'), cleanFilters(), {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    only: ['incidents', 'filters'],
  })
}

function handleToolbarFilter({ key, value }) {
  filters[key] = value
}

function handleToolbarAction(action) {
  if (action === 'notifications') {
    toggleNotificationPanel()
    return
  }

  if (action === 'create') {
    openCreateModal()
  }
}

function toggleNotificationPanel() {
  notificationPanelOpen.value = !notificationPanelOpen.value
}

function closeNotificationPanel() {
  notificationPanelOpen.value = false
}

function notificationItemKey(item) {
  return `${item.id}:${item.status}:${item.updated_at || ''}`
}

function loadDismissedNotifications() {
  try {
    dismissedNotificationKeys.value = new Set(JSON.parse(localStorage.getItem(notificationStorageKey.value) || '[]'))
  } catch {
    dismissedNotificationKeys.value = new Set()
  }
}

function dismissNotification(item) {
  const next = new Set(dismissedNotificationKeys.value)
  next.add(notificationItemKey(item))
  dismissedNotificationKeys.value = next
  localStorage.setItem(notificationStorageKey.value, JSON.stringify([...next].slice(-80)))
}

function openNotificationIncident(item) {
  dismissNotification(item)
  const row = (props.incidents.data || []).find((incident) => Number(incident.id) === Number(item.id))

  if (row) {
    openIncidentModal(row, 'view')
  } else {
    filters.status = item.status || ''
  }

  closeNotificationPanel()
}

function todayDate() {
  const date = new Date()
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

function resetForm() {
  form.clearErrors()
  form.employee_id = ''
  form.incident_date = todayDate()
  form.incident_time = ''
  form.estimated_arrival_at = '08:00'
  form.rest_day_requested = false
  form.rest_day_date = ''
  form.make_up_date = ''
  form.reason = ''
}

function fillForm(row) {
  form.clearErrors()
  form.employee_id = row.employee_id ?? ''
  form.incident_date = String(row.incident_date || '').slice(0, 10)
  form.incident_time = ''
  form.estimated_arrival_at = row.estimated_arrival_at || '08:00'
  form.rest_day_requested = Boolean(row.rest_day_requested)
  form.rest_day_date = String(row.rest_day_date || '').slice(0, 10)
  form.make_up_date = String(row.make_up_date || '').slice(0, 10)
  form.reason = row.reason || ''
}

function toggleRestDayRequest() {
  if (isReadonly.value) return

  form.rest_day_requested = !form.rest_day_requested

  if (form.rest_day_requested) {
    form.rest_day_date = form.rest_day_date || form.incident_date
    return
  }

  form.rest_day_date = ''
  form.make_up_date = ''
}

function openCreateModal() {
  selectedIncident.value = null
  modalMode.value = 'create'
  resetForm()
  modalOpen.value = true
}

function openIncidentModal(row, mode) {
  selectedIncident.value = row
  modalMode.value = mode
  fillForm(row)
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  selectedIncident.value = null
  form.clearErrors()
}

function saveIncident() {
  if (modalMode.value === 'view') return

  const options = {
    preserveScroll: true,
    onSuccess: () => {
      closeModal()
      resetForm()
    },
  }

  if (modalMode.value === 'edit') {
    form.put(route('human-resources.attendance-incidents.update', selectedIncident.value.id), options)
    return
  }

  form.post(route('human-resources.attendance-incidents.store'), options)
}

async function reviewIncident(action, row) {
  const isApproval = action === 'approve'
  const result = await confirmModalAction({
    mode: isApproval ? 'edit' : 'delete',
    entityName: 'incidencia',
    title: isApproval ? 'Aprobar incidencia' : 'Denegar incidencia',
    message: `¿Deseas ${isApproval ? 'aprobar' : 'denegar'} esta incidencia?`,
    confirmText: isApproval ? 'Sí, aprobar' : 'Sí, denegar',
  })

  if (!result.isConfirmed) return

  router.patch(route('human-resources.attendance-incidents.review', row.id), {
    status: isApproval ? 'approved' : 'rejected',
  }, getModalRequestOptions({ mode: 'edit', entityName: 'Incidencia' }))
}

function handleAction({ action, row }) {
  if (action === 'view') {
    openIncidentModal(row, 'view')
    return
  }

  if (action === 'edit') {
    openIncidentModal(row, 'edit')
    return
  }

  if (action === 'approve' || action === 'reject') {
    reviewIncident(action, row)
  }
}

function refreshIncidents(event) {
  if (!event?.action?.startsWith('incident_')) return

  const affectsSelectedIncident =
    Number(event.recordId) === Number(selectedIncident.value?.id)

  if (
    affectsSelectedIncident &&
    (
      event.action === 'incident_deleted' ||
      (
        modalMode.value === 'edit' &&
        ['incident_approved', 'incident_rejected'].includes(event.action)
      )
    )
  ) {
    closeModal()
  }

  refreshRealtimeProps(page, ['incidents', 'employees', 'notificationSummary'], {
    onSuccess: () => {
      if (!modalOpen.value || modalMode.value !== 'view' || !selectedIncident.value?.id) return

      const updatedIncident = (props.incidents?.data || []).find((incident) =>
        Number(incident.id) === Number(selectedIncident.value.id)
      )

      if (!updatedIncident) return

      selectedIncident.value = updatedIncident
      fillForm(updatedIncident)
    },
  })
}

onMounted(() => {
  loadDismissedNotifications()
  unsubscribeIncidents = subscribePrivateRealtime(
    REALTIME_CHANNELS.user(page.props.auth.user.id),
    REALTIME_EVENTS.attendanceChanged,
    refreshIncidents,
  )
})

onBeforeUnmount(() => {
  clearTimeout(filterTimer)
  unsubscribeIncidents?.()
})
</script>

<template>
  <PageLayout>

    <GlobalToolbar
      v-bind="toolbarConfig"
      :search="filters.search"
      @update:search="filters.search = $event"
      @update:filter="handleToolbarFilter"
      @update:records-per-page="filters.per_page = $event"
      @action="handleToolbarAction"
    />

    <section
      v-if="notificationPanelOpen"
      class="overflow-hidden rounded-2xl border border-secondary bg-background shadow-xl"
    >
      <div class="border-b border-secondary bg-primary px-4 py-4 text-white">
        <div class="flex items-start justify-between gap-3">
          <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-white/80">
              Incidencias
            </p>
            <h3 class="mt-1 text-lg font-black">
              {{ notificationPanelTitle }}
            </h3>
            <p class="mt-1 text-xs text-white/80">
              {{ notificationPanelSubtitle }}
            </p>
          </div>

          <button
            type="button"
            class="flex h-8 w-8 items-center justify-center rounded-xl bg-white/10 text-white transition hover:bg-white/20"
            @click="closeNotificationPanel"
          >
            <span class="material-symbols-outlined text-[18px]">close</span>
          </button>
        </div>
      </div>

      <div v-if="notificationItems.length" class="max-h-[360px] space-y-2 overflow-y-auto bg-secondary p-3">
        <button
          v-for="item in notificationItems"
          :key="item.id"
          type="button"
          class="w-full rounded-xl border border-secondary bg-background p-3 text-left shadow-sm transition hover:border-primary"
          @click="openNotificationIncident(item)"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate text-sm font-black text-text">
                {{ item.employee_name }}
              </p>
              <p class="mt-1 text-xs text-text opacity-65">
                Incidencia {{ item.status_label }}
              </p>
            </div>

            <span
              class="shrink-0 rounded-full px-2 py-1 text-[11px] font-black"
              :class="{
                'bg-amber-100 text-amber-700': item.status === 'pending',
                'bg-emerald-100 text-emerald-700': item.status === 'approved',
                'bg-red-100 text-red-700': item.status === 'rejected',
              }"
            >
              {{ item.status_label }}
            </span>
          </div>
        </button>
      </div>

      <div v-else class="bg-secondary p-4 text-sm text-text opacity-70">
        No hay avisos de incidencias por atender.
      </div>
    </section>

    <GlobalTable
      :items="incidents.data || []"
      :columns="columns"
      :actions="actions"
      :pagination="incidents"
      mobile-card-header-field="employee_name"
      no-data-message="No hay incidencias registradas."
      @action="handleAction"
      @page-change="handlePageChange"
    />

    <GlobalModal
      v-if="modalOpen"
      :title="modalTitle"
      :mode="modalMode"
      :total-errors="totalErrors"
      :processing="form.processing"
      :save-button-text="modalSaveText"
      size="md"
      height="auto"
      :columns="2"
      @save="saveIncident"
      @close="closeModal"
    >
      <SelectField
        v-model="form.employee_id"
        label="Empleado"
        field="incident-employee"
        :options="employees"
        :disabled="isReadonly"
        :error="form.errors.employee_id"
      />
      <InputField
        v-model="form.incident_date"
        label="Fecha de incidencia"
        field="incident-date"
        type="date"
        :readonly="isReadonly"
        :error="form.errors.incident_date"
      />
      <TimeField
        v-model="form.estimated_arrival_at"
        label="Llegada estimada"
        field="incident-time"
        class="xl:col-span-2"
        :readonly="isReadonly"
        :error="form.errors.estimated_arrival_at"
      />
      <div class="xl:col-span-2 rounded-[24px] border border-secondary bg-secondary/30 p-4">
        <SelectionCheckboxCard
          compact
          title="Día de descanso con reposición"
          description="Marca esta opción si el empleado pidió descansar un día y lo repondrá en otra fecha."
          :checked="form.rest_day_requested"
          :disabled="isReadonly"
          @toggle="toggleRestDayRequest"
        />
        <div
          v-if="form.rest_day_requested"
          class="mt-4 grid gap-4 sm:grid-cols-2"
        >
          <InputField
            v-model="form.rest_day_date"
            label="Día solicitado"
            field="incident-rest-day-date"
            type="date"
            :readonly="isReadonly"
            :error="form.errors.rest_day_date"
          />
          <InputField
            v-model="form.make_up_date"
            label="Día de reposición"
            field="incident-make-up-date"
            type="date"
            :readonly="isReadonly"
            :error="form.errors.make_up_date"
          />
        </div>
      </div>
      <InputField
        v-model="form.reason"
        label="Motivo"
        field="incident-reason"
        type="textarea"
        rows="4"
        class="xl:col-span-2"
        :readonly="isReadonly"
        :error="form.errors.reason"
      />
    </GlobalModal>
  </PageLayout>
</template>
