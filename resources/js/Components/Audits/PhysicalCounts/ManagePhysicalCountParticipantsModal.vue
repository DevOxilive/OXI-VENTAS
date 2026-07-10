<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import { getModalRequestOptions } from '@/Components/Modales/useModalConfig'

const props = defineProps({
    show: {
        type: Boolean,
        default: false,
    },
    physicalCount: {
        type: Object,
        default: null,
    },
    users: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close', 'updated'])

const search = ref('')

const form = useForm({
    name: '',
    participant_ids: [],
})

const totalErrors = computed(() => Object.keys(form.errors || {}).length)

const filteredUsers = computed(() => {
    const term = search.value.trim().toLowerCase()

    if (!term) return props.users

    return props.users.filter((user) => {
        return [user.name, user.email, user.role]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(term))
    })
})

watch(
    () => props.physicalCount,
    (physicalCount) => {
        form.clearErrors()
        form.name = physicalCount?.name ?? ''
        form.participant_ids = (physicalCount?.participants || []).map((participant) => participant.id)
        search.value = ''
    },
    { immediate: true },
)

function closeModal() {
    if (form.processing) return

    emit('close')
}

function submit() {
    if (!props.physicalCount) return

    form.put(route('audits.physical-counts.update', props.physicalCount.id), getModalRequestOptions({
        mode: 'update',
        entityName: 'Participantes',
        close: () => emit('close'),
        successTitle: 'Participantes actualizados correctamente',
        errorTitle: 'Error al actualizar participantes',
        errorMessage: 'No fue posible actualizar los participantes de la auditoria.',
        onSuccess: () => {
            emit('updated')
        },
    }))
}
</script>

<template>
    <GlobalModal
        v-if="show"
        title="Participantes de auditoria"
        :subtitle="physicalCount?.folio ?? physicalCount?.name ?? ''"
        mode="update"
        size="lg"
        height="auto"
        :total-errors="totalErrors"
        :processing="form.processing"
        save-button-text="Guardar participantes"
        close-button-text="Cancelar"
        @save="submit"
        @close="closeModal"
    >
        <form class="space-y-4" @submit.prevent="submit">
            <div>
                <div class="mb-2 flex items-center justify-between gap-3">
                    <label class="block text-sm font-medium text-gray-700">
                        Usuarios asignados
                    </label>

                    <span class="text-xs text-gray-500">
                        {{ form.participant_ids.length }} seleccionados
                    </span>
                </div>

                <input
                    v-model="search"
                    type="text"
                    placeholder="Buscar usuario"
                    class="mb-3 h-9 w-full rounded-lg border-gray-300 text-sm"
                    autocomplete="off"
                >

                <div class="max-h-72 overflow-y-auto rounded-lg border border-gray-300 bg-white">
                    <label
                        v-for="user in filteredUsers"
                        :key="user.id"
                        class="flex cursor-pointer items-center gap-3 border-b border-gray-100 px-3 py-2 text-sm last:border-b-0 hover:bg-gray-50"
                    >
                        <input
                            v-model="form.participant_ids"
                            type="checkbox"
                            :value="user.id"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        >

                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium text-gray-700">
                                {{ user.name }}
                            </span>

                            <span class="block truncate text-xs text-gray-500">
                                {{ user.role }} - {{ user.email }}
                            </span>
                        </span>
                    </label>

                    <div
                        v-if="filteredUsers.length === 0"
                        class="px-3 py-4 text-sm text-gray-500"
                    >
                        No hay usuarios para mostrar.
                    </div>
                </div>

                <p
                    v-if="form.errors.participant_ids"
                    class="mt-2 text-sm text-red-600"
                >
                    {{ form.errors.participant_ids }}
                </p>
            </div>
        </form>
    </GlobalModal>
</template>
