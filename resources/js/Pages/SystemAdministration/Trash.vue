<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalTable from '@/Components/Tables/GlobalTable.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectField from '@/Components/Forms/SelectField.vue'
import AppButton from '@/Components/Buttons/AppButton.vue'
import { ToastAlert, UniversalActionModal } from '@/Components/Modales/UniversalActionModal'
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribeRealtime } from '@/realtime'
import { usePermissions } from '@/Composables/usePermissions'

const props = defineProps({ resources: Array, resource: String, records: Object, filters: Object })
const { can } = usePermissions()
const form = reactive({
    resource: props.resource,
    search: props.filters.search ?? '',
    period: props.filters.period ?? '',
    from: props.filters.from ?? '',
    to: props.filters.to ?? '',
})
const selectedItems = ref({})
const selectedIds = computed(() => Object.keys(selectedItems.value).filter((id) => selectedItems.value[id]).map(Number))
const currentLabel = computed(() => props.resources.find((item) => item.key === props.resource)?.label ?? 'Registros')
const resourceOptions = [
    { value: 'all', label: 'Todos los módulos' },
    ...props.resources.map((item) => ({ value: item.key, label: item.label })),
]
const periodOptions = [
    { value: '', label: 'Todos los periodos' },
    { value: 'today', label: 'Hoy' },
    { value: 'last_7_days', label: 'Últimos 7 días' },
    { value: 'last_30_days', label: 'Últimos 30 días' },
    { value: 'last_90_days', label: 'Últimos 90 días' },
    { value: 'expired', label: 'Vencidos para depuración' },
]
const columns = [{ key: 'module', label: 'Módulo' }, { key: 'label', label: 'Registro' }, { key: 'deleted_at', label: 'Eliminado el', format: 'datetime' }]
const actions = [
    { id: 'restore', label: 'Restaurar', icon: 'restore', variant: 'blue', permission: 'system.trash.restore' },
    { id: 'force-delete', label: 'Eliminar definitivamente', icon: 'delete_forever', variant: 'red', permission: 'system.trash.force-delete' },
]

let unsubscribeTrashRealtime = null
let refreshTimer = null

function clearSelection() {
    selectedItems.value = {}
}

function refreshTrash() {
    clearTimeout(refreshTimer)
    refreshTimer = setTimeout(() => {
        router.reload({
            only: ['records'],
            preserveScroll: true,
            onSuccess: clearSelection,
        })
    }, 120)
}

onMounted(() => {
    unsubscribeTrashRealtime = subscribeRealtime(
        REALTIME_CHANNELS.systems,
        REALTIME_EVENTS.systemTrashChanged,
        (event) => {
            // "all" is the aggregate view, so it must react to changes from every recoverable module.
            if (props.resource === 'all' || event?.resource === props.resource) refreshTrash()
        },
    )
})

onBeforeUnmount(() => {
    clearTimeout(refreshTimer)
    clearTimeout(searchTimer)
    unsubscribeTrashRealtime?.()
})

const filter = () => router.get(route('system-administration.trash.index'), form, { preserveState: true, replace: true })
let searchTimer = null
watch(() => form.search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(filter, 350)
})
watch([() => form.resource, () => form.period, () => form.from, () => form.to], filter)
const clearFilters = () => {
    form.search = ''
    form.period = ''
    form.from = ''
    form.to = ''
    filter()
}
async function restore(id, resource = form.resource) {
    const result = await UniversalActionModal({
        title: 'Restaurar registro original',
        html: '<p class="text-sm">Se recuperará el mismo registro con su información, relaciones, rol, permisos y contraseña cifrada originales.</p>',
        confirmText: 'Restaurar completamente',
        icon: 'question',
        focusConfirm: true,
        reverseButtons: false,
    })

    if (result.isConfirmed) {
        router.post(route('system-administration.trash.restore', { resource, record: id }), {}, {
            preserveScroll: true,
            onSuccess: () => {
                clearSelection()
                ToastAlert({ icon: 'success', title: '✓ Registro restaurado completamente' })
            },
        })
    }

}
const restoreMany = () => selectedIds.value.length && router.post(route('system-administration.trash.restore-many', { resource: props.resource }), { ids: selectedIds.value }, { preserveScroll: true, onSuccess: () => { clearSelection(); ToastAlert({ icon: 'success', title: '✓ Registros restaurados correctamente' }) } })
const restoreAll = () => router.post(route('system-administration.trash.restore-all', { resource: props.resource }), {}, { preserveScroll: true, onSuccess: () => { clearSelection(); ToastAlert({ icon: 'success', title: '✓ Registros restaurados correctamente' }) } })

async function confirmPermanent(action, callback) {
    const result = await UniversalActionModal({
        title: 'Confirmar eliminación definitiva',
        message: `Esta acción eliminará permanentemente registros de ${currentLabel.value}. No se puede deshacer.`,
        confirmText: 'Eliminar definitivamente',
        icon: 'warning',
    })

    if (result.isConfirmed) callback()
}

async function confirmPurgeExpired() {
    const result = await UniversalActionModal({
        title: 'Depurar registros vencidos',
        message: 'Solo se eliminarán definitivamente registros no críticos que superaron su periodo de retención. Las ventas, cortes y conteos físicos no se incluirán.',
        confirmText: 'Depurar registros vencidos',
        icon: 'warning',
    })

    if (result.isConfirmed) {
        router.delete(route('system-administration.trash.purge-expired'), {
            data: { confirmation: 'ELIMINAR' },
            preserveScroll: true,
            onSuccess: () => {
                clearSelection()
                ToastAlert({ icon: 'success', title: '✓ Depuración completada' })
            },
        })
    }
}

function handleAction({ action, row }) {
    if (action === 'restore') restore(row.id, row.resource)
    if (action === 'force-delete') confirmPermanent(action, () => router.delete(route('system-administration.trash.force-delete', { resource: row.resource, record: row.id }), { data: { confirmation: 'ELIMINAR' }, preserveScroll: true, onSuccess: () => { clearSelection(); ToastAlert({ icon: 'success', title: '✓ Registro eliminado permanentemente' }) } }))
}

function handleToolbarAction(action) {
    if (action === 'restore-many') restoreMany()
    if (action === 'restore-all') restoreAll()
    if (action === 'purge-expired') confirmPurgeExpired()
    if (action === 'empty') confirmPermanent(action, () => router.delete(route('system-administration.trash.empty', { resource: props.resource }), { data: { confirmation: 'ELIMINAR' }, preserveScroll: true, onSuccess: () => { clearSelection(); ToastAlert({ icon: 'success', title: '✓ Papelera vaciada correctamente' }) } }))
}
</script>

<template>
    <Head title="Papelera Global" />
    <AdminLayout>
        <PageLayout>
            <GlobalToolbar
                title="Papelera Global"
                subtitle="Los registros eliminados permanecen disponibles para recuperación."
                :back-button="true"
                back-label="Centro de Administración"
                :show-search="false"
                :show-records-per-page="false"
                :show-counter="false"
                :actions="[
                    ...(form.resource !== 'all' ? [
                        { id: 'restore-many', label: 'Restaurar seleccionados', icon: 'restore', variant: 'secondary', disabled: !selectedIds.length },
                        { id: 'restore-all', label: 'Restaurar todos', icon: 'restore_page', variant: 'secondary' },
                    ] : []),
                    ...(can('system.trash.empty') ? [
                        { id: 'purge-expired', label: 'Depurar vencidos', icon: 'auto_delete', variant: 'secondary' },
                        { id: 'empty', label: 'Vaciar papelera', icon: 'delete_forever', variant: 'danger' },
                    ] : []),
                ]"
                @back="router.get(route('system-administration.index'))"
                @action="handleToolbarAction"
            />

            <form class="grid gap-3 rounded-2xl border border-secondary bg-background p-4 md:grid-cols-2 xl:grid-cols-[minmax(180px,0.7fr)_minmax(240px,1.25fr)_minmax(180px,0.7fr)_minmax(150px,0.55fr)_minmax(150px,0.55fr)_auto]" @submit.prevent="filter">
                <SelectField v-model="form.resource" hide-label :options="resourceOptions" />
                <InputField v-model="form.search" hide-label placeholder="Buscar en registros eliminados" />
                <SelectField v-model="form.period" hide-label :options="periodOptions" />
                <InputField v-model="form.from" hide-label type="date" />
                <InputField v-model="form.to" hide-label type="date" />
                <AppButton type="button" variant="secondary" @click="clearFilters">Limpiar</AppButton>
            </form>

            <GlobalTable
                :items="records.data"
                :columns="columns"
                :actions="actions"
                :pagination="records"
                :selectable="form.resource !== 'all'"
                :selected-items="selectedItems"
                mobile-card-header-field="label"
                no-data-message="No hay registros eliminados en este módulo."
                @update:selected-items="selectedItems = $event"
                @action="handleAction"
                @page-change="(url) => router.get(url, {}, { preserveState: true })"
            />
        </PageLayout>
    </AdminLayout>
</template>
