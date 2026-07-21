<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import MetricCard from '@/Components/Cards/MetricCard.vue'
import GlobalCard from '@/Components/Cards/GlobalCard.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import { ErrorAlert, ToastAlert } from '@/Components/Modales/UniversalActionModal'
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribePrivateRealtime } from '@/realtime'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    records: { type: Object, default: () => ({ data: [] }) },
    dashboard: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    options: { type: Object, default: () => ({}) },
    canManage: Boolean,
    canRegister: Boolean,
    canRequestCorrection: Boolean,
    canReviewCorrections: Boolean,
    canExport: Boolean,
})

const page = usePage()
const registering = ref(false)
let unsubscribeAttendance = null
const filters = reactive({
    from: props.filters.from || '', to: props.filters.to || '', branch: props.filters.branch || '',
    department: props.filters.department || '', employee: props.filters.employee || '', type: props.filters.type || '',
})
const attendanceType = ref('check_in')

const columns = [
    { key: 'employee', label: 'Empleado' }, { key: 'role', label: 'Rol' }, { key: 'branch', label: 'Sucursal' },
    { key: 'date', label: 'Fecha' }, { key: 'time', label: 'Hora' }, { key: 'type', label: 'Tipo' },
    { key: 'status', label: 'Estado' }, { key: 'authentication', label: 'Autenticación' },
]
const metricCards = computed(() => [
    ['Presentes', props.dashboard.present || 0, 'success'], ['Retardos', props.dashboard.late || 0, 'danger'],
    ['En comida', props.dashboard.meal || 0, 'neutral'], ['En descanso', props.dashboard.break || 0, 'neutral'],
    ['Trabajo remoto', props.dashboard.remote || 0, 'neutral'], ['Empleados activos', props.dashboard.activeEmployees || 0, 'dark'],
])

function applyFilters() {
    router.get(route('systems.attendance.index'), filters, { preserveState: true, preserveScroll: true, replace: true })
}

function clearFilters() {
    Object.keys(filters).forEach((key) => { filters[key] = '' })
    applyFilters()
}

function pageChange(url) { router.visit(url, { preserveScroll: true, preserveState: true }) }

async function registerAttendance() {
    registering.value = true
    try {
        const verification = await verifyDeviceAuthentication()
        const location = await resolveLocation()
        router.post(route('systems.attendance.store'), {
            type: attendanceType.value,
            latitude: location?.latitude ?? null,
            longitude: location?.longitude ?? null,
            accuracy: location?.accuracy ?? null,
            authenticationMethod: verification.method,
            authenticationVerified: true,
            device: deviceDetails(),
        }, {
            preserveScroll: true,
            onSuccess: () => ToastAlert({ title: 'Asistencia registrada correctamente' }),
            onError: () => ErrorAlert({ title: 'No fue posible registrar la asistencia', message: 'Revisa los datos e inténtalo nuevamente.' }),
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
    const available = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
    if (!available) throw new Error('Configura huella, rostro o un bloqueo seguro en este dispositivo para continuar.')

    // The browser performs the local verification. The server records only its result and never biometric data.
    return { method: 'platform_biometric' }
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
onBeforeUnmount(() => unsubscribeAttendance?.())
</script>

<template>
    <PageLayout>
        <GlobalCard class="p-5 md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-xl font-bold text-text">Sistema de Asistencias</p>
                    <p class="mt-1 text-sm text-text opacity-70">Registra y consulta la asistencia del personal con validación del dispositivo.</p>
                </div>
                <div v-if="canRegister" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <SelectField v-model="attendanceType" hide-label field="attendance-type" :options="options.types" />
                    <AppButton :disabled="registering" @click="registerAttendance">
                        <span class="material-symbols-outlined mr-2 text-[19px]">fingerprint</span>
                        {{ registering ? 'Validando dispositivo...' : 'Registrar asistencia' }}
                    </AppButton>
                </div>
            </div>
        </GlobalCard>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <MetricCard v-for="([label, value, tone]) in metricCards" :key="label" :label="label" :value="value" :tone="tone" />
        </section>

        <GlobalCard class="p-4 md:p-5">
            <form class="grid gap-3 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="applyFilters">
                <InputField v-model="filters.from" hide-label type="date" field="attendance-from" placeholder="Desde" />
                <InputField v-model="filters.to" hide-label type="date" field="attendance-to" placeholder="Hasta" />
                <SelectField v-if="canManage" v-model="filters.branch" hide-label field="attendance-branch" placeholder="Todas las sucursales" :options="options.branches" />
                <SelectField v-if="canManage" v-model="filters.department" hide-label field="attendance-department" placeholder="Todos los departamentos" :options="options.departments" />
                <SelectField v-if="canManage" v-model="filters.employee" hide-label field="attendance-employee" placeholder="Todo el personal" :options="options.employees" />
                <SelectField v-model="filters.type" hide-label field="attendance-filter-type" placeholder="Todos los tipos" :options="options.types" />
                <div class="flex flex-wrap gap-2 xl:col-span-2">
                    <AppButton type="submit">Filtrar</AppButton>
                    <AppButton variant="secondary" type="button" @click="clearFilters">Limpiar</AppButton>
                    <a v-if="canExport" :href="route('systems.attendance.export-excel', filters)"><AppButton variant="secondary" type="button">Exportar Excel</AppButton></a>
                    <a v-if="canExport" :href="route('systems.attendance.export-pdf', filters)"><AppButton variant="secondary" type="button">Exportar PDF</AppButton></a>
                </div>
            </form>
        </GlobalCard>

        <GlobalTable :items="records.data || []" :columns="columns" :pagination="records" mobile-card-header-field="employee" no-data-message="No hay asistencias para los filtros seleccionados." @page-change="pageChange" />
    </PageLayout>
</template>
