<script setup>
import { computed, onMounted, onBeforeUnmount } from 'vue'

import GeneralModalHeader from '../Forms/GeneralModalHeader.vue'
import GeneralModalFooter from '../Forms/GeneralModalFooter.vue'
import GeneralModalContent from '@/Components/Forms/GeneralModalContent.vue'

const props = defineProps({
    form: Object,
    errores: Object,
    roles: Array,
    sucursales: Array,
    permisosAgrupados: Object,
    editando: Boolean,
    canGuardar: Boolean,
    esRolVentas: Boolean,
})

const emit = defineEmits([
    'close',
    'guardar',
    'toggle-permiso',
    'change-role',
])

const modo = computed(() => props.editando ? 'edit' : 'crear')

const totalErrores = computed(() => {
    return Object.keys(props.errores || {}).length
})

const textoBotonGuardar = computed(() => {
    if (props.form.processing) return 'Procesando...'

    return props.editando
        ? 'Actualizar usuario'
        : 'Guardar usuario'
})

function cerrar() {
    emit('close')
}

function handleEsc(e) {
    if (e.key === 'Escape') cerrar()
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
        <div class="absolute inset-0" @click="cerrar"></div>

        <div
            class="relative bg-white w-full h-[100dvh] md:h-[94vh] md:w-[96%] md:max-w-5xl rounded-t-[28px] md:rounded-3xl shadow-2xl flex flex-col overflow-hidden">
            <GeneralModalHeader :modo="modo" :totalErrores="totalErrores" @close="cerrar" />

            <GeneralModalContent :columns="2">
                <section class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
                    <h3 class="font-bold text-base border-b pb-3 mb-4">
                        Datos de usuario
                    </h3>

                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <input v-model="form.name" maxlength="50" minlength="1" placeholder="Nombre completo"
                                class="border rounded-xl px-4 py-3 w-full">

                            <p v-if="errores.name" class="text-red-500 text-xs mt-1">
                                {{ errores.name }}
                            </p>
                        </div>

                        <div>
                            <input v-model="form.email" placeholder="Correo electrónico"
                                class="border rounded-xl px-4 py-3 bg-gray-100 w-full">

                            <p v-if="errores.email" class="text-red-500 text-xs mt-1">
                                {{ errores.email }}
                            </p>
                        </div>

                        <div>
                            <select v-model="form.role_id" class="border rounded-xl px-4 py-3 w-full bg-white"
                                @change="$emit('change-role')">
                                <option value="">
                                    Seleccionar rol
                                </option>

                                <option v-for="rol in roles" :key="rol.id" :value="rol.id">
                                    {{ rol.name }}
                                </option>
                            </select>

                            <p v-if="errores.role_id" class="text-red-500 text-xs mt-1">
                                {{ errores.role_id }}
                            </p>
                        </div>

                        <div>
                            <input type="password" v-model="form.password" maxlength="15" minlength="7"
                                placeholder="Contraseña" class="border rounded-xl px-4 py-3 w-full">

                            <p v-if="errores.password" class="text-red-500 text-xs mt-1">
                                {{ errores.password }}
                            </p>
                        </div>

                        <div>
                            <input type="password" v-model="form.password_confirmation" maxlength="15" minlength="7"
                                placeholder="Confirmar contraseña" class="border rounded-xl px-4 py-3 w-full">

                            <p v-if="errores.password_confirmation" class="text-red-500 text-xs mt-1">
                                {{ errores.password_confirmation }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="bg-white border border-slate-200 rounded-3xl p-4 sm:p-5 md:p-6 shadow-sm">
                    <h3 class="font-bold text-base border-b pb-3 mb-4">
                        Accesos
                    </h3>

                    <div v-if="esRolVentas" class="mb-6">
                        <p class="text-sm font-semibold text-slate-700 mb-3">
                            Sucursales permitidas para este vendedor
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label v-for="sucursal in sucursales" :key="sucursal.id"
                                class="border rounded-xl px-3 py-3 cursor-pointer flex items-center gap-2 hover:bg-slate-50">
                                <input type="checkbox" :value="sucursal.id" v-model="form.sucursal_ids">

                                <span>{{ sucursal.nombre }}</span>
                            </label>
                        </div>

                        <p v-if="errores.sucursal_ids" class="text-red-500 text-xs mt-1">
                            {{ errores.sucursal_ids }}
                        </p>

                        <p v-if="form.errors.sucursal_ids" class="text-red-500 text-xs mt-1">
                            {{ form.errors.sucursal_ids }}
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
                            <div v-for="(grupo, modulo) in permisosAgrupados" :key="modulo"
                                class="border rounded-2xl p-4 bg-gray-50">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="font-semibold capitalize text-slate-700">
                                        {{ modulo }}
                                    </h4>

                                    <span class="text-xs text-gray-500">
                                        {{grupo.filter(p => form.permissions.includes(p.id)).length}}
                                        /
                                        {{ grupo.length }}
                                    </span>
                                </div>

                                <div class="space-y-2">
                                    <div v-for="perm in grupo" :key="perm.id"
                                        class="flex items-center justify-between bg-white px-3 py-2 rounded-xl border">
                                        <span class="text-sm capitalize">
                                            {{ perm.name }}
                                        </span>

                                        <div @click="$emit('toggle-permiso', perm.id)"
                                            class="w-10 h-5 flex items-center rounded-full p-1 cursor-pointer transition"
                                            :class="form.permissions.includes(perm.id) ? 'bg-green-500' : 'bg-gray-300'">
                                            <div class="w-4 h-4 bg-white rounded-full shadow transform transition"
                                                :class="form.permissions.includes(perm.id) ? 'translate-x-5' : 'translate-x-0'" />
                                        </div>
                                    </div>

                                    <p v-if="!grupo.length" class="text-xs text-gray-400 text-center py-2">
                                        Sin permisos registrados.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </GeneralModalContent>

            <GeneralModalFooter :empleado="form" :modo="canGuardar ? modo : 'view'"
                :textoBotonGuardar="textoBotonGuardar" @guardar="$emit('guardar')" @close="cerrar" />
        </div>
    </div>
</template>