<script setup>
defineProps({
    errores: Array
})
</script>

<template>
    <div v-if="errores.length" class="mb-5 rounded-2xl border border-primary bg-secondary p-4">
        <h3 class="mb-2 font-bold text-primary">Se detectaron errores:</h3>
        <ul class="ml-5 list-disc text-sm text-primary">
            <li v-for="(error, index) in errores" :key="index">{{ error }}</li>
        </ul>
    </div>
</template>
