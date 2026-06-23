<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    mode: {
        type: String,
        default: 'create',
    },
    totalErrors: {
        type: Number,
        default: 0,
    },
})

defineEmits(['close'])
</script>

<template>
    <header class="sticky top-0 z-10 flex items-center justify-between gap-4 border-b bg-white px-5 py-4 md:px-8 md:py-5">
        <div class="min-w-0">
            <h2 class="text-lg font-bold text-slate-800 md:text-2xl">
                {{ title }}
            </h2>

            <p v-if="subtitle" class="mt-1 text-xs text-slate-500 md:text-sm">
                {{ subtitle }}
            </p>

            <p v-if="mode !== 'view' && totalErrors > 0" class="mt-1 text-xs font-medium text-red-500 md:text-sm">
                {{ totalErrors }}
                campo{{ totalErrors > 1 ? 's' : '' }}
                pendiente{{ totalErrors > 1 ? 's' : '' }}
                por validar
            </p>
        </div>

        <button
            type="button"
            class="text-2xl text-slate-500 transition hover:text-black"
            aria-label="Cerrar modal"
            @click="$emit('close')"
        >
            ×
        </button>
    </header>
</template>
