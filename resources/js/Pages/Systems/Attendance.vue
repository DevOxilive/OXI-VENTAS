<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import MetricCard from '@/Components/Cards/MetricCard.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import { GlobalToolbar } from '@/Components/Toolbars'
import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import { ErrorAlert, ToastAlert } from '@/Components/Modales/UniversalActionModal'
import { REALTIME_CHANNELS, REALTIME_EVENTS, refreshRealtimeProps, subscribePrivateRealtime } from '@/realtime'
import { usePermissions } from '@/Composables/usePermissions'
import { getAttendanceRegistrationToolbarConfig } from '@/config/ToolbarConfigs/attendanceToolbarConfig'

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
    attendanceShifts: { type: Array, default: () => [] },
    attendanceBranches: { type: Array, default: () => [] },
})

const page = usePage()
const { can } = usePermissions()
const canExportExcel = computed(() => can('attendance.export.excel'))
const canExportPdf = computed(() => can('attendance.export.pdf'))
const registering = ref(false)
let unsubscribeAttendance = null
let unsubscribeUserChanged = null
let filterTimer = null
const attendanceRoutePrefix = computed(() => {
    const currentRoute = typeof route === 'function' ? route().current() : null

    return currentRoute?.startsWith('ventas.attendance')
        ? 'ventas.attendance'
        : 'human-resources.attendance'
})
const filters = reactive({
    from: props.filters.from || '', to: props.filters.to || '', branch: props.filters.branch || '',
    search: props.filters.search || '', type: props.filters.type || '',
    per_page: Number(props.filters.per_page ?? 30),
})
const attendanceType = ref('check_in')
const attendanceShiftId = ref('')
const attendanceBranchId = ref('')
const attendanceShifts = computed(() => props.attendanceShifts || [])
const attendanceBranches = computed(() => props.attendanceBranches || [])
const showAttendanceBranchSelector = computed(() => attendanceBranches.value.length > 1)
const selectedAttendanceShift = computed(() => attendanceShifts.value.find((shift) => (
    String(shift.id ?? '') === String(attendanceShiftId.value)
)) || null)
const registeredTypesTodaySet = computed(() => new Set(selectedAttendanceShift.value?.registered_types || []))
const attendanceTypeOptions = computed(() => (props.options.types || []).map((option) => {
    const alreadyRegistered = registeredTypesTodaySet.value.has(option.value)

    return {
        ...option,
        disabled: alreadyRegistered,
        label: alreadyRegistered ? `${option.label} (registrado hoy)` : option.label,
    }
}))
const availableAttendanceTypeOptions = computed(() =>
    attendanceTypeOptions.value.filter((option) => !option.disabled),
)
const hasAvailableAttendanceTypes = computed(() => availableAttendanceTypeOptions.value.length > 0)
const passkeyReady = ref(props.passkeyEnabled)
const biometricActionLabel = computed(() => (
    passkeyReady.value ? 'Agregar o cambiar Face ID/huella' : 'Configurar Face ID o huella'
))
const selfie = ref(null)
const evidenceRecord = ref(null)
const attendanceRegistrationToolbarConfig = computed(() => getAttendanceRegistrationToolbarConfig({
    filters,
    types: props.options.types || [],
    branches: props.options.branches || [],
    canViewAttendance: props.canViewAttendance,
    total: Number(props.records?.total ?? 0),
}))

const columns = [
    { key: 'employee', label: 'Empleado' }, { key: 'role', label: 'Rol' }, { key: 'branch', label: 'Sucursal' },
    { key: 'shift', label: 'Turno' },
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
    if (!props.canViewAttendance) return

    clearTimeout(filterTimer)
    filterTimer = setTimeout(() => {
        router.get(
            route(`${attendanceRoutePrefix.value}.index`),
            { ...filters },
            { preserveState: true, preserveScroll: true, replace: true },
        )
    }, 350)
}, { deep: true })

watch(availableAttendanceTypeOptions, (options) => {
    if (options.some((option) => option.value === attendanceType.value)) {
        return
    }

    attendanceType.value = options[0]?.value || ''
}, { immediate: true })

watch(attendanceShifts, (shifts) => {
    const activeShift = shifts.find((shift) => String(shift.id ?? '') === String(attendanceShiftId.value))
    if (activeShift) return

    const pendingShift = shifts.find((shift) => (shift.registered_types || []).length < 4) || shifts[0]
    attendanceShiftId.value = String(pendingShift?.id ?? '')
}, { immediate: true })

watch(attendanceBranches, (branches) => {
    if (branches.some((branch) => String(branch.value) === String(attendanceBranchId.value))) return

    attendanceBranchId.value = String(branches[0]?.value ?? '')
}, { immediate: true })

function pageChange(url) { router.visit(url, { preserveScroll: true, preserveState: true }) }

function handleAttendanceFilter({ key, value }) {
    filters[key] = value
}

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
        router.post(route(`${attendanceRoutePrefix.value}.store`), {
            type: attendanceType.value,
            attendance_schedule_assignment_id: attendanceShiftId.value || null,
            attendance_branch_id: attendanceBranchId.value || null,
            latitude: location?.latitude ?? null,
            longitude: location?.longitude ?? null,
            accuracy: location?.accuracy ?? null,
            authenticationMethod: verification.method,
            device: deviceDetails(),
            selfie: selfie.value,
        }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                selfie.value = null
                ToastAlert({ title: 'Asistencia registrada correctamente' })
            },
            onError: (errors) => ErrorAlert({ title: 'No fue posible registrar la asistencia', message: Object.values(errors || {})[0] || 'Revisa los datos e inténtalo nuevamente.' }),
        })
    } catch (error) {
        await showBiometricError({
            title: 'Validacion del dispositivo requerida',
            error,
            fallbackMessage: 'No fue posible validar la autenticacion del dispositivo.',
        })
    } finally {
        registering.value = false
    }
}

async function verifyDeviceAuthentication() {
    await assertPlatformBiometricsAvailable()
    if (!passkeyReady.value) throw new Error('Primero configura la biometría de este dispositivo.')

    const { data } = await window.axios.get('/passkeys/confirm/options', { headers: { Accept: 'application/json' } })
    const credential = await navigator.credentials.get({ publicKey: toPublicKeyOptions(data.options) })
    await window.axios.post('/passkeys/confirm', { credential: credentialToJson(credential) }, { headers: { Accept: 'application/json' } })
    return { method: 'platform_biometric' }
}

async function registerPasskey() {
    registering.value = true
    try {
        await assertPlatformBiometricsAvailable()
        const { data } = await window.axios.get('/user/passkeys/options', { headers: { Accept: 'application/json' } })
        const credential = await navigator.credentials.create({ publicKey: toPublicKeyOptions(data.options) })
        await window.axios.post('/user/passkeys', {
            name: `${deviceDetails().type} de ${page.props.auth.user.name}`,
            credential: credentialToJson(credential),
        }, { headers: { Accept: 'application/json' } })
        passkeyReady.value = true
        ToastAlert({ title: 'Huella o rostro configurado correctamente' })
    } catch (error) {
        await showBiometricError({
            title: 'No fue posible configurar la biometria',
            error,
            fallbackMessage: 'Intentalo nuevamente.',
        })
    } finally { registering.value = false }
}

async function assertPlatformBiometricsAvailable() {
    if (!window.isSecureContext || window.location.protocol !== 'https:') {
        throw new Error('La biometría requiere una conexión HTTPS válida. Abre la URL actual del túnel sin aceptar advertencias de certificado y vuelve a intentarlo.')
    }

    if (!window.PublicKeyCredential || !navigator.credentials?.create || !navigator.credentials?.get) {
        throw new Error('Este navegador no permite autenticación segura. En iPhone abre la aplicación desde Safari; en Android usa Chrome.')
    }

    if (typeof window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable === 'function') {
        const available = await window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
        if (!available) {
            throw new Error('Configura primero una huella, Face ID o bloqueo seguro en este dispositivo.')
        }
    }
}

async function showBiometricError({ title, error, fallbackMessage }) {
    const message = error?.response?.data?.message || error?.message || fallbackMessage

    if (isAndroidTryCloudflareStandalone() && isTlsCertificateError(message)) {
        await clearPwaRuntime()

        ErrorAlert({
            title,
            message: [
                'Android marco esta PWA instalada con un certificado TLS invalido.',
                'Ya limpie el service worker y cache local de esta instalacion.',
                'Cierra esta app instalada, abre la URL actual desde Chrome normal y vuelve a instalar Super-Kay desde ese mismo enlace.',
                `<a href="${window.location.href}" target="_blank" rel="noopener" class="font-semibold text-primary underline">Abrir esta URL en Chrome</a>`,
            ].join('<br><br>'),
        })

        return
    }

    ErrorAlert({ title, message })
}

function isTlsCertificateError(message) {
    return /tls|certificate|certificado|secure connection|conexion segura|conexión segura/i.test(String(message || ''))
}

function isAndroidTryCloudflareStandalone() {
    if (typeof window === 'undefined') return false

    const isAndroid = /Android/i.test(window.navigator.userAgent || '')
    const isTryCloudflare = window.location.hostname === 'trycloudflare.com'
        || window.location.hostname.endsWith('.trycloudflare.com')
    const isStandalone = window.matchMedia?.('(display-mode: standalone)')?.matches
        || window.navigator.standalone === true

    return isAndroid && isTryCloudflare && isStandalone
}

async function clearPwaRuntime() {
    if (typeof window === 'undefined') return

    if ('serviceWorker' in navigator) {
        const registrations = await navigator.serviceWorker.getRegistrations()
        await Promise.all(registrations.map((registration) => registration.unregister()))
    }

    if ('caches' in window) {
        const cacheNames = await window.caches.keys()
        await Promise.all(cacheNames.map((cacheName) => window.caches.delete(cacheName)))
    }
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
        () => refreshRealtimeProps(page, ['records', 'dashboard', 'attendanceShifts', 'attendanceBranches']),
    )
    unsubscribeUserChanged = subscribePrivateRealtime(
        REALTIME_CHANNELS.user(page.props.auth.user.id),
        REALTIME_EVENTS.userChanged,
        (event) => {
            if (Number(event?.userId) !== Number(page.props.auth.user.id)) return

            refreshRealtimeProps(page, [
                    'records', 'dashboard', 'options', 'canViewAttendance',
                    'canManage', 'canViewEvidence', 'canRegister', 'canRequestCorrection',
                    'canReviewCorrections', 'passkeyEnabled', 'attendanceShifts', 'attendanceBranches',
            ])
        },
    )
})
onBeforeUnmount(() => {
    clearTimeout(filterTimer)
    unsubscribeAttendance?.()
    unsubscribeUserChanged?.()
})
</script>

<template>
    <PageLayout>
        <GlobalToolbar
            v-bind="attendanceRegistrationToolbarConfig"
            :search="filters.search"
            @update:search="filters.search = $event"
            @update:filter="handleAttendanceFilter"
            @update:records-per-page="filters.per_page = $event"
        >
            <template #actions>
                <div v-if="canRegister || canExportExcel || canExportPdf" class="flex w-full flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-end">
                    <div v-if="canRegister && showAttendanceBranchSelector" class="min-w-56">
                        <SelectField v-model="attendanceBranchId" hide-label field="attendance-branch" :options="attendanceBranches" :disabled="registering" />
                    </div>
                    <div v-if="canRegister" class="min-w-56 rounded-xl border border-secondary bg-secondary px-4 py-2.5">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-text opacity-60">Horario actual</p>
                        <p class="mt-0.5 text-sm font-semibold text-text">{{ selectedAttendanceShift?.label || 'Sin horario pendiente' }}</p>
                    </div>
                    <div v-if="canRegister" class="min-w-48">
                        <SelectField v-model="attendanceType" hide-label field="attendance-type" :options="attendanceTypeOptions" :disabled="!hasAvailableAttendanceTypes" />
                    </div>
                    <AppButton v-if="canRegister && hasAvailableAttendanceTypes" as="label" variant="secondary" class="h-11 cursor-pointer">
                        <span class="material-symbols-outlined mr-2 text-lg">photo_camera</span>
                        {{ selfie ? 'Foto lista' : 'Tomar foto de asistencia' }}
                        <input class="sr-only" type="file" accept="image/jpeg,image/png,image/webp" capture="user" @change="selfie = $event.target.files?.[0] || null">
                    </AppButton>
                    <AppButton v-if="canRegister" :disabled="registering || !hasAvailableAttendanceTypes" @click="registerAttendance">
                        <span class="material-symbols-outlined mr-2 text-[19px]">fingerprint</span>
                        {{ !hasAvailableAttendanceTypes ? 'Asistencia del dia completa' : registering ? 'Validando dispositivo...' : 'Registrar asistencia' }}
                    </AppButton>
                    <AppButton v-if="canRegister" :disabled="registering" variant="secondary" @click="registerPasskey">
                        <span class="material-symbols-outlined mr-2 text-[19px]">face</span>
                        {{ biometricActionLabel }}
                    </AppButton>
                    <AppButton v-if="canExportExcel" as="a" :href="route('human-resources.attendance.export-excel', filters)" variant="secondary">
                        <span class="material-symbols-outlined mr-2 text-[19px]">download</span>
                        Exportar Excel
                    </AppButton>
                    <AppButton v-if="canExportPdf" as="a" :href="route('human-resources.attendance.export-pdf', filters)" variant="secondary">
                        <span class="material-symbols-outlined mr-2 text-[19px]">picture_as_pdf</span>
                        Exportar PDF
                    </AppButton>
                </div>
            </template>
        </GlobalToolbar>

        <template v-if="canViewAttendance">
            <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <MetricCard v-for="([label, value, tone]) in metricCards" :key="label" :label="label" :value="value" :tone="tone" />
            </section>

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
