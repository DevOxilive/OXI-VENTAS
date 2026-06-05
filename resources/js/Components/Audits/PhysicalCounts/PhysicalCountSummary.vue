<script setup>
defineProps({
    summary: {
        type: Object,
        default: () => ({
            total_entries: 0,
            total_counted: 0,
            total_damaged: 0,
            total_expired: 0,
            participants: 0
        })
    }
})
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900">
            Resumen
        </h2>

        <div class="mt-4 space-y-3">
            <div class="flex justify-between border-b pb-2">
                <span class="text-sm text-gray-500">Registros</span>
                <strong>{{ summary.total_entries }}</strong>
            </div>

            <div class="flex justify-between border-b pb-2">
                <span class="text-sm text-gray-500">Cantidad contada</span>
                <strong>{{ summary.total_counted }}</strong>
            </div>

            <div class="flex justify-between border-b pb-2">
                <span class="text-sm text-gray-500">Dañados</span>
                <strong>{{ summary.total_damaged }}</strong>
            </div>

            <div class="flex justify-between border-b pb-2">
                <span class="text-sm text-gray-500">Caducados</span>
                <strong>{{ summary.total_expired }}</strong>
            </div>

            <div class="flex justify-between">
                <span class="text-sm text-gray-500">Participantes</span>
                <strong>{{ summary.participants }}</strong>
            </div>
            <div class="flex justify-between border-t pt-3">
    <span class="text-sm text-gray-500">Productos auditados</span>
    <strong>{{ summary.audited_products ?? 0 }}</strong>
</div>

<div class="flex justify-between">
    <span class="text-sm text-red-600">Faltantes</span>
    <strong class="text-red-600">{{ summary.missing_products ?? 0 }}</strong>
</div>

<div class="flex justify-between">
    <span class="text-sm text-yellow-600">Sobrantes</span>
    <strong class="text-yellow-600">{{ summary.surplus_products ?? 0 }}</strong>
</div>

<div class="flex justify-between">
    <span class="text-sm text-green-600">Correctos</span>
    <strong class="text-green-600">{{ summary.matched_products ?? 0 }}</strong>
</div>
        </div>
    </div>
</template>