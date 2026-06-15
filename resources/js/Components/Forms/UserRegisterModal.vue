<script setup>
import { computed, onMounted, onBeforeUnmount } from 'vue'

import GeneralModalHeader from '@/Components/Forms/GeneralModalHeader.vue'
import GeneralModalFooter from '@/Components/Forms/GeneralModalFooter.vue'
import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'

const props = defineProps({
    form: Object,
    errors: Object,
    roles: Array,
    branches: Array,
    groupedPermissions: Object,
    isEditing: Boolean,
    canSave: Boolean,
    isSalesRole: Boolean,

    moduleLabel: {
  type: Function,
  required: true,
},
permissionLabel: {
  type: Function,
  required: true,
},
    
})

const emit = defineEmits([
    'close',
    'save',
    'toggle-permission',
    'change-role',
])

const mode = computed(() => props.isEditing ? 'edit' : 'create')

const totalErrors = computed(() => {
    return Object.keys(props.errors || {}).length
})

const saveButtonText = computed(() => {
    if (props.form.processing) return 'Procesando...'

    return props.isEditing
        ? 'Actualizar usuario'
        : 'Guardar usuario'
})

function closeModal() {
    emit('close')
}

function handleEsc(event) {
    if (event.key === 'Escape') closeModal()
}

onMounted(() => {
    window.addEventListener('keydown', handleEsc)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleEsc)
})
</script>

<template>
    <div class="fixed inset-0 z-50 bg-black/60 flex items-end md:items-center justify-center">
        <div class="absolute inset-0" @click="closeModal"></div>

        <div
            class="relative bg-white w-full h-[100dvh] md:h-[94vh] md:w-[96%] md:max-w-5xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden">
            <GeneralModalHeader :title="isEditing ? 'Actualizar usuario' : 'Registrar usuario'"
                subtitle="Configuración de acceso y permisos" :total-errors="totalErrors" :mode="mode"
                @close="closeModal" />
<div class="flex-1 overflow-hidden p-4 sm:p-5 md:p-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 h-full overflow-hidden">
                <section class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
                    <h3 class="font-bold text-base border-b pb-3 mb-4">
                        Datos de usuario
                    </h3>

                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <input v-model="form.name" maxlength="50" minlength="1" placeholder="Nombre completo"
                                class="border rounded-xl px-4 py-3 w-full">

                            <p v-if="errors.name" class="text-red-500 text-xs mt-1">
                                {{ errors.name }}
                            </p>
                        </div>

                        <div>
                            <input v-model="form.email" placeholder="Correo electrónico"
                                class="border rounded-xl px-4 py-3 bg-gray-100 w-full">

                            <p v-if="errors.email" class="text-red-500 text-xs mt-1">
                                {{ errors.email }}
                            </p>
                        </div>

                        <div>
                            <select v-model="form.role_id" class="border rounded-xl px-4 py-3 w-full bg-white"
                                @change="$emit('change-role')">
                                <option value="">
                                    Seleccionar rol
                                </option>

                                <option v-for="role in roles" :key="role.id" :value="role.id">
                                    {{ role.name }}
                                </option>
                            </select>

                            <p v-if="errors.role_id" class="text-red-500 text-xs mt-1">
                                {{ errors.role_id }}
                            </p>
                        </div>

                        <div>
                            <input v-model="form.password" type="password" maxlength="15" minlength="7"
                                placeholder="Contraseña" class="border rounded-xl px-4 py-3 w-full">

                            <p v-if="errors.password" class="text-red-500 text-xs mt-1">
                                {{ errors.password }}
                            </p>
                        </div>

                        <div>
                            <input v-model="form.password_confirmation" type="password" maxlength="15" minlength="7"
                                placeholder="Confirmar contraseña" class="border rounded-xl px-4 py-3 w-full">

                            <p v-if="errors.password_confirmation" class="text-red-500 text-xs mt-1">
                                {{ errors.password_confirmation }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm h-full overflow-y-auto pr-3">
                    <h3 class="font-bold text-base border-b pb-3 mb-4">
                        Accesos
                    </h3>

                    <div v-if="isSalesRole" class="mb-6">
                        <p class="text-sm font-semibold text-slate-700 mb-3">
                            Sucursales permitidas para este vendedor
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label v-for="branch in branches" :key="branch.id"
                                class="border rounded-xl px-3 py-3 cursor-pointer flex items-center gap-2 hover:bg-slate-50">
                                <input v-model="form.branch_ids" type="checkbox" :value="branch.id">

                                <span>{{ branch.name }}</span>
                            </label>
                        </div>

                        <p v-if="errors.branch_ids" class="text-red-500 text-xs mt-1">
                            {{ errors.branch_ids }}
                        </p>

                        <p v-if="form.errors.branch_ids" class="text-red-500 text-xs mt-1">
                            {{ form.errors.branch_ids }}
                        </p>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <p class="text-sm font-semibold text-slate-700">
                                Permisos
                            </p>

                            <span class="text-xs text-gray-500">
                                {{ form.permissions.length }} seleccionados
                            </span>
                        </div>

                        <div class="grid grid-cols-1 gap-4">
                            <div v-for="(group, module) in groupedPermissions" :key="module"
                                class="border rounded-2xl p-4 bg-gray-50">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="font-semibold capitalize text-slate-700">
                                     {{ moduleLabel(module) }}
                                    </h4>

                                    <span class="text-xs text-gray-500">
                                        {{group.filter(permission => form.permissions.includes(permission.id)).length
                                        }}
                                        /
                                        {{ group.length }}
                                    </span>
                                </div>

                                <div class="space-y-2">
                                    <div v-for="permission in group" :key="permission.id"
                                        class="flex items-center justify-between bg-white px-3 py-2 rounded-xl border">
                                        <span class="text-sm">
                                        {{ permissionLabel(permission.name) }}
                                        </span>

                                        <div @click="$emit('toggle-permission', permission.id)"
                                            class="w-10 h-5 flex items-center rounded-full p-1 cursor-pointer transition"
                                            :class="form.permissions.includes(permission.id)
                                                ? 'bg-green-500'
                                                : 'bg-gray-300'">
                                            <div class="w-4 h-4 bg-white rounded-full shadow transform transition"
                                                :class="form.permissions.includes(permission.id)
                                                    ? 'translate-x-5'
                                                    : 'translate-x-0'" />
                                        </div>
                                    </div>

                                    <p v-if="!group.length" class="text-xs text-gray-400 text-center py-2">
                                        Sin permisos registrados.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
</div>

         <GeneralModalFooter
    v-if="canSave"
    :processing="form.processing"
    :save-button-text="saveButtonText"
    :mode="mode"
    @save="$emit('save')"
    @close="closeModal"
/>
<div
    v-else
    class="flex justify-end gap-3 px-6 py-4 border-t bg-white"
>
    <button
        type="button"
        @click="closeModal"
        class="px-5 py-3 rounded-2xl bg-slate-200 text-slate-700 font-semibold"
    >
        Cerrar
    </button>
</div>
        </div>
    </div>
</template>