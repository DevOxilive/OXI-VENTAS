<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    branch: {
        type: Object,
        default: null,
    },
    users: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close'])

const search = ref('')

const form = useForm({
    name: '',
    branch_id: '',
    participant_ids: [],
})

watch(
    () => props.branch,
    (branch) => {
        form.branch_id = branch?.id ?? ''
    },
    { immediate: true }
)

watch(
    () => props.show,
    (show) => {
        if (!show) {
            search.value = ''
        }
    }
)

const filteredUsers = computed(() => {
    const term = search.value.trim().toLowerCase()

    if (!term) return props.users

    return props.users.filter((user) => {
        return [
            user.name,
            user.email,
            user.role,
        ]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(term))
    })
})

function toggleParticipant(userId) {
    const ids = new Set(form.participant_ids)

    if (ids.has(userId)) {
        ids.delete(userId)
    } else {
        ids.add(userId)
    }

    form.participant_ids = Array.from(ids)
}

function submit() {
    if (!props.branch) return

    form.branch_id = props.branch.id

    form.post(route('audits.physical-counts.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset()
            search.value = ''
            emit('close')
        },
    })
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
    >
        <div class="w-full max-w-3xl rounded-xl bg-white p-6 shadow-xl">
            <div class="mb-5 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold text-gray-900">
                        Nueva auditoria
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Crea una sesion de captura para la sucursal {{ branch?.name }} y asigna participantes.
                    </p>
                </div>

                <button
                    type="button"
                    class="text-gray-400 hover:text-gray-600"
                    @click="emit('close')"
                >
                    X
                </button>
            </div>

            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">
                        Nombre de la auditoria
                    </label>

                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-lg border-gray-300 text-sm"
                        placeholder="Ej. Conteo general de junio"
                    >

                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">
                                Participantes
                            </p>
                            <p class="text-xs text-slate-500">
                                Solo los usuarios seleccionados podran capturar en esta auditoria.
                            </p>
                        </div>

                        <input
                            v-model="search"
                            type="search"
                            class="w-full rounded-lg border-gray-300 text-sm md:max-w-xs"
                            placeholder="Buscar usuario"
                        >
                    </div>

                    <div class="mt-4 max-h-72 space-y-2 overflow-y-auto pr-1">
                        <label
                            v-for="user in filteredUsers"
                            :key="user.id"
                            class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white px-3 py-3"
                        >
                            <input
                                type="checkbox"
                                :checked="form.participant_ids.includes(user.id)"
                                class="mt-1 rounded border-slate-300"
                                @change="toggleParticipant(user.id)"
                            >

                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ user.name }}
                                </p>

                                <p class="text-xs text-slate-500">
                                    {{ user.email }} · {{ user.role }}
                                </p>
                            </div>
                        </label>
                    </div>

                    <p v-if="form.errors.participant_ids" class="mt-2 text-sm text-red-600">
                        {{ form.errors.participant_ids }}
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
                        Crear auditoria
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
