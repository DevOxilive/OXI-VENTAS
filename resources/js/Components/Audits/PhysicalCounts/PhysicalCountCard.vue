<script setup>
import { Link } from '@inertiajs/vue3'
import { usePermissions } from '@/Composables/usePermissions'

defineProps({
    physicalCount: {
        type: Object,
        required: true
    }
})
const { can } = usePermissions()
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="font-semibold text-gray-900">
                    {{ physicalCount.name }}
                </h3>

                <p class="mt-1 text-sm text-gray-500">
                    Sucursal: {{ physicalCount.branch?.name ?? 'Sin sucursal' }}
                </p>
<p class="mt-1 text-sm font-medium text-gray-700">
    Folio: {{ physicalCount.folio ?? 'Sin folio' }}
</p>
                <p class="mt-1 text-xs text-gray-400">
                    Estado: {{ physicalCount.status }}
                </p>
            </div>

         <Link
    v-if="can('audits.physical-counts.view')"
    :href="route('audits.physical-counts.show', physicalCount.id)"
    class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50"
>
    Abrir
</Link>
        </div>
    </div>
</template> 