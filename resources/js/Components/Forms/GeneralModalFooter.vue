<script setup>
defineProps({
    processing: {
        type: Boolean,
        default: false
    },

    saveButtonText: {
        type: String,
        default: 'Guardar'
    },

    closeButtonText: {
        type: String,
        default: 'Cancelar'
    },

    mode: {
        type: String,
        default: 'create'
    }
})

defineEmits(['save', 'close'])
</script>

<template>
    <div class="sticky bottom-0 bg-white border-t p-4 flex flex-col sm:flex-row gap-3 justify-end">

        <button v-if="mode !== 'view'" type="button" @click="$emit('save')" :disabled="processing"
            class="bg-[#1f1d2b] text-white px-8 py-3 rounded-full w-full sm:w-auto disabled:opacity-50">
            {{ processing ? 'Guardando...' : saveButtonText }}
        </button>

        <button type="button" @click="$emit('close')" class="bg-gray-200 px-8 py-3 rounded-full w-full sm:w-auto">
            {{ mode === 'view' ? 'Cerrar' : closeButtonText }}
        </button>

    </div>
</template>