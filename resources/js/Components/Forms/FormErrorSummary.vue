<script setup>
defineProps({
    errores: Array
})
</script>

<template>
    <div v-if="errores.length" class="mb-5 bg-red-50 border border-red-300 rounded-2xl p-4">
        <h3 class="font-bold text-red-700 mb-2">Se detectaron errores:</h3>
        <ul class="list-disc ml-5 text-sm text-red-600">
            <li v-for="(error, index) in errores" :key="index">{{ error }}</li>
        </ul>
    </div>
</template>