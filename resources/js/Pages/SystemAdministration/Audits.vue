<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, reactive } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribeRealtime } from '@/realtime'

const props = defineProps({ audits: Object, filters: Object, modules: Array })
const form = reactive({ search: props.filters.search ?? '', module: props.filters.module ?? '', from: props.filters.from ?? '', to: props.filters.to ?? '' })
const filter = () => router.get(route('system-administration.audits.index'), form, { preserveState: true, replace: true })
const moduleLabels = {
    authentication: 'Autenticación',
    login: 'Autenticación',
    'system-trash': 'Papelera Global',
    user: 'Usuarios',
    employee: 'Empleados',
    branch: 'Sucursales',
    customer: 'Clientes',
    product: 'Productos',
    category: 'Categorías',
    'cash-register-closure': 'Cortes de caja',
    'physical-count': 'Conteos físicos',
    'physical-count-entry': 'Registros de conteo',
    'purchase-report': 'Listas de compra',
    'ticket-template': 'Plantillas de ticket',
}
const actionLabels = {
    create: 'Crear',
    update: 'Actualizar',
    delete: 'Eliminar',
    restore: 'Restaurar',
    restore_many: 'Restaurar varios',
    restore_all: 'Restaurar todos',
    force_delete: 'Eliminar definitivamente',
    empty: 'Vaciar papelera',
    purge_expired: 'Depurar vencidos',
    login: 'Iniciar sesión',
    logout: 'Cerrar sesión',
}
const auditRows = computed(() => props.audits.data.map((audit) => ({
    ...audit,
    module: moduleLabels[audit.module] ?? 'Sistema',
    action: actionLabels[audit.action] ?? 'Acción registrada',
})))
const columns = [
    { key: 'occurred_at', label: 'Fecha', format: 'date' },
    { key: 'user_name', label: 'Usuario', subKey: 'role_name', formatOptions: { multiline: true } },
    { key: 'module', label: 'Módulo' },
    { key: 'action', label: 'Acción' },
    { key: 'record_label', label: 'Registro' },
]
const moduleOptions = [{ value: '', label: 'Todos los módulos' }, ...props.modules.map((module) => ({ value: module, label: moduleLabels[module] ?? 'Sistema' }))]
let unsubscribeAuditRealtime = null
let refreshTimer = null

function refreshAudits() {
    clearTimeout(refreshTimer)
    refreshTimer = setTimeout(() => {
        router.reload({ only: ['audits', 'modules'], preserveScroll: true })
    }, 120)
}

onMounted(() => {
    unsubscribeAuditRealtime = subscribeRealtime(
        REALTIME_CHANNELS.systems,
        REALTIME_EVENTS.systemAuditChanged,
        refreshAudits,
    )
})

onBeforeUnmount(() => {
    clearTimeout(refreshTimer)
    unsubscribeAuditRealtime?.()
})
</script>

<template>
    <Head title="Auditoría del Sistema" />
    <AdminLayout>
        <PageLayout>
            <GlobalToolbar title="Auditoría del Sistema" subtitle="Historial centralizado de actividades críticas." :back-button="true" back-label="Centro de Administración" :show-search="false" :show-records-per-page="false" :show-counter="false" @back="router.get(route('system-administration.index'))" />
            <form class="grid gap-3 rounded-2xl border border-secondary bg-background p-4 md:grid-cols-4" @submit.prevent="filter">
                <InputField v-model="form.search" hide-label placeholder="Buscar" />
                <SelectField v-model="form.module" hide-label :options="moduleOptions" />
                <InputField v-model="form.from" hide-label type="date" />
                <AppButton type="submit">Filtrar</AppButton>
            </form>
            <GlobalTable :items="auditRows" :columns="columns" :pagination="audits" mobile-card-header-field="user_name" no-data-message="No hay registros para los filtros seleccionados." @page-change="(url) => router.get(url, {}, { preserveState: true })" />
        </PageLayout>
    </AdminLayout>
</template>
