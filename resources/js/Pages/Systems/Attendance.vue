<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import MetricCard from '@/Components/Cards/MetricCard.vue'
import GlobalCard from '@/Components/Cards/GlobalCard.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import { ErrorAlert, ToastAlert } from '@/Components/Modales/UniversalActionModal'
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribePrivateRealtime } from '@/realtime'
import { usePermissions } from '@/Composables/usePermissions'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    records: { type: Object, default: () => ({ data: [] }) },
    dashboard: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    canViewAttendance: Boolean,
    canManage: Boolean,
    canViewEvidence: Boolean,
    canRegister: Boolean,
    canRequestCorrection: Boolean,
    canReviewCorrections: Boolean,
    passkeyEnabled: Boolean,
})

const page = usePage()
const { can } = usePermissions()
const canExportExcel = computed(() => can('attendance.export.excel'))
const canExportPdf = computed(() => can('attendance.export.pdf'))
const registering = ref(false)
let unsubscribeAttendance = null
let filterTimer = null
const filters = reactive({
    from: props.filters.from || '', to: props.filters.to || '', branch: props.filters.branch || '',
    department: props.filters.department || '', employee: props.filters.employee || '', type: props.filters.type || '',
})
const attendanceType = ref('check_in')
const passkeyReady = ref(props.passkeyEnabled)
const selfie = ref(null)
const evidenceRecord = ref(null)
const typeFilterOptions = computed(() => [
    { value: '', label: 'Todos los tipos' },
    ...(props.options.types || []),
])
const branchFilterOptions = computed(() => [
    { value: '', label: 'Todas las sucursales' },
    ...(props.options.branches || []),
])
const departmentFilterOptions = computed(() => [
    { value: '', label: 'Todos los departamentos' },
    ...(props.options.departments || []),
])
const employeeFilterOptions = computed(() => [
    { value: '', label: 'Todo el personal' },
    ...(props.options.employees || []),
])

const columns = [
    { key: 'employee', label: 'Empleado' }, { key: 'role', label: 'Rol' }, { key: 'branch', label: 'Sucursal' },
    { key: 'date', label: 'Fecha' }, { key: 'time', label: 'Hora' }, { key: 'type', label: 'Tipo' },
    { key: 'status', label: 'Estado' }, { key: 'authentication', label: 'Autenticación' },
]
const metricCards = computed(() => [
    ['Presentes', props.dashboard.present || 0, 'success'], ['Retardos', props.dashboard.late || 0, 'danger'],
    ['En comida', props.dashboard.meal || 0, 'neutral'], ['Empleados activos', props.dashboard.activeEmployees || 0, 'dark'],
])
const attendanceActions = [
    {
        id: 'view-evidence',
        label: 'Ver evidencia',
        icon: 'location_on',
        variant: 'blue',
        permission: 'attendance.manage',
        hidden: (row) => Boolean(row?.id) && !row.evidence,
    },
]

watch(filters, () => {
    clearTimeout(filterTimer)
    filterTimer = setTimeout(() => {
        router.get(
            route('human-resources.attendance.index'),
            { ...filters },
            { preserveState: true, preserveScroll: true, replace: true },
        )
    }, 350)
}, { deep: true })

function pageChange(url) { router.visit(url, { preserveScroll: true, preserveState: true }) }

function handleAttendanceAction({ action, row }) {
    if (action === 'view-evidence' && row.evidence) evidenceRecord.value = row
}

async function registerAttendance() {
    registering.value = true
    try {
        if (!selfie.value) throw new Error('Toma una foto de asistencia antes de registrar.')
        const verification = await verifyDeviceAuthentication()
        const location = await resolveLocation()
        if (!location) {
            ErrorAlert({
                title: 'Ubicación requerida',
                message: 'Activa y permite la ubicación precisa para registrar asistencia dentro del perímetro autorizado.',
            })
            return
        }
        if (!location) throw new Error('Activa y permite la ubicación precisa para registrar asistencia dentro del perímetro de tu sucursal.')
        router.post(route('human-resources.attendance.store'), {
            type: attendanceType.value,
            latitude: location?.latitude ?? null,
            longitude: location?.longitude ?? null,
            accuracy: location?.accuracy ?? null,
            authenticationMethod: verification.method,
            device: deviceDetails(),
            selfie: selfie.value,
        }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => ToastAlert({ title: 'Asistencia registrada correctamente' }),
            onError: (errors) => ErrorAlert({ title: 'No fue posible registrar la asistencia', message: Object.values(errors || {})[0] || 'Revisa los datos e inténtalo nuevamente.' }),
        })
    } catch (error) {
        ErrorAlert({ title: 'Validación del dispositivo requerida', message: error?.message || 'No fue posible validar la autenticación del dispositivo.' })
    } finally {
        registering.value = false
    }
}

async function verifyDeviceAuthentication() {
    if (!window.PublicKeyCredential || !navigator.credentials?.get) {
        throw new Error('Este navegador no permite autenticación segura del dispositivo.')
    }
    if (!passkeyReady.value) throw new Error('Primero configura la biometría de este dispositivo.')

    const { data } = await window.axios.get('/passkeys/confirm/options', { headers: { Accept: 'application/json' } })
    const credential = await navigator.credentials.get({ publicKey: toPublicKeyOptions(data.options) })
    await window.axios.post('/passkeys/confirm', { credential: credentialToJson(credential) }, { headers: { Accept: 'application/json' } })
    return { method: 'platform_biometric' }
}

async function registerPasskey() {
    registering.value = true
    try {
        if (!window.PublicKeyCredential || !navigator.credentials?.create) {
            throw new Error('Este navegador no permite configurar una credencial segura. En iPhone, abre la aplicación directamente en Safari.')
        }
        const { data } = await window.axios.get('/user/passkeys/options', { headers: { Accept: 'application/json' } })
        const credential = await navigator.credentials.create({ publicKey: toPublicKeyOptions(data.options) })
        await window.axios.post('/user/passkeys', {
            name: `${deviceDetails().type} de ${page.props.auth.user.name}`,
            credential: credentialToJson(credential),
        }, { headers: { Accept: 'application/json' } })
        passkeyReady.value = true
        ToastAlert({ title: 'Huella o rostro configurado correctamente' })
    } catch (error) {
        ErrorAlert({ title: 'No fue posible configurar la biometría', message: error?.response?.data?.message || error?.message || 'Inténtalo nuevamente.' })
    } finally { registering.value = false }
}

function base64UrlToBuffer(value) {
    const source = String(value).replace(/-/g, '+').replace(/_/g, '/')
    const padded = source + '='.repeat((4 - source.length % 4) % 4)
    return Uint8Array.from(atob(padded), (character) => character.charCodeAt(0)).buffer
}

function bufferToBase64Url(buffer) {
    return btoa(String.fromCharCode(...new Uint8Array(buffer))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

function toPublicKeyOptions(options) {
    const normalized = { ...options, challenge: base64UrlToBuffer(options.challenge) }
    if (normalized.user?.id) normalized.user = { ...normalized.user, id: base64UrlToBuffer(normalized.user.id) }
    if (normalized.allowCredentials) normalized.allowCredentials = normalized.allowCredentials.map((item) => ({ ...item, id: base64UrlToBuffer(item.id) }))
    if (normalized.excludeCredentials) normalized.excludeCredentials = normalized.excludeCredentials.map((item) => ({ ...item, id: base64UrlToBuffer(item.id) }))
    return normalized
}

function credentialToJson(credential) {
    const response = credential.response
    return {
        id: credential.id, rawId: bufferToBase64Url(credential.rawId), type: credential.type,
        response: {
            clientDataJSON: bufferToBase64Url(response.clientDataJSON),
            ...(response.attestationObject ? { attestationObject: bufferToBase64Url(response.attestationObject) } : {}),
            ...(response.authenticatorData ? { authenticatorData: bufferToBase64Url(response.authenticatorData) } : {}),
            ...(response.signature ? { signature: bufferToBase64Url(response.signature) } : {}),
            ...(response.userHandle ? { userHandle: bufferToBase64Url(response.userHandle) } : {}),
        },
        clientExtensionResults: credential.getClientExtensionResults(),
    }
}

function resolveLocation() {
    if (!navigator.geolocation) return Promise.resolve(null)
    return new Promise((resolve) => navigator.geolocation.getCurrentPosition(
        (position) => resolve({ latitude: position.coords.latitude, longitude: position.coords.longitude, accuracy: Math.round(position.coords.accuracy) }),
        () => resolve(null),
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
    ))
}

function deviceDetails() {
    const agent = navigator.userAgent || ''
    return {
        operatingSystem: /Android/i.test(agent) ? 'Android' : /iPhone|iPad/i.test(agent) ? 'iOS' : /Windows/i.test(agent) ? 'Windows' : /Mac OS/i.test(agent) ? 'macOS' : 'Desconocido',
        browser: /Edg\//.test(agent) ? 'Microsoft Edge' : /Chrome\//.test(agent) ? 'Google Chrome' : /Firefox\//.test(agent) ? 'Mozilla Firefox' : /Safari\//.test(agent) ? 'Safari' : 'Desconocido',
        type: /Mobile|Android|iPhone/i.test(agent) ? 'Móvil' : /iPad|Tablet/i.test(agent) ? 'Tableta' : 'Escritorio',
    }
}

onMounted(() => {
    unsubscribeAttendance = subscribePrivateRealtime(
        REALTIME_CHANNELS.user(page.props.auth.user.id),
        REALTIME_EVENTS.attendanceChanged,
        () => router.reload({ only: ['records', 'dashboard'], preserveScroll: true, preserveState: true }),
    )
})
onBeforeUnmount(() => {
    clearTimeout(filterTimer)
    unsubscribeAttendance?.()
})
</script>

<template>
    <PageLayout>
        <GlobalCard title="Sistema de Asistencias" icon="" :clickable="false" class="p-5 md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xl font-bold text-text">Sistema de Asistencias</p>
                    <p class="mt-1 text-sm text-text opacity-70">Registra y consulta la asistencia del personal con validación del dispositivo.</p>
                </div>
                <div v-if="canRegister" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <SelectField v-model="attendanceType" hide-label field="attendance-type" :options="options.types" />
                    <label class="flex h-11 cursor-pointer items-center rounded-xl border border-secondary bg-background px-3 text-sm font-medium text-text hover:border-primary">
                        <span class="material-symbols-outlined mr-2 text-lg">photo_camera</span>
                        {{ selfie ? 'Foto lista' : 'Tomar foto de asistencia' }}
                        <input class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" capture="user" @change="selfie = $event.target.files?.[0] || null">
                    </label>
                    <AppButton :disabled="registering" @click="registerAttendance">
                        <span class="material-symbols-outlined mr-2 text-[19px]">fingerprint</span>
                        {{ registering ? 'Validando dispositivo...' : 'Registrar asistencia' }}
                    </AppButton>
                    <AppButton v-if="!passkeyReady" :disabled="registering" variant="secondary" @click="registerPasskey">
                        {{ passkeyReady ? 'Reconfigurar biometría' : 'Configurar biometría' }}
                    </AppButton>
                </div>
            </div>
        </GlobalCard>

        <template v-if="canViewAttendance">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard v-for="([label, value, tone]) in metricCards" :key="label" :label="label" :value="value" :tone="tone" />
            </section>

            <GlobalCard title="Filtros de asistencia" icon="" :clickable="false" class="p-4 md:p-5">
                <div class="grid items-end gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <p class="text-sm font-semibold text-text">Fecha inicial</p>
                        <p class="mb-2 text-xs text-text opacity-60">Muestra registros desde esta fecha.</p>
                        <InputField v-model="filters.from" hide-label type="date" field="attendance-from" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-text">Fecha final</p>
                        <p class="mb-2 text-xs text-text opacity-60">Muestra registros hasta esta fecha.</p>
                        <InputField v-model="filters.to" hide-label type="date" field="attendance-to" />
                    </div>
                    <div v-if="canManage">
                        <p class="text-sm font-semibold text-text">Sucursal</p>
                        <p class="mb-2 text-xs text-text opacity-60">Limita los resultados a una sucursal.</p>
                        <SelectField v-model="filters.branch" hide-label field="attendance-branch" placeholder="Todas las sucursales" :options="branchFilterOptions" />
                    </div>
                    <div v-if="canManage">
                        <p class="text-sm font-semibold text-text">Departamento</p>
                        <p class="mb-2 text-xs text-text opacity-60">Consulta un departamento específico.</p>
                        <SelectField v-model="filters.department" hide-label field="attendance-department" placeholder="Todos los departamentos" :options="departmentFilterOptions" />
                    </div>
                    <div v-if="canManage">
                        <p class="text-sm font-semibold text-text">Personal</p>
                        <p class="mb-2 text-xs text-text opacity-60">Consulta la asistencia de una persona.</p>
                        <SelectField v-model="filters.employee" hide-label field="attendance-employee" placeholder="Todo el personal" :options="employeeFilterOptions" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-text">Tipo de registro</p>
                        <p class="mb-2 text-xs text-text opacity-60">Filtra entradas, comida o salidas.</p>
                        <SelectField v-model="filters.type" hide-label field="attendance-filter-type" placeholder="Todos los tipos" :options="typeFilterOptions" />
                    </div>
                    <div v-if="canExportExcel || canExportPdf" class="flex flex-wrap gap-2 xl:col-span-2">
                        <a v-if="canExportExcel" :href="route('human-resources.attendance.export-excel', filters)"><AppButton variant="secondary" type="button">Exportar Excel</AppButton></a>
                        <a v-if="canExportPdf" :href="route('human-resources.attendance.export-pdf', filters)"><AppButton variant="secondary" type="button">Exportar PDF</AppButton></a>
                    </div>
                </div>
            </GlobalCard>

            <GlobalTable
                :items="records.data || []"
                :columns="columns"
                :actions="attendanceActions"
                :pagination="records"
                mobile-card-header-field="employee"
                no-data-message="No hay asistencias para los filtros seleccionados."
                @action="handleAttendanceAction"
                @page-change="pageChange"
            />
        </template>

        <GlobalModal
            v-if="evidenceRecord"
            title="Evidencia de asistencia"
            subtitle="Fotografía y ubicación registradas al marcar asistencia."
            size="md"
            height="auto"
            :columns="1"
            :show-footer="false"
            @close="evidenceRecord = null"
        >
            <div class="grid gap-4 md:grid-cols-[minmax(220px,0.85fr)_minmax(0,1.15fr)] md:items-start">
                <div class="overflow-hidden rounded-2xl border border-secondary bg-secondary shadow-sm">
                    <img
                        :src="evidenceRecord.evidence.photo_url"
                        :alt="`Foto de asistencia de ${evidenceRecord.employee}`"
                        class="aspect-[16/10] w-full object-cover md:aspect-[4/5]"
                    >
                </div>

                <div class="flex min-w-0 flex-col gap-3">
                    <div class="rounded-2xl border border-secondary bg-secondary p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-text opacity-60">Empleado</p>
                        <p class="mt-1 font-semibold text-text">{{ evidenceRecord.employee }}</p>
                        <p class="text-sm text-text opacity-70">{{ evidenceRecord.date }} · {{ evidenceRecord.time }}</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-secondary bg-background p-3">
                            <p class="text-xs text-text opacity-60">Precisión GPS</p>
                            <p class="mt-1 font-semibold text-text">{{ evidenceRecord.evidence.accuracy_meters ?? 'No disponible' }}<span v-if="evidenceRecord.evidence.accuracy_meters !== null" class="ml-1 text-xs font-medium">m</span></p>
                        </div>
                        <div class="rounded-2xl border border-secondary bg-background p-3">
                            <p class="text-xs text-text opacity-60">Distancia al perímetro</p>
                            <p class="mt-1 font-semibold text-text">{{ evidenceRecord.evidence.distance_meters ?? 'No disponible' }}<span v-if="evidenceRecord.evidence.distance_meters !== null" class="ml-1 text-xs font-medium">m</span></p>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-secondary bg-background p-4">
                        <p class="text-xs font-semibold uppercase tracking-wide text-text opacity-60">Ubicación registrada</p>
                        <p class="mt-1 text-sm font-medium text-text">{{ evidenceRecord.evidence.location_label }}</p>
                        <p class="mt-2 text-xs text-text opacity-70">{{ evidenceRecord.evidence.latitude }}, {{ evidenceRecord.evidence.longitude }}</p>
                        <p v-if="evidenceRecord.evidence.radius_meters" class="mt-1 text-xs text-text opacity-70">Radio autorizado: {{ evidenceRecord.evidence.radius_meters }} m</p>
                    </div>

                    <a :href="evidenceRecord.evidence.map_url" target="_blank" rel="noopener noreferrer" class="mt-auto block">
                        <AppButton type="button" variant="secondary" class="w-full justify-center">
                            <span class="material-symbols-outlined mr-2 text-[18px]">map</span>
                            Ver en mapa
                        </AppButton>
                    </a>
                </div>
            </div>
        </GlobalModal>
    </PageLayout>
</template>
