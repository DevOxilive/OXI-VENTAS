<script setup>
defineProps({
    empleado: Object,
    textoBotonGuardar: String,
    modo: String
})

defineEmits(['guardar', 'close'])
</script>

<template>
    <div class="sticky bottom-0 bg-white border-t p-4 flex flex-col sm:flex-row gap-3 justify-end">
        <button v-if="modo !== 'view'" @click="$emit('guardar')" :disabled="empleado.processing"
            class="bg-[#1f1d2b] text-white px-8 py-3 rounded-full w-full sm:w-auto disabled:opacity-50">
            {{ textoBotonGuardar }}
        </button>

        <button @click="$emit('close')" class="bg-gray-200 px-8 py-3 rounded-full w-full sm:w-auto">
            {{ modo === 'view' ? 'Cerrar' : 'Cancelar' }}
        </button>
    </div>
</template>