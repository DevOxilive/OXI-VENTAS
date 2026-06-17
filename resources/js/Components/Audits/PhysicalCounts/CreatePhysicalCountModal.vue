<script setup>
import { watch } from 'vue'
import { useForm } from '@inertiajs/vue3'


const props = defineProps({
    show: {
        type: Boolean,
        default: false
    },
    branch: {
        type: Object,
        default: null
    }
})

const emit = defineEmits(['close'])

const form = useForm({
    name: '',
    branch_id: ''
})

watch(
    () => props.branch,
    (branch) => {
        form.branch_id = branch?.id ?? ''
    },
    { immediate: true }
)

const submit = () => {
    if (!props.branch) return

    form.branch_id = props.branch.id

    form.post(route('audits.physical-counts.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            emit('close')
        }
    })

}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
        <div class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">
                        Nuevo conteo físico
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Crea una nueva sesión para la sucursal {{ branch?.name }}.
                    </p>
                </div>

                <button
                    type="button"
                    class="text-gray-400 hover:text-gray-600"
                    @click="emit('close')"
                >
                    ✕
                </button>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Nombre del conteo
                    </label>

                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-lg border-gray-300 text-sm"
                        placeholder="Ej. Conteo ##/##/####"

                    >

                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                        {{ form.errors.name }}
                    </p>

                    <p v-if="form.errors.branch_id" class="mt-1 text-sm text-red-600">
                        {{ form.errors.branch_id }}
                    </p>
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button
                        type="button"
                        class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        @click="emit('close')"
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-50"
                   :disabled="form.processing || !props.branch"
                    >
                        Crear conteo
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>