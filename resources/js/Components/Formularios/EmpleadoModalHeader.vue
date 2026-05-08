<script setup>
defineProps({
    modo: String,
    totalErrores: Number
})

defineEmits(['close'])
</script>

<template>
    <div class="sticky top-0 bg-white border-b px-5 py-4 md:px-8 md:py-5 flex justify-between items-center z-10">

        <div>
            <h2 class="text-lg md:text-2xl font-bold text-slate-800">
                {{ modo === 'crear' ? 'Registro corporativo de empleado' : 'Actualizar empleado' }}
            </h2>

            <p v-if="totalErrores > 0" class="text-xs md:text-sm text-red-500 mt-1 font-medium">
                {{ totalErrores }} campo{{ totalErrores > 1 ? 's' : '' }} pendiente{{ totalErrores > 1 ? 's' : '' }} por
                validar
            </p>
        </div>

        <button @click="$emit('close')" class="text-2xl text-slate-500 hover:text-black transition">
            ✕
        </button>
    </div>
</template>