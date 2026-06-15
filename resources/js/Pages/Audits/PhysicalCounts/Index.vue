<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { router } from '@inertiajs/vue3'
import PhysicalCountHeader from '@/Components/Audits/PhysicalCounts/PhysicalCountHeader.vue'
import CreatePhysicalCountModal from '@/Components/Audits/PhysicalCounts/CreatePhysicalCountModal.vue'
import PhysicalCountList from '@/Components/Audits/PhysicalCounts/PhysicalCountList.vue'
import { usePermissions } from '@/Composables/usePermissions'
import AdminLayout from '@/Layouts/AdminLayout.vue'

defineProps({
    physicalCounts: {
        type: Array,
        default: () => []
    },
    branches: {
        type: Array,
        default: () => []
    }
})

const { can } = usePermissions()

const showCreateModal = ref(false)

function reloadPhysicalCounts() {
    router.reload({
        only: ['physicalCounts'],
        preserveScroll: true,
        preserveState: true,
    })
}

onMounted(() => {
    if (!window.Echo) return
window.Echo.channel('audits')
    .listen('.PhysicalCountChanged', (event) => {
        console.log('AUDITORIA RECIBIDA', event)

        reloadPhysicalCounts()
    })
})

onBeforeUnmount(() => {
    if (!window.Echo) return

    window.Echo.leave('audits')
})
</script>

<template>
    <AdminLayout>
        <div class="space-y-6">
            <div class="mb-6 flex items-start justify-between gap-4">
                <PhysicalCountHeader
                    title="Conteo físico"
                    subtitle="Administra las sesiones de conteo físico"
                />

              <button
    v-if="can('audits.physical-counts.create')"
    type="button"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                    @click="showCreateModal = true"
                >
                    Nuevo conteo físico
                </button>
            </div>

            <PhysicalCountList :physical-counts="physicalCounts" />

         <CreatePhysicalCountModal
    v-if="can('audits.physical-counts.create')"
    :show="showCreateModal"
    :branches="branches"
    @close="showCreateModal = false"
/>
        </div>
    </AdminLayout>
</template>