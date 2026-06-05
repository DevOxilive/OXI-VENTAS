<script setup>
import { useForm } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'

const props = defineProps({
    physicalCountId: {
        type: Number,
        required: true
    }
})

const codeInput = ref(null)

const form = useForm({
    code: ''
})

const focusInput = () => {
    codeInput.value?.focus()
}

const scan = () => {
    form.post(route('audits.physical-counts.scan', props.physicalCountId), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('code')
            focusInput()
        }
    })
}

onMounted(() => {
    focusInput()
})
</script>

<template>
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-900">
            Escanear producto
        </h2>

        <form class="mt-4 flex gap-3" @submit.prevent="scan">
            <input
                ref="codeInput"
                v-model="form.code"
                type="text"
                placeholder="Escanea o escribe el código"
                class="w-full rounded-lg border-gray-300 text-sm"
            >

            <button
                type="submit"
                class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                :disabled="form.processing"
            >
                Buscar
            </button>
        </form>

        <p v-if="form.errors.code" class="mt-2 text-sm text-red-600">
            {{ form.errors.code }}
        </p>
    </div>
</template>