<script setup>
defineProps({
    processing: {
        type: Boolean,
        default: false,
    },
    saveButtonText: {
        type: String,
        default: 'Guardar',
    },
    closeButtonText: {
        type: String,
        default: 'Cancelar',
    },
    mode: {
        type: String,
        default: 'create',
    },
    showSave: {
        type: Boolean,
        default: true,
    },
})

defineEmits(['save', 'close'])
</script>

<template>
    <footer class="sticky bottom-0 flex flex-col justify-end gap-3 border-t bg-white p-4 sm:flex-row">
        <button
            v-if="mode !== 'view' && showSave"
            type="button"
            class="w-full rounded-full bg-[#1f1d2b] px-8 py-3 text-white disabled:opacity-50 sm:w-auto"
            :disabled="processing"
            @click="$emit('save')"
        >
            {{ processing ? 'Guardando...' : saveButtonText }}
        </button>

        <button
            type="button"
            class="w-full rounded-full bg-gray-200 px-8 py-3 text-slate-800 transition hover:bg-gray-300 sm:w-auto"
            @click="$emit('close')"
        >
            {{ mode === 'view' ? 'Cerrar' : closeButtonText }}
        </button>
    </footer>
</template>
