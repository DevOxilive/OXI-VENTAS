<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { Head, router, useForm, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import { GlobalTable } from '@/Components/Tables'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import { GlobalModal } from '@/Components/Modales'
import SelectField from '@/Components/Forms/SelectField.vue'
import InputField from '@/Components/Forms/InputField.vue'
import MultiSelectDropdown from '@/Components/Forms/MultiSelectDropdown.vue'
import { ErrorAlert, ToastAlert } from '@/Components/Modales/UniversalActionModal'
import { useGlobalTablePagination } from '@/Composables/useGlobalTablePagination'
import { usePermissions } from '@/Composables/usePermissions'
import {
  connectQzTray,
  getDefaultQzPrinter,
  getQzPrinters,
  getStoredPrinterName,
  printEscPosTicket,
  saveStoredPrinterName,
} from '@/Composables/useQzTray'
import {
  buildEscPosTicketData,
  normalizeTicketTemplate,
} from '@/config/ticketTemplate'

defineOptions({ layout: AdminLayout })

const props = defineProps({
  accounts: { type: Object, default: () => ({ data: [] }) },
  paymentMethods: { type: Array, default: () => [] },
  currentPaymentBranch: { type: Object, default: null },
  ticketTemplate: { type: Object, default: null },
  filters: { type: Object, default: () => ({}) },
})

const page = usePage()
const { can } = usePermissions()
const selected = ref(null)
const selectedLimit = ref(null)
const loading = ref(false)
const search = ref(props.filters.search || '')
const recordsPerPage = ref(Number(props.filters.per_page || props.accounts?.per_page || 25))
const { handlePageChange } = useGlobalTablePagination()
const availablePrinters = ref([])
const selectedPrinterName = ref(getStoredPrinterName())
const printerBridgeReady = ref(false)
const printerBridgeMessage = ref('Conecta QZ Tray para imprimir tickets.')
const TICKET_LOGO_URL = '/icons/super-kay-ticket-bw.png'
let ticketLogoDataUrlPromise = null
const ticketHeaderDataUrlPromises = new Map()

const payment = useForm({
  payment_method_id: props.paymentMethods[0]?.id || '',
  selected_charge_ids: [],
  amount: '',
  cash_received: '',
  confirmed_card_payment: false,
})

const limitForm = useForm({
  credit_limit: '',
})

const rows = computed(() => props.accounts?.data || [])
const canPrintEmployeeCredit = computed(() => can('sales.employee-credit.print'))
const money = (value) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(Number(value || 0))
const compactNumber = (value) => new Intl.NumberFormat('es-MX', { maximumFractionDigits: 3 }).format(Number(value || 0))

const columns = [
  { key: 'employee', label: 'Empleado', format: 'text', minWidth: '240px' },
  { key: 'balance', label: 'Adeudo', format: 'currency', minWidth: '140px', mobileBadge: true },
  { key: 'credit_limit_label', label: 'Límite', format: 'text', minWidth: '140px' },
  { key: 'estimated_payment_date', label: 'Pago estimado', format: 'text', minWidth: '150px' },
]

const actions = [
  { id: 'view', label: 'Ver', icon: 'visibility', variant: 'blue', permission: 'sales.employee-credit.view' },
  { id: 'pay', label: 'Pagar', icon: 'payments', variant: 'green', permission: 'sales.employee-credit.collect' },
  { id: 'limit', label: 'Editar límite', icon: 'edit_note', variant: 'amber', permission: 'sales.employee-credit.create' },
]

const toolbarConfig = computed(() => ({
  icon: 'account_balance_wallet',
  title: 'Estados de Cuenta',
  subtitle: 'Adeudos vigentes, cargos por ticket y abonos recibidos.',
  search: search.value,
  searchPlaceholder: 'Buscar empleado por nombre...',
  showSearch: true,
  filters: [],
  recordsPerPage: recordsPerPage.value,
  showRecordsPerPage: true,
  totalRecords: Number(props.accounts?.total || 0),
  filteredRecords: rows.value.length,
}))

const selectedPaymentMethod = computed(() =>
  props.paymentMethods.find((method) => String(method.id) === String(payment.payment_method_id))
)

const printerOptions = computed(() =>
  availablePrinters.value.map((printerName) => ({ label: printerName, value: printerName }))
)

const ticketOptions = computed(() =>
  (selected.value?.charges || []).map((charge) => ({
    value: String(charge.id),
    label: `${charge.folio || 'Sin folio'} · ${charge.date || 'Sin fecha'} · ${money(charge.outstanding_amount)}`,
  }))
)

const selectedChargesForPayment = computed(() => {
  const selectedIds = new Set((payment.selected_charge_ids || []).map((id) => String(id)))
  const charges = selected.value?.charges || []

  if (!selected.value?.paying) return charges
  if (selectedIds.size === 0) return []

  return charges.filter((charge) => selectedIds.has(String(charge.id)))
})

const selectedTicketsTotal = computed(() =>
  selectedChargesForPayment.value.reduce((total, charge) => total + Number(charge.outstanding_amount || 0), 0)
)

const resolvedTicketTemplate = computed(() => {
  const base = normalizeTicketTemplate(
    props.ticketTemplate?.settings || {}
  )

  return normalizeTicketTemplate(base)
})

const isCashPayment = computed(() =>
  String(selectedPaymentMethod.value?.name || '').toLowerCase().includes('efectivo')
)

const isCardPayment = computed(() => {
  const methodName = String(selectedPaymentMethod.value?.name || '').toLowerCase()
  return methodName.includes('tarjeta') || methodName.includes('card') || methodName.includes('credito') || methodName.includes('debito')
})

const changeDue = computed(() => {
  if (!isCashPayment.value) return 0
  return Math.max(0, Number(payment.cash_received || 0) - Number(payment.amount || 0))
})

const groupedCharges = computed(() => {
  const groups = new Map()
  const selectedIds = new Set((payment.selected_charge_ids || []).map((id) => String(id)))

  ;(selected.value?.charges || []).forEach((charge) => {
    if (selected.value?.paying && selectedIds.size > 0 && !selectedIds.has(String(charge.id))) return

    const key = charge.date_key || 'sin-fecha'
    if (!groups.has(key)) {
      groups.set(key, {
        key,
        label: charge.date_label || 'Sin fecha',
        total: 0,
        charges: [],
      })
    }

    const group = groups.get(key)
    group.total += Number(charge.outstanding_amount || 0)
    group.charges.push(charge)
  })

  return Array.from(groups.values())
})

function saleDateLabel(group) {
  if (!group?.key || group.key === 'sin-fecha') return group?.label || 'Sin fecha'

  const [year, month, day] = String(group.key).split('-').map(Number)
  const date = new Date(year, Number(month || 1) - 1, Number(day || 1), 12)
  const label = new Intl.DateTimeFormat('es-MX', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
  }).format(date)

  return label.charAt(0).toUpperCase() + label.slice(1)
}

function quantityLabel(item) {
  const unit = String(item?.sale_unit || '').toLowerCase()
  const label = unit === 'box'
    ? 'Caja'
    : unit === 'kg' || unit === 'kilo'
      ? 'kg'
      : 'Pza'

  return `${compactNumber(item?.quantity)} ${label}`
}

function reloadAccounts() {
  router.get(route('ventas.employee-credit.index'), {
    search: search.value || undefined,
    per_page: recordsPerPage.value,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}

watch(search, reloadAccounts)
watch(recordsPerPage, reloadAccounts)
watch(() => payment.payment_method_id, () => {
  payment.confirmed_card_payment = false
  if (!isCashPayment.value) payment.cash_received = ''
})
watch(() => payment.selected_charge_ids, () => {
  payment.amount = selected.value?.paying ? Number(selectedTicketsTotal.value || 0).toFixed(2) : ''
}, { deep: true })

async function openAccount(row, pay = false) {
  loading.value = true
  try {
    const { data } = await window.axios.get(route('ventas.employee-credit.show', { account: row.id }))
    selected.value = { ...data.account, paying: pay }
    payment.selected_charge_ids = pay ? (data.account.charges || []).map((charge) => String(charge.id)) : []
    payment.amount = pay ? Number((data.account.charges || []).reduce((total, charge) => total + Number(charge.outstanding_amount || 0), 0)).toFixed(2) : ''
    payment.cash_received = ''
    payment.payment_method_id = props.paymentMethods[0]?.id || ''
    payment.confirmed_card_payment = false
    payment.clearErrors()
    return selected.value
  } catch {
    ErrorAlert({ title: 'No se pudo abrir la cuenta', message: 'Intenta nuevamente.' })
    return null
  } finally {
    loading.value = false
  }
}

function openLimit(row) {
  selectedLimit.value = row
  limitForm.credit_limit = row.credit_limit ?? ''
  limitForm.clearErrors()
}

function handleTableAction({ action, row }) {
  if (action === 'view') void openAccount(row, false)
  if (action === 'pay') void openAccount(row, true)
  if (action === 'limit') openLimit(row)
}

function detectPreferredPrinter(printers = []) {
  return printers.find((printerName) => {
    const text = String(printerName || '').toLowerCase()
    return text.includes('3nstar') || text.includes('rpt006') || text.includes('pos-58') || text.includes('pos58')
  }) || printers[0] || ''
}

async function initializePrinterBridge({ silent = true } = {}) {
  try {
    await connectQzTray()
    const printers = await getQzPrinters()
    availablePrinters.value = printers
    printerBridgeReady.value = true

    let printerName = selectedPrinterName.value || getStoredPrinterName()
    if (!printerName || !printers.includes(printerName)) {
      try {
        const defaultPrinter = await getDefaultQzPrinter()
        printerName = printers.includes(defaultPrinter) ? defaultPrinter : ''
      } catch {
        printerName = ''
      }
    }
    if (!printerName || !printers.includes(printerName)) {
      printerName = detectPreferredPrinter(printers)
    }

    selectedPrinterName.value = printerName || ''
    saveStoredPrinterName(selectedPrinterName.value)
    printerBridgeMessage.value = selectedPrinterName.value
      ? `Impresora lista: ${selectedPrinterName.value}`
      : 'QZ Tray conectado. Selecciona la impresora del ticket.'
  } catch (error) {
    printerBridgeReady.value = false
    availablePrinters.value = []
    printerBridgeMessage.value = error?.message || 'QZ Tray no esta conectado en esta computadora.'
    if (!silent) {
      ErrorAlert({ title: 'No se pudo conectar la impresora', message: printerBridgeMessage.value })
    }
  }
}

function handlePrinterChange(value) {
  selectedPrinterName.value = value || ''
  saveStoredPrinterName(selectedPrinterName.value)
  printerBridgeReady.value = false
  printerBridgeMessage.value = selectedPrinterName.value
    ? `Impresora seleccionada: ${selectedPrinterName.value}. Falta verificar la conexión.`
    : 'Selecciona una impresora para continuar.'
}

function blobToDataUrl(blob) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result || ''))
    reader.onerror = () => reject(reader.error || new Error('No se pudo leer el logo del ticket.'))
    reader.readAsDataURL(blob)
  })
}

async function getTicketLogoDataUrl() {
  if (typeof window === 'undefined') return ''

  if (!ticketLogoDataUrlPromise) {
    ticketLogoDataUrlPromise = window.fetch(TICKET_LOGO_URL, { cache: 'force-cache' })
      .then((response) => response.ok ? response.blob() : null)
      .then((blob) => blob ? blobToDataUrl(blob) : '')
      .catch(() => '')
  }

  return ticketLogoDataUrlPromise
}

function imageFromDataUrl(dataUrl) {
  return new Promise((resolve, reject) => {
    const image = new Image()
    image.onload = () => resolve(image)
    image.onerror = () => reject(new Error('No se pudo preparar el encabezado del ticket.'))
    image.src = dataUrl
  })
}

async function getTicketHeaderDataUrl(cashBoxText = '') {
  const normalizedCashBoxText = String(cashBoxText || '').trim()
  const cacheKey = normalizedCashBoxText || '__default__'
  if (ticketHeaderDataUrlPromises.has(cacheKey)) return ticketHeaderDataUrlPromises.get(cacheKey)

  const promise = getTicketLogoDataUrl().then(async (logoDataUrl) => {
    if (!logoDataUrl || typeof document === 'undefined') return logoDataUrl

    const logoImage = await imageFromDataUrl(logoDataUrl)
    const canvas = document.createElement('canvas')
    canvas.width = 576
    canvas.height = 92
    const context = canvas.getContext('2d')
    if (!context) return logoDataUrl

    context.fillStyle = '#ffffff'
    context.fillRect(0, 0, canvas.width, canvas.height)
    context.drawImage(logoImage, 12, 5, 82, 82)
    context.fillStyle = '#000000'
    context.font = '700 27px Arial, sans-serif'
    context.textAlign = 'center'
    context.textBaseline = 'middle'
    context.fillText('SUPER KAY', Math.round(canvas.width / 2), Math.round(canvas.height / 2))
    if (normalizedCashBoxText) {
      context.font = '700 21px Arial, sans-serif'
      context.textAlign = 'right'
      context.fillText(normalizedCashBoxText.toUpperCase(), canvas.width - 12, Math.round(canvas.height / 2))
    }

    return canvas.toDataURL('image/png')
  }).catch(() => '')

  ticketHeaderDataUrlPromises.set(cacheKey, promise)
  return promise
}

function buildAccountPrintJob(account) {
  const items = []
  const creditGroups = groupedCharges.value.map((group) => ({
    key: group.key,
    label: saleDateLabel(group),
    total: Number(group.total || 0),
    charges: group.charges.map((charge) => ({
      folio: charge.folio || 'Sin folio',
      date: charge.date,
      total: Number(charge.outstanding_amount || 0),
      items: (charge.items || []).map((item) => ({
        code: item.code || '-',
        product: item.product || 'Producto',
        quantity: Number(item.quantity || 0),
        quantity_label: quantityLabel(item),
        unit_price: Number(item.unit_price || 0),
        subtotal: Number(item.subtotal || 0),
      })),
    })),
  }))

  groupedCharges.value.forEach((group) => {
    items.push({
      product_name: `-- ${group.label} --`,
      quantity: 1,
      unit_price: group.total,
      subtotal: group.total,
      discount_amount: 0,
    })

    group.charges.forEach((charge) => {
      ;(charge.items || []).forEach((item) => {
        items.push({
          product_name: `${charge.folio || 'S/F'} ${item.product || ''}`.trim(),
          quantity: Number(item.quantity || 0),
          unit_price: Number(item.unit_price || 0),
          subtotal: Number(item.subtotal || 0),
          discount_amount: 0,
        })
      })
    })
  })

  return {
    type: 'employee_credit_statement',
    folio: `EDO-${String(account.id).padStart(6, '0')}`,
    date: new Date().toLocaleDateString('es-MX'),
    branch_name: account.employee,
    cash_box_number: '1',
    cash_box_text: 'EDO. CTA.',
    user_name: page.props.auth?.user?.name || '',
    payment_method: 'Estado de cuenta',
    credit_groups: creditGroups,
    items,
    cash_received: 0,
    change_due: 0,
    total: selected.value?.paying ? Number(selectedTicketsTotal.value || 0) : Number(account.balance || 0),
  }
}

async function printAccountTicket() {
  if (!selected.value) return
  if (!canPrintEmployeeCredit.value) {
    ErrorAlert({ title: 'Permiso requerido', message: 'Tu usuario no tiene permiso para imprimir o reimprimir tickets de estado de cuenta.' })
    return
  }
  if (!selectedPrinterName.value) {
    ErrorAlert({ title: 'Impresora requerida', message: 'Selecciona una impresora para imprimir el estado de cuenta.' })
    return
  }
  if (!printerBridgeReady.value) {
    ErrorAlert({ title: 'Impresora no verificada', message: 'Conecta QZ Tray y usa Reconectar antes de imprimir.' })
    return
  }

  try {
    const baseJob = buildAccountPrintJob(selected.value)
    const cashBoxText = baseJob.cash_box_text
    const printJob = {
      ...baseJob,
      ticket_logo_data_url: await getTicketLogoDataUrl(),
      ticket_header_data_url: await getTicketHeaderDataUrl(cashBoxText),
    }
    const printData = buildEscPosTicketData(resolvedTicketTemplate.value, printJob)
    await printEscPosTicket(selectedPrinterName.value, printData, { timeoutMs: 10000 })
    printerBridgeReady.value = true
    printerBridgeMessage.value = `Impresora lista: ${selectedPrinterName.value}`
    ToastAlert({ title: 'Estado de cuenta enviado a la impresora' })
  } catch (error) {
    printerBridgeReady.value = false
    printerBridgeMessage.value = error?.message || 'QZ Tray no pudo imprimir el estado de cuenta.'
    ErrorAlert({ title: 'No se pudo imprimir', message: printerBridgeMessage.value })
  }
}

function submitPayment() {
  if (!selected.value || payment.processing) return
  if (!payment.selected_charge_ids.length) {
    ErrorAlert({ title: 'Selecciona al menos un ticket', message: 'Elige los tickets completos que vas a cobrar.' })
    return
  }
  if (isCardPayment.value && !payment.confirmed_card_payment) {
    ErrorAlert({ title: 'Confirma el cobro', message: 'Marca la confirmación del pago.' })
    return
  }

  payment.post(route('ventas.employee-credit.pay', { account: selected.value.id }), {
    preserveScroll: true,
    onSuccess: async () => {
      if (canPrintEmployeeCredit.value) {
        await printAccountTicket()
      }
      selected.value = null
    },
  })
}

function submitLimit() {
  if (!selectedLimit.value || limitForm.processing) return
  limitForm.put(route('ventas.employee-credit.limit', { account: selectedLimit.value.id }), {
    preserveScroll: true,
    onSuccess: () => { selectedLimit.value = null },
  })
}

onMounted(() => {
  if (canPrintEmployeeCredit.value) {
    void initializePrinterBridge({ silent: true })
  }
})
</script>

<template>
  <Head title="Estados de Cuenta" />

  <PageLayout>
    <template #toolbar>
      <GlobalToolbar
        v-bind="toolbarConfig"
        @update:search="search = $event"
        @update:records-per-page="recordsPerPage = $event"
      />
    </template>

    <GlobalTable
      :items="rows"
      :columns="columns"
      :actions="actions"
      :pagination="accounts"
      row-key="id"
      mobile-card-header-field="employee"
      no-data-message="No hay empleados con adeudo."
      @page-change="handlePageChange"
      @action="handleTableAction"
    >
      <template #cell-credit_limit_label="{ row }">
        <span class="font-semibold" :class="row.credit_limit === null ? 'text-text opacity-55' : 'text-text'">
          {{ row.credit_limit_label }}
        </span>
      </template>
    </GlobalTable>

    <GlobalModal
      v-if="selected"
      :title="selected.employee"
      :subtitle="`Saldo pendiente: ${money(selected.balance)}`"
      size="lg"
      height="compact"
      :columns="1"
      :show-save="selected.paying"
      save-button-text="Registrar abono"
      close-button-text="Cerrar"
      @close="selected = null"
      @save="submitPayment"
    >
      <div class="space-y-4">
        <section class="grid gap-3 md:grid-cols-3">
          <div class="rounded-xl border border-primary bg-primary px-4 py-3 text-white">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] opacity-80">Saldo</p>
            <p class="mt-1 text-2xl font-black leading-none">{{ money(selected.balance) }}</p>
          </div>

          <div class="rounded-xl border border-secondary bg-secondary/40 px-4 py-3">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-text opacity-55">Límite</p>
            <p class="mt-1 text-2xl font-black leading-none text-text">
              {{ selected.credit_limit === null ? 'Sin límite' : money(selected.credit_limit) }}
            </p>
          </div>

          <div class="rounded-xl border border-secondary bg-secondary/40 px-4 py-3">
            <p class="text-[10px] font-black uppercase tracking-[0.18em] text-text opacity-55">Cambio</p>
            <p class="mt-1 text-2xl font-black leading-none text-text">{{ money(changeDue) }}</p>
          </div>
        </section>

        <section v-if="canPrintEmployeeCredit" class="grid gap-3 rounded-xl border border-secondary bg-secondary/30 p-3 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
          <div>
            <SelectField
              label="Impresora"
              field="ticket_printer"
              :model-value="selectedPrinterName"
              :options="printerOptions"
              :disabled="!printerOptions.length"
              :placeholder="printerBridgeReady ? 'Selecciona impresora' : 'QZ Tray no conectado'"
              @update:model-value="handlePrinterChange"
            />
            <p class="mt-2 flex flex-wrap items-center gap-2 text-xs font-semibold text-text opacity-75">
              <span>Estado: {{ printerBridgeReady ? 'Lista' : 'Sin conexión' }}</span>
              <span v-if="selectedPrinterName">{{ selectedPrinterName }}</span>
              <button
                v-if="!printerBridgeReady"
                type="button"
                class="font-black text-primary underline underline-offset-2"
                @click="initializePrinterBridge({ silent: false })"
              >
                Reconectar
              </button>
            </p>
          </div>

          <button
            type="button"
            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-primary bg-primary px-4 text-sm font-bold text-white transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="!selectedPrinterName || !printerBridgeReady"
            @click="printAccountTicket"
          >
            <span class="material-symbols-outlined text-[18px]">print</span>
            Imprimir ticket
          </button>
        </section>

        <section v-if="selected.paying" class="grid gap-3 rounded-xl border border-secondary bg-secondary/40 p-4 md:grid-cols-2">
          <div class="md:col-span-2">
            <label class="mb-1 block text-sm font-semibold text-text">Tickets a pagar</label>
            <MultiSelectDropdown
              v-model="payment.selected_charge_ids"
              :options="ticketOptions"
              placeholder="Selecciona tickets pendientes"
              :floating="true"
            />
            <p v-if="payment.errors.selected_charge_ids" class="mt-1 text-xs font-semibold text-primary">
              {{ payment.errors.selected_charge_ids }}
            </p>
          </div>

          <InputField v-model="payment.amount" label="Cantidad a pagar" field="amount" type="number" prefix="$" readonly :error="payment.errors.amount" />
          <SelectField v-model="payment.payment_method_id" label="Método de pago" field="payment_method_id" :options="paymentMethods" :error="payment.errors.payment_method_id" />
          <InputField v-if="isCashPayment" v-model="payment.cash_received" label="Efectivo recibido" field="cash_received" type="number" prefix="$" :error="payment.errors.cash_received" />

          <button
            v-if="isCardPayment"
            type="button"
            class="flex items-center justify-between gap-3 rounded-xl border px-4 py-3 text-left transition md:col-span-2"
            :class="payment.confirmed_card_payment
              ? 'border-accent bg-secondary text-accent'
              : 'border-primary bg-background text-primary hover:bg-secondary'"
            @click="payment.confirmed_card_payment = !payment.confirmed_card_payment"
          >
            <span class="flex min-w-0 items-center gap-3">
              <span
                class="material-symbols-outlined text-[22px]"
                :class="payment.confirmed_card_payment ? 'text-accent' : 'text-primary'"
              >
                {{ payment.confirmed_card_payment ? 'task_alt' : 'credit_score' }}
              </span>
              <span class="min-w-0">
                <span class="block text-sm font-black">Confirmar pago antes de registrar abono</span>
                <span class="block text-xs font-semibold opacity-70">Marca esta opción para continuar.</span>
              </span>
            </span>
            <span
              class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border"
              :class="payment.confirmed_card_payment
                ? 'border-accent bg-accent text-white'
                : 'border-primary bg-background text-transparent'"
            >
              <span class="material-symbols-outlined text-[16px]">check</span>
            </span>
          </button>
        </section>

        <section>
          <h2 class="mb-2 text-sm font-black uppercase tracking-[0.18em] text-text opacity-60">Compras pendientes</h2>

          <div class="overflow-x-auto rounded-xl border border-secondary">
            <table class="w-full min-w-[820px] text-sm">
              <thead class="bg-secondary text-left text-text">
                <tr>
                  <th class="px-3 py-2">Código</th>
                  <th class="px-3 py-2">Producto</th>
                  <th class="px-3 py-2">Piezas</th>
                  <th class="px-3 py-2">Precio</th>
                  <th class="px-3 py-2">Importe</th>
                </tr>
              </thead>

              <tbody class="divide-y divide-secondary bg-background">
                <template v-for="group in groupedCharges" :key="group.key">
                  <tr class="bg-secondary/80">
                    <td colspan="5" class="px-3 py-2 text-center font-black text-text">
                      Fecha de venta: {{ saleDateLabel(group) }}
                    </td>
                  </tr>

                  <template v-for="charge in group.charges" :key="charge.folio || charge.date">
                    <tr class="bg-background">
                      <td colspan="3" class="px-3 py-2 font-black text-text">
                        Número de ticket: {{ charge.folio || 'Sin folio' }}
                      </td>
                      <td colspan="2" class="px-3 py-2 text-right font-black text-text">
                        Importe total: {{ money(charge.outstanding_amount) }}
                      </td>
                    </tr>

                    <tr v-for="(item, index) in charge.items" :key="`${charge.folio}-${item.code}-${item.product}-${index}`">
                      <td class="px-3 py-2 font-semibold text-text opacity-75">{{ item.code || '-' }}</td>
                      <td class="px-3 py-2 font-semibold text-text">{{ item.product }}</td>
                      <td class="px-3 py-2 text-text opacity-80">{{ quantityLabel(item) }}</td>
                      <td class="px-3 py-2 text-text opacity-80">{{ money(item.unit_price) }}</td>
                      <td class="px-3 py-2 text-text opacity-80">{{ money(item.subtotal) }}</td>
                    </tr>
                  </template>
                </template>
              </tbody>
            </table>
          </div>
        </section>

        <section v-if="selected.payments?.length">
          <h2 class="mb-2 text-sm font-black uppercase tracking-[0.18em] text-text opacity-60">Abonos anteriores</h2>
          <div class="divide-y divide-secondary overflow-hidden rounded-xl border border-secondary bg-background">
            <p v-for="entry in selected.payments" :key="entry.folio" class="flex justify-between gap-3 px-3 py-2 text-sm text-text">
              <span>{{ entry.date }} · {{ entry.folio }} · {{ entry.method }}</span>
              <strong>{{ money(entry.amount) }}</strong>
            </p>
          </div>
        </section>
      </div>
    </GlobalModal>

    <GlobalModal
      v-if="selectedLimit"
      :title="`Límite de ${selectedLimit.employee}`"
      subtitle="Deja el campo vacío si el empleado puede comprar sin límite."
      size="md"
      :columns="1"
      save-button-text="Guardar límite"
      close-button-text="Cancelar"
      @close="selectedLimit = null"
      @save="submitLimit"
    >
      <InputField
        v-model="limitForm.credit_limit"
        label="Límite de crédito"
        field="credit_limit"
        type="number"
        prefix="$"
        :error="limitForm.errors.credit_limit"
      />
    </GlobalModal>
  </PageLayout>
</template>
