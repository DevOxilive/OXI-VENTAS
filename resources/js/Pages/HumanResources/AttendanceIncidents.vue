<script setup>
import { onBeforeUnmount, onMounted } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalCard from '@/Components/Cards/GlobalCard.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import InputField from '@/Components/Forms/InputField.vue'
import TimeField from '@/Components/Forms/TimeField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales'
import { usePermissions } from '@/Composables/usePermissions'
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribePrivateRealtime } from '@/realtime'
defineOptions({ layout: AdminLayout })
const props = defineProps({ incidents: { type: Object, default: () => ({ data: [] }) }, employees: { type: Array, default: () => [] } })
const page = usePage()
let unsubscribeIncidents = null
const { can } = usePermissions()
const form = useForm({ employee_id: '', incident_date: new Date().toISOString().slice(0, 10), incident_time: '', estimated_arrival_at: '', reason: '' })
const columns = [{ key: 'incident_date', label: 'Fecha' }, { key: 'employee.first_name', label: 'Empleado' }, { key: 'reason', label: 'Motivo' }, { key: 'status', label: 'Estado', format: 'badge' }]
const actions = [
  { id: 'view', label: 'Ver', icon: 'visibility', variant: 'blue', mobile: 'button', permission: 'attendance.incidents.view' },
  { id: 'edit', label: 'Editar', icon: 'edit', variant: 'amber', mobile: 'button', permission: 'attendance.incidents.update', hidden: row => row.status !== 'pending' },
  { id: 'delete', label: 'Eliminar', icon: 'delete', variant: 'red', mobile: 'button', permission: 'attendance.incidents.delete', hidden: row => row.status !== 'pending' },
  { id: 'approve', label: 'Aprobar', icon: 'check_circle', variant: 'green', mobile: 'button', permission: 'attendance.incidents.approve', hidden: row => row.status !== 'pending' },
  { id: 'reject', label: 'Rechazar', icon: 'cancel', variant: 'red', mobile: 'button', permission: 'attendance.incidents.reject', hidden: row => row.status !== 'pending' },
]
function save() { form.post(route('human-resources.attendance-incidents.store'), { onSuccess: () => form.reset('incident_time','estimated_arrival_at','reason') }) }
async function handleAction({ action, row }) { if (action !== 'approve' && action !== 'reject') return; const result = await confirmModalAction({ mode: action === 'approve' ? 'edit' : 'delete', entityName: 'incidencia', title: action === 'approve' ? 'Aprobar incidencia' : 'Rechazar incidencia', message: `¿Deseas ${action === 'approve' ? 'aprobar' : 'rechazar'} esta incidencia?`, confirmText: action === 'approve' ? 'Sí, aprobar' : 'Sí, rechazar' }); if (result.isConfirmed) router.patch(route('human-resources.attendance-incidents.review', row.id), { status: action === 'approve' ? 'approved' : 'rejected' }, getModalRequestOptions({ mode: 'edit', entityName: 'Incidencia' })) }
onMounted(() => {
  unsubscribeIncidents = subscribePrivateRealtime(
    REALTIME_CHANNELS.user(page.props.auth.user.id),
    REALTIME_EVENTS.attendanceChanged,
    ({ action }) => {
      if (action?.startsWith('incident_')) {
        router.reload({ only: ['incidents', 'employees'], preserveScroll: true, preserveState: true })
      }
    },
  )
})

onBeforeUnmount(() => unsubscribeIncidents?.())
</script>

<template>
  <PageLayout>
    <GlobalCard title="Incidencias" icon="event_note" :clickable="false" class="p-5 md:p-6"><p class="text-sm text-text opacity-70">Registra excepciones y autoriza su efecto sobre la clasificación de asistencia.</p></GlobalCard>
    <GlobalCard v-if="can('attendance.incidents.create')" title="Nueva incidencia" icon="add_circle" :clickable="false" class="p-5">
      <form class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="save">
        <SelectField v-model="form.employee_id" label="Empleado" field="incident-employee" :options="employees" :error="form.errors.employee_id" />
        <InputField v-model="form.incident_date" label="Fecha" field="incident-date" type="date" />
        <TimeField v-model="form.estimated_arrival_at" label="Llegada estimada" field="incident-time" />
        <InputField v-model="form.reason" label="Motivo" field="incident-reason" class="md:col-span-2 xl:col-span-3" :error="form.errors.reason" />
        <div class="xl:col-span-4"><AppButton :disabled="form.processing" type="submit">Enviar incidencia</AppButton></div>
      </form>
    </GlobalCard>
    <GlobalTable :items="incidents.data || []" :columns="columns" :actions="actions" :pagination="incidents" mobile-card-header-field="reason" no-data-message="No hay incidencias registradas." @action="handleAction" />
  </PageLayout>
</template>
