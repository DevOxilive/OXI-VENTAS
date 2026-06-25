<script setup>
import { ref, reactive, computed, watch } from 'vue'
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
</script><template>
    <GlobalModal
        v-if="show"
        v-bind="modalConfig"
        @save="submit"
        @close="closeModal"
    >
        <form
            class="space-y-5"
            @submit.prevent="submit"
        >
            <!-- Nombre del conteo -->
            <div>
                <label class="mb-1 block text-sm font-medium text-gray-700">
                    Nombre del conteo
                </label>

                <input
                    v-model="form.name"
                    type="text"
                    class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                    placeholder="Ej. Conteo 15/06/2026"
                >

                <p
                    v-if="form.errors.name"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.name }}
                </p>
            </div>

            <!-- Participantes -->
            <div>
                <div class="mb-2 flex items-center justify-between gap-3">
                    <label class="block text-sm font-medium text-gray-700">
                        Participantes de la auditoría
                    </label>

                    <span class="text-xs text-gray-500">
                        {{ form.participant_ids.length }} seleccionados
                    </span>
                </div>

                <div
                    class="max-h-52 overflow-y-auto rounded-lg border border-gray-300 bg-white"
                >
                    <label
                        v-for="user in props.users"
                        :key="user.id"
                        class="flex cursor-pointer items-center gap-3 border-b border-gray-100 px-3 py-2 text-sm last:border-b-0 hover:bg-gray-50"
                    >
                        <input
                            v-model="form.participant_ids"
                            type="checkbox"
                            :value="user.id"
                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        >

                        <span class="truncate text-gray-700">
                            {{ user.name }}
                        </span>
                    </label>

                    <div
                        v-if="!props.users || props.users.length === 0"
                        class="px-3 py-4 text-sm text-gray-500"
                    >
                        No hay usuarios disponibles para seleccionar.
                    </div>
                </div>

                <p class="mt-1 text-xs text-gray-500">
                    Puedes seleccionar uno o varios participantes para esta auditoría.
                </p>

                <p
                    v-if="form.errors.participant_ids"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.participant_ids }}
                </p>
            </div>

            <!-- Sucursal -->
            <div class="rounded-lg bg-gray-50 p-3">
                <p class="text-sm text-gray-600">
                    <span class="font-medium text-gray-700">Sucursal:</span>
                    {{ branch?.name ?? 'Sin sucursal seleccionada' }}
                </p>

                <p
                    v-if="form.errors.branch_id"
                    class="mt-1 text-sm text-red-600"
                >
                    {{ form.errors.branch_id }}
                </p>
            </div>
        </form>
    </GlobalModal>
</template>