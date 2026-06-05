<script setup>
defineProps({
    title: {
        type: String,
        default: 'Physical Counts'
    },
    subtitle: {
        type: String,
        default: 'Manage physical count audits'
    }
})
</script>

<template>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">
            {{ title }}
        </h1>

        <p class="mt-1 text-sm text-gray-500">
            {{ subtitle }}
        </p>
    </div>
</template>