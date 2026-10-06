<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import { GlobalTable } from '@/Components/Tables'
import { GlobalToolbar } from '@/Components/Toolbars'
import { GlobalModal, confirmModalAction, getModalRequestOptions } from '@/Components/Modales'
import InputField from '@/Components/Forms/InputField.vue'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { usePermissions } from '@/Composables/usePermissions'
import { REALTIME_CHANNELS, REALTIME_EVENTS, refreshRealtimeProps, subscribeRealtime } from '@/realtime'

defineOptions({ layout: AdminLayout })

const props = defineProps({
  customers: { type: Object, default: () => ({ data: [] }) },
  filters: { type: Object, default: () => ({}) },
})

const page = usePage()
const { can } = usePermissions()
const { handlePageChange } = useGlobalTablePagination()
const search = ref(props.filters.search || '')
const recordsPerPage = ref(Number(props.filters.per_page || props.customers?.per_page || 25))
const showModal = ref(false)
const modalMode = ref('create')
const selectedCustomer = ref(null)
let searchTimer = null
let unsubscribeCustomerChanged = null
let unsubscribeCreditAccountChanged = null

const form = useForm({
  first_name: '',
  last_name: '',
  credit_limit: '',
})

const rows = computed(() => props.customers?.data || [])
const readonly = computed(() => modalMode.value === 'view')
const modalTitle = computed(() => ({
  create: 'Nuevo cliente',
  edit: 'Actualizar cliente',
  view: 'Detalle del cliente',
}[modalMode.value]))

const columns = [
  { key: 'first_name', label: 'Nombre', format: 'text', minWidth: '180px' },
  { key: 'last_name', label: 'Apellido', format: 'text', minWidth: '180px' },
  { key: 'credit_limit_label', label: 'Límite de deuda', format: 'text', minWidth: '150px' },
  { key: 'sales_count', label: 'Ventas', format: 'number', minWidth: '90px' },
  { key: 'created_at_label', label: 'Registro', format: 'text', minWidth: '120px' },
]

const actions = [
  { id: 'view', label: 'Ver', icon: 'visibility', variant: 'blue', permission: 'sales.customers.view' },
  { id: 'edit', label: 'Editar', icon: 'edit', variant: 'amber', permission: 'sales.customers.update' },
  { id: 'delete', label: 'Eliminar', icon: 'delete', variant: 'red', permission: 'sales.customers.delete' },
]

const toolbarConfig = computed(() => ({
  icon: 'groups',
  title: 'Clientes',
  subtitle: 'Personas independientes de empleados y usuarios que pueden comprar a crédito.',
  search: search.value,
  searchPlaceholder: 'Buscar por nombre o apellido...',
  showSearch: true,
  filters: [],
  actions: can('sales.customers.create') ? [{
    id: 'create',
    label: 'Nuevo cliente',
    icon: 'person_add',
    variant: 'primary',
  }] : [],
  recordsPerPage: recordsPerPage.value,
  showRecordsPerPage: true,
  totalRecords: Number(props.customers?.total || 0),
  filteredRecords: rows.value.length,
}))

function fillForm(customer = null) {
  form.clearErrors()
  form.first_name = customer?.first_name || ''
  form.last_name = customer?.last_name || ''
  form.credit_limit = customer?.credit_limit ?? ''
}

function openModal(mode, customer = null) {
  modalMode.value = mode
  selectedCustomer.value = customer
  fillForm(customer)
  showModal.value = true
}

function closeModal() {
  showModal.value = false
  selectedCustomer.value = null
  form.clearErrors()
}

function reloadCustomers() {
  router.get(route('ventas.customers.index'), {
    search: search.value || undefined,
    per_page: recordsPerPage.value,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}

function scheduleReload() {
  window.clearTimeout(searchTimer)
  searchTimer = window.setTimeout(reloadCustomers, 250)
}

function refreshCustomers(event = null) {
  if (event?.action === 'deleted' && Number(event.customerId) === Number(selectedCustomer.value?.id)) {
    closeModal()
  }

  refreshRealtimeProps(page, ['customers'], {
    onSuccess: () => {
      if (modalMode.value !== 'view' || !selectedCustomer.value?.id) return
      const updated = rows.value.find((customer) => Number(customer.id) === Number(selectedCustomer.value.id))
      if (!updated) return
      selectedCustomer.value = updated
      fillForm(updated)
    },
  })
}

function submit() {
  if (readonly.value) return closeModal()

  const options = getModalRequestOptions({
    mode: modalMode.value,
    entityName: 'Cliente',
    close: closeModal,
    successTitle: modalMode.value === 'create'
      ? 'Cliente creado correctamente'
      : 'Cliente actualizado correctamente',
    errorTitle: 'No se pudo guardar el cliente',
    errorMessage: 'Revisa los datos capturados.',
  })

  if (modalMode.value === 'edit') {
    form.put(route('ventas.customers.update', selectedCustomer.value.id), options)
    return
  }

  form.post(route('ventas.customers.store'), options)
}

async function deleteCustomer(customer) {
  const result = await confirmModalAction({
    mode: 'delete',
    entityName: 'cliente',
    title: 'Eliminar cliente',
    message: `¿Deseas eliminar a ${customer.name}? Sus ventas anteriores permanecerán en el histórico. Si todavía tiene deuda, el sistema impedirá la eliminación.`,
    confirmText: 'Sí, eliminar',
  })

  if (!result.isConfirmed) return

  form.delete(route('ventas.customers.destroy', customer.id), getModalRequestOptions({
    mode: 'delete',
    entityName: 'Cliente',
    successTitle: 'Cliente eliminado correctamente',
    errorTitle: 'No se pudo eliminar el cliente',
    errorMessage: 'Verifica que no tenga adeudos pendientes.',
  }))
}

function handleAction({ action, row }) {
  if (action === 'view') openModal('view', row)
  if (action === 'edit') openModal('edit', row)
  if (action === 'delete') void deleteCustomer(row)
}

watch(search, scheduleReload)
watch(recordsPerPage, reloadCustomers)

onMounted(() => {
  unsubscribeCustomerChanged = subscribeRealtime(
    REALTIME_CHANNELS.systems,
    REALTIME_EVENTS.customerChanged,
    refreshCustomers,
  )
  unsubscribeCreditAccountChanged = subscribeRealtime(
    REALTIME_CHANNELS.systems,
    REALTIME_EVENTS.creditAccountChanged,
    refreshCustomers,
  )
})

onBeforeUnmount(() => {
  window.clearTimeout(searchTimer)
  unsubscribeCustomerChanged?.()
  unsubscribeCreditAccountChanged?.()
})
</script>

<template>
  <Head title="Clientes" />

  <PageLayout>
    <template #toolbar>
      <GlobalToolbar
        v-bind="toolbarConfig"
        @update:search="search = $event"
        @update:records-per-page="recordsPerPage = $event"
        @action="(action) => action === 'create' && openModal('create')"
      />
    </template>

    <GlobalTable
      :items="rows"
      :columns="columns"
      :actions="actions"
      :pagination="customers"
      row-key="id"
      mobile-card-header-field="name"
      no-data-message="No hay clientes registrados para los filtros seleccionados."
      @page-change="handlePageChange"
      @row-click="(row) => openModal('view', row)"
      @action="handleAction"
    />

    <GlobalModal
      v-if="showModal"
      :title="modalTitle"
      subtitle="Este registro no necesita estar ligado a un empleado ni a un usuario del sistema."
      :mode="modalMode"
      :processing="form.processing"
      :total-errors="Object.keys(form.errors).length"
      :show-save="!readonly"
      :save-button-text="modalMode === 'edit' ? 'Guardar cambios' : 'Crear cliente'"
      close-button-text="Cerrar"
      size="lg"
      height="compact"
      :columns="1"
      @save="submit"
      @close="closeModal"
    >
      <form class="space-y-4" @submit.prevent="submit">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
          <InputField
            v-model="form.first_name"
            label="Nombre"
            field="first_name"
            placeholder="Ej. Juan"
            :readonly="readonly"
            :error="form.errors.first_name"
          />
          <InputField
            v-model="form.last_name"
            label="Apellido"
            field="last_name"
            placeholder="Ej. Pérez"
            :readonly="readonly"
            :error="form.errors.last_name"
          />
        </div>

        <InputField
          v-model="form.credit_limit"
          label="Límite de deuda"
          field="credit_limit"
          type="number"
          prefix="$"
          placeholder="0.00"
          :readonly="readonly"
          :error="form.errors.credit_limit"
        />
      </form>
    </GlobalModal>
  </PageLayout>
</template>
