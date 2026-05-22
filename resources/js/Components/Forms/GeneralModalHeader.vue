<script setup>
defineProps({
    title: {
        type: String,
        required: true
    },

    subtitle: {
        type: String,
        default: ''
    },

    mode: {
        type: String,
        default: 'create'
    },

    totalErrors: {
        type: Number,
        default: 0
    }
})

defineEmits(['close'])
</script>

<template>
    <div class="sticky top-0 bg-white border-b px-5 py-4 md:px-8 md:py-5 flex justify-between items-center z-10">

        <div>
            <h2 class="text-lg md:text-2xl font-bold text-slate-800">
                {{ title }}
            </h2>

            <p v-if="subtitle" class="text-xs md:text-sm text-slate-500 mt-1">
                {{ subtitle }}
            </p>

            <p v-if="mode !== 'view' && totalErrors > 0" class="text-xs md:text-sm text-red-500 mt-1 font-medium">
                {{ totalErrors }}
                campo{{ totalErrors > 1 ? 's' : '' }}
                pendiente{{ totalErrors > 1 ? 's' : '' }}
                por validar
            </p>
        </div>

        <button type="button" @click="$emit('close')" class="text-2xl text-slate-500 hover:text-black transition">
            ✕
        </button>
    </div>
</template>