<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
import InputField from '@/Components/Forms/InputField.vue'
import SelectionCheckboxCard from '@/Components/Forms/SelectionCheckboxCard.vue'
import SelectionGridSection from '@/Components/Forms/SelectionGridSection.vue'
import { getModalRequestOptions } from '@/Components/Modales/useModalConfig'
import { getCreatePhysicalCountModalConfig } from '@/config/ModalConfigs/createPhysicalCountModalConfig'

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
    participant_ids: [],
})

const totalErrors = computed(() => Object.keys(form.errors || {}).length)
const modalConfig = computed(() => getCreatePhysicalCountModalConfig({
    totalErrors: totalErrors.value,
    processing: form.processing,
}))
const filteredUsers = computed(() => {
    const term = search.value.trim().toLowerCase()

    if (!term) return props.users

    return props.users.filter((user) =>
        [user.name, user.email, user.role]
            .filter(Boolean)
            .some((value) => String(value).toLowerCase().includes(term)),
    )
})

watch(() => props.show, (show) => {
    if (!show) return

    form.clearErrors()
    search.value = ''
})

function toggleParticipant(userId) {
    const normalizedId = Number(userId)

    form.participant_ids = form.participant_ids.includes(normalizedId)
        ? form.participant_ids.filter((id) => id !== normalizedId)
        : [...form.participant_ids, normalizedId]
}

function closeModal() {
    if (form.processing) return

    emit('close')
}

function submit() {
    if (!props.branch?.slug) return

    form.post(route('audits.physical-counts.store', {
        branch: props.branch.slug,
    }), getModalRequestOptions({
        mode: 'create',
        entityName: modalConfig.value.alerts.entityName,
        close: () => emit('close'),
        successTitle: modalConfig.value.alerts.create.successTitle,
        errorTitle: modalConfig.value.alerts.create.errorTitle,
        errorMessage: modalConfig.value.alerts.create.errorMessage,
        onSuccess: () => {
            form.reset()
            search.value = ''
        },
    }))
}
</script>

<template>
    <GlobalModal
        v-if="show"
        v-bind="modalConfig"
        @save="submit"
        @close="closeModal"
    >
        <form class="space-y-5" @submit.prevent="submit">
            <InputField
                v-model="form.name"
                label="Nombre del conteo"
                field="name"
                placeholder="Ej. Conteo 15/06/2026"
                :error="form.errors.name"
            />

            <SelectionGridSection
                title="Participantes de la auditoría"
                description="Selecciona uno o varios participantes para este conteo."
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

                <div class="max-h-56 space-y-2 overflow-y-auto pr-1 sm:col-span-2">
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
                        No hay usuarios disponibles para mostrar.
                    </p>
                </div>

                <p
                    v-if="form.errors.participant_ids"
                    class="text-sm text-primary sm:col-span-2"
                >
                    {{ form.errors.participant_ids }}
                </p>
            </SelectionGridSection>

            <div class="rounded-lg border border-secondary bg-secondary p-3">
                <p class="text-sm text-text opacity-75">
                    <span class="font-semibold">Sucursal:</span>
                    {{ branch?.name ?? 'Sin sucursal seleccionada' }}
                </p>

                <p
                    v-if="form.errors.branch"
                    class="mt-1 text-sm text-primary"
                >
                    {{ form.errors.branch }}
                </p>
            </div>
        </form>
    </GlobalModal>
</template>
