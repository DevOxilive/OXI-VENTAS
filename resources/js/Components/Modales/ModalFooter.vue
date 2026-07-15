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
    <footer class="sticky bottom-0 flex flex-col justify-end gap-3 border-t border-secondary bg-background p-4 sm:flex-row">
        <button
            v-if="mode !== 'view' && showSave"
            type="button"
            class="w-full rounded-full border border-primary bg-primary px-8 py-3 text-white transition hover:brightness-110 disabled:opacity-50 sm:w-auto"
            :disabled="processing"
            @click="$emit('save')"
        >
            {{ processing ? 'Guardando...' : saveButtonText }}
        </button>

        <button
            type="button"
            class="w-full rounded-full border border-secondary bg-secondary px-8 py-3 text-text transition hover:brightness-95 sm:w-auto"
            @click="$emit('close')"
        >
            {{ mode === 'view' ? 'Cerrar' : closeButtonText }}
        </button>
    </footer>
</template>
