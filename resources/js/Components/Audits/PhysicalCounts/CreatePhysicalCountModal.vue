<script setup>
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'

import GlobalModal from '@/Components/Modales/GlobalModal.vue'
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
})

const emit = defineEmits(['close'])

const form = useForm({
    name: '',
    branch_id: '',
})

const totalErrors = computed(() => Object.keys(form.errors || {}).length)

const modalConfig = computed(() => getCreatePhysicalCountModalConfig({
    totalErrors: totalErrors.value,
    processing: form.processing,
}))

watch(
    () => props.branch,
    (branch) => {
        form.branch_id = branch?.id ?? ''
    },
    { immediate: true },
)

function closeModal() {
    if (form.processing) return

    emit('close')
}

function submit() {
    if (!props.branch) return

    form.branch_id = props.branch.id

    form.post(route('audits.physical-counts.store'), getModalRequestOptions({
        mode: 'create',
        entityName: modalConfig.value.alerts.entityName,
        close: () => emit('close'),
        successTitle: modalConfig.value.alerts.create.successTitle,
        errorTitle: modalConfig.value.alerts.create.errorTitle,
        errorMessage: modalConfig.value.alerts.create.errorMessage,
        onSuccess: () => {
            form.reset()
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
        <form class="space-y-4" @submit.prevent="submit">
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

            <p class="text-sm text-gray-500">
                Sucursal: {{ branch?.name ?? 'Sin sucursal seleccionada' }}
            </p>
        </form>
    </GlobalModal>
</template>
