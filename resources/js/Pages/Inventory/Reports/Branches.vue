<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import GlobalToolbar from '@/Components/Toolbars/GlobalToolbar.vue'
import GlobalCard from '@/Components/Cards/GlobalCard.vue'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    branches: {
        type: Array,
        default: () => [],
    },
    selectedReport: {
        type: Object,
        default: null,
    },
})

const pageTitle = computed(() => props.selectedReport?.label ?? 'Reportes')

const toolbarConfig = computed(() => ({
    title: pageTitle.value,
    subtitle: 'Selecciona la sucursal que deseas consultar.',
    showSearch: false,
    showRecordsPerPage: false,
    showCounter: false,
    filters: [],
    actions: [],
    tabs: [],
}))

function openBranch(branch) {
    if (!props.selectedReport?.routeName) return

    router.get(route(props.selectedReport.routeName, {
        branch: branch.id,
    }))
}
</script>

<template>
    <Head :title="pageTitle" />

    <PageLayout>
        <template #toolbar>
            <GlobalToolbar v-bind="toolbarConfig" />
        </template>

        <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <GlobalCard
                v-for="branch in branches"
                :key="branch.id"
                :title="branch.name"
                description="Consulta los reportes disponibles para esta sucursal."
                icon="store"
                @click="openBranch(branch)"
            />
        </section>
    </PageLayout>
</template>
