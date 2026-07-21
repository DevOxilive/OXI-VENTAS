<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { onBeforeUnmount, onMounted } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalCard from '@/Components/Cards/GlobalCard.vue'
import MetricCard from '@/Components/Cards/MetricCard.vue'
import { REALTIME_CHANNELS, REALTIME_EVENTS, subscribeRealtime } from '@/realtime'

defineProps({
    auditCount: Number,
    trashCount: Number,
})

const permissions = usePage().props.auth?.permissions ?? []
const can = (permission) => permissions.includes(permission)

const sections = [
    { group: 'Seguridad y trazabilidad', permission: 'system.audit.view', title: 'Auditoría del Sistema', description: 'Consulta actividades, resultados y el contexto de cada operación crítica.', icon: 'history', routeName: 'system-administration.audits.index' },
    { group: 'Recuperación de información', permission: 'system.trash.view', title: 'Papelera Global', description: 'Restaura información eliminada o administra eliminaciones definitivas.', icon: 'restore_from_trash', routeName: 'system-administration.trash.index' },
]

const visibleSections = sections.filter((section) => can(section.permission))

let unsubscribeTrashRealtime = null

onMounted(() => {
    unsubscribeTrashRealtime = subscribeRealtime(
        REALTIME_CHANNELS.systems,
        REALTIME_EVENTS.systemTrashChanged,
        () => router.reload({ only: ['trashCount'], preserveScroll: true }),
    )
})

onBeforeUnmount(() => unsubscribeTrashRealtime?.())
</script>

<template>
    <Head title="Centro de Administración" />

    <AdminLayout>
        <PageLayout>
            <template #toolbar>
                <GlobalToolbar
                    title="Centro de Administración del Sistema"
                    subtitle="Seguridad, trazabilidad, recuperación y control de accesos en un solo lugar."
                    :show-search="false"
                    :show-records-per-page="false"
                    :show-counter="false"
                />
            </template>

            <section class="grid gap-4 md:grid-cols-3">
                <MetricCard label="Eventos auditados" :value="auditCount" suffix="Historial disponible" tone="neutral" />
                <MetricCard label="Registros recuperables" :value="trashCount" suffix="En la Papelera Global" tone="danger" />
                <MetricCard label="Estado de seguridad" value="Activo" suffix="Controles exclusivos habilitados" tone="success" />
            </section>

            <section class="grid gap-4 md:grid-cols-2">
                <Link v-for="section in visibleSections" :key="section.title" :href="route(section.routeName)" class="block h-full">
                    <GlobalCard class="h-full" :title="section.title" :subtitle="section.group" :description="section.description" :icon="section.icon" />
                </Link>
            </section>
        </PageLayout>
    </AdminLayout>
</template>
