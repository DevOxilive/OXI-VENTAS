<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue'
import SelectionGridSection from '@/Components/Forms/SelectionGridSection.vue'
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

    return props.users.filter((user) =>
        [user.name, user.email, user.role]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(term)),
    )
})

watch(
    () => props.physicalCount,
    (physicalCount) => {
        form.clearErrors()
        form.name = physicalCount?.name ?? ''
        form.participant_ids = (physicalCount?.participants || [])
            .map((participant) => Number(participant.id))
        search.value = ''
    },
    { immediate: true },
)

function closeModal() {
    if (form.processing) return

    emit('close')
}

function toggleParticipant(userId) {
    const normalizedId = Number(userId)

    form.participant_ids = form.participant_ids.includes(normalizedId)
        ? form.participant_ids.filter((id) => id !== normalizedId)
        : [...form.participant_ids, normalizedId]
}

function submit() {
    if (!props.physicalCount) return

    form.put(route('audits.physical-counts.update', props.physicalCount.id), getModalRequestOptions({
        mode: 'update',
        entityName: 'Participantes',
        close: () => emit('close'),
        successTitle: 'Participantes actualizados correctamente',
        errorTitle: 'Error al actualizar participantes',
        errorMessage: 'No fue posible actualizar los participantes de la auditoría.',
        onSuccess: () => {
            emit('updated')
        },
    }))
}
</script>

<template>
    <GlobalModal
        v-if="show"
        title="Participantes de auditoría"
        :subtitle="physicalCount?.folio ?? physicalCount?.name ?? ''"
        mode="update"
        size="md"
        height="compact"
        :columns="1"
        :total-errors="totalErrors"
        :processing="form.processing"
        save-button-text="Guardar participantes"
        close-button-text="Cancelar"
        @save="submit"
        @close="closeModal"
    >
        <form class="space-y-4" @submit.prevent="submit">
            <SelectionGridSection
                title="Usuarios asignados"
                description="Define quiénes participarán en esta auditoría."
                grid-class="grid grid-cols-1 gap-2 sm:grid-cols-2"
            >
                <template #aside>
                    <span class="text-xs font-semibold text-text opacity-60">
                        {{ form.participant_ids.length }} seleccionados
                    </span>
                </template>

                <div class="sm:col-span-2">
                    <InputField
                        v-model="search"
                        hide-label
                        field="participant_search"
                        validation-field="toolbar_search"
                        placeholder="Buscar por nombre, correo o rol"
                    />
                </div>

                <div class="max-h-72 space-y-2 overflow-y-auto pr-1 sm:col-span-2">
                    <SelectionCheckboxCard
                        v-for="user in filteredUsers"
                        :key="user.id"
                        class="w-full"
                        compact
                        variant="soft"
                        :checked="form.participant_ids.includes(Number(user.id))"
                        :title="user.name"
                        :description="[user.role, user.email].filter(Boolean).join(' · ')"
                        @toggle="toggleParticipant(user.id)"
                    />

                    <p
                        v-if="filteredUsers.length === 0"
                        class="rounded-lg border border-secondary bg-secondary px-3 py-4 text-sm text-text opacity-60"
                    >
                        No hay usuarios para mostrar.
                    </p>
                </div>

                <p
                    v-if="form.errors.participant_ids"
                    class="text-sm text-primary sm:col-span-2"
                >
                    {{ form.errors.participant_ids }}
                </p>
            </SelectionGridSection>
        </form>
    </GlobalModal>
</template>
