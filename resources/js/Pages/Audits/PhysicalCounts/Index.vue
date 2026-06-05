<script setup>
import { ref } from 'vue'
import PhysicalCountHeader from '@/Components/Audits/PhysicalCounts/PhysicalCountHeader.vue'
import CreatePhysicalCountModal from '@/Components/Audits/PhysicalCounts/CreatePhysicalCountModal.vue'
import PhysicalCountList from '@/Components/Audits/PhysicalCounts/PhysicalCountList.vue'
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

const showCreateModal = ref(false)
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
                    type="button"
                    class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                    @click="showCreateModal = true"
                >
                    Nuevo conteo físico
                </button>
            </div>

            <PhysicalCountList :physical-counts="physicalCounts" />

            <CreatePhysicalCountModal
                :show="showCreateModal"
                :branches="branches"
                @close="showCreateModal = false"
            />
        </div>
    </AdminLayout>
</template>