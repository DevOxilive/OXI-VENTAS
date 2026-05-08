<script setup>
import { ref, watch, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { usePage, useForm, router } from '@inertiajs/vue3'
import { usePermissions } from '@/Composables/usePermissions'

defineOptions({ layout: AdminLayout })

// 🔥 PAGE
const page = usePage()
// 🔥 GLOBAL PERMISSIONS
const { can } = usePermissions()


// 🔥 DATA BACKEND
const empleados = computed(() => page.props.empleados || [])
const usuarios = computed(() => page.props.usuarios || [])
const roles = computed(() => page.props.roles || [])

const permissions = computed(() => page.props.permissions || [])


// 🔥 UI

const vista = ref(can('usuarios.ver') ? 'usuarios' : 'empleados')
const busqueda = ref('')
const porPagina = ref(10)
const pagina = ref(1)

// 🔥 MODAL CREAR / EDITAR
const showModal = ref(false)
const editando = ref(false)
const userId = ref(null)
const moduloActivo = ref(null)

// 🔥 MODAL DETALLE USUARIO
const showDetalle = ref(false)
const usuarioSeleccionado = ref(null)
const showPermisosModal = ref(false)

function abrirDetalleUsuario(user) {
    if (vista.value !== 'usuarios') return

    usuarioSeleccionado.value = user
    showDetalle.value = true
}

function cerrarDetalleUsuario() {
    showDetalle.value = false
    usuarioSeleccionado.value = null
}

// 🔥 FORM
const form = useForm({
    empleado_id: '',
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role_id: '',
    permissions: []
})

// 🔥 ERRORES
const errores = ref({})

// 🔥 VALIDAR
function validar() {
    errores.value = {}

    if (!form.name) errores.value.name = 'Nombre requerido'

    if (!form.email) {
        errores.value.email = 'Correo requerido'
    } else if (!/^\S+@\S+\.\S+$/.test(form.email)) {
        errores.value.email = 'Correo inválido'
    }

    if (!editando.value || form.password) {
        if (!form.password) {
            errores.value.password = 'Contraseña requerida'
        } else if (form.password.length < 7) {
            errores.value.password = 'Mínimo 7 caracteres'
        } else if (form.password.length > 15) {
            errores.value.password = 'Máximo 15 caracteres'
        }

        if (form.password !== form.password_confirmation) {
            errores.value.password_confirmation = 'No coincide'
        }
    }

    return Object.keys(errores.value).length === 0
}

// 🔍 FILTRO
const listaFiltrada = computed(() => {
    let data = vista.value === 'usuarios'
        ? usuarios.value
        : empleados.value

    if (busqueda.value) {
        const b = busqueda.value.toLowerCase()

        data = data.filter(emp => {
            const nombre = vista.value === 'usuarios'
                ? (emp.name || '')
                : `${emp.nombre || ''} ${emp.apellido || ''}`

            const correo = vista.value === 'usuarios'
                ? (emp.email || '')
                : (emp.correo || '')

            return (
                nombre.toLowerCase().includes(b) ||
                correo.toLowerCase().includes(b)
            )
        })
    }

    return data
})

const listaActual = computed(() => {
    const limite = Number(porPagina.value)
    const inicio = (pagina.value - 1) * limite

    return listaFiltrada.value.slice(inicio, inicio + limite)
})

// ✅ PERMISOS AGRUPADOS
// ✅ PERMISOS AGRUPADOS
const permisosAgrupados = computed(() => {

    const grupos = {
        empleados: [],
        roles: [],
        usuarios: [],
        exportar: [],
        ventas: [],
        inventario: [],
    }

    permissions.value.forEach(p => {

        const modulo = (p.name || '')
            .split('.')[0]
            ?.toLowerCase() || 'otros'

        if (!grupos[modulo]) {
            grupos[modulo] = []
        }

        grupos[modulo].push(p)
    })

    return grupos
})

// 🔥 TOGGLES
function toggleModulo(modulo) {
    moduloActivo.value = moduloActivo.value === modulo ? null : modulo
}

function togglePermiso(id) {
    if (form.permissions.includes(id)) {
        form.permissions = form.permissions.filter(p => p !== id)
    } else {
        form.permissions = [...form.permissions, id]
    }
}
function activarModulo(modulo) {
    const ids = permisosAgrupados.value[modulo].map(p => p.id)

    form.permissions = [
        ...new Set([
            ...form.permissions,
            ...ids
        ])
    ]
}

function desactivarModulo(modulo) {
    const ids = permisosAgrupados.value[modulo].map(p => p.id)

    form.permissions = form.permissions.filter(id => !ids.includes(id))
}
// 🔥 ABRIR MODAL CREAR / EDITAR
function abrirModal(emp = null) {
    form.reset()
    form.clearErrors()
    errores.value = {}
    showModal.value = true

    if (emp && vista.value === 'usuarios') {
        editando.value = true
        userId.value = emp.id

        form.empleado_id = emp.empleado_id || ''
        form.name = emp.name || ''
        form.email = emp.email || ''
        form.role_id = emp.role_id || ''
        form.permissions = emp.permissions?.length
            ? emp.permissions.map(p => p.id)
            : permisosPorRol(emp.role_id)
    } else {
        editando.value = false
        userId.value = null

        if (emp) {
            form.empleado_id = emp.id
            form.name = `${emp.nombre || ''} ${emp.apellido || ''}`.trim()
        }
    }
}
watch(() => form.name, (nuevoNombre) => {
    if (!nuevoNombre) {
        form.email = ''
        return
    }

    const correo = nuevoNombre
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, '.')
        .replace(/[^a-z0-9.]/g, '')

    form.email = `${correo}@oxilive.com.mx`
})
// 🔥 CERRAR MODAL CREAR / EDITAR
function cerrarModal() {
    showModal.value = false
    editando.value = false
    userId.value = null
    form.reset()
    errores.value = {}
}

// 🔥 GUARDAR
function guardarEmpleado() {
    if (!validar()) return

    form.permissions = form.permissions.filter(p => p)

    if (editando.value) {
        form.put(route('sistemas.empleados.update', userId.value), {
            preserveScroll: true,
            onSuccess: () => {
                cerrarModal()
                router.reload({ only: ['empleados', 'usuarios'] })
            }
        })
    } else {
        form.post(route('sistemas.empleados.store'), {
            preserveScroll: true,
            onSuccess: () => {
                cerrarModal()
                router.visit(route('sistemas.empleados'), {
                    preserveScroll: true,
                    preserveState: false,
                    replace: true
                })
            }
        })
    }
}

// 🔥 ELIMINAR
function eliminarUsuario(id) {
    if (!confirm('¿Eliminar usuario?')) return

    form.delete(route('sistemas.empleados.destroy', id), {
        preserveScroll: true,
        onSuccess: () => {
            router.visit(route('sistemas.empleados'), {
                preserveScroll: true,
                preserveState: false,
                replace: true
            })
        }
    })
}// 🔥 PERMISOS AUTOMÁTICOS POR ROL
function permisosPorRol(roleId) {
    const rol = roles.value.find(r => String(r.id) === String(roleId))

    return rol?.permissions?.map(p => p.id) || []
}



</script>

<template>
<div class="bg-[#f6f3f7] min-h-screen rounded-3xl p-6">

    <h1 class="text-xl font-semibold text-slate-700 mb-6">
        Dashboard Registro de Usuario
    </h1>

    <!-- HEADER -->
    <div class="flex items-center justify-between mb-5 gap-4 flex-wrap">

        <div class="flex gap-3">
       <button
    v-if="can('usuarios.crear')"
    @click="vista = 'empleados'"
    class="px-5 py-2 rounded-xl border shadow-sm"
    :class="vista === 'empleados' ? 'bg-black text-white' : 'bg-white'"
>
    Empleados
</button>

<button
    v-if="can('usuarios.ver')"
    @click="vista = 'usuarios'"
    class="px-5 py-2 rounded-xl border shadow-sm"
    :class="vista === 'usuarios' ? 'bg-black text-white' : 'bg-white'"
>
    Usuarios
</button>
        </div>

        <div class="flex items-center gap-3">

            <div class="bg-white rounded-full px-5 py-2 border flex items-center gap-2">
                <span class="material-symbols-outlined">search</span>
                <input v-model="busqueda"
                    type="text"
                    placeholder="Buscar empleado"
                    class="outline-none bg-transparent text-sm" />
            </div>
 <!-- 🔢 SELECTOR -->
    <select v-model="porPagina"
        class="border rounded-full px-3 py-2 text-sm bg-white">
  <option :value="5">5</option>
        <option :value="10">10</option>
        <option :value="20">20</option>
        <option :value="30">30</option>

    </select>
            

        </div>
    </div>

    <!-- TABLA -->
   <div class="bg-white border rounded-xl overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 text-slate-600">
            <tr>
                <th class="px-4 py-3 text-left">Nombre</th>
                <th class="px-4 py-3 text-left">Correo</th>
                <th class="px-4 py-3 text-left">Rol</th>
                <th class="px-4 py-3 text-left">Acciones</th>
            </tr>
        </thead>
<tbody>
<tr v-for="emp in listaActual" :key="emp.id"
    @click="abrirDetalleUsuario(emp)"
    class="border-t hover:bg-gray-50"
    :class="vista === 'usuarios' ? 'cursor-pointer' : ''">

   <!-- NOMBRE -->
<td class="px-4 py-3">
    {{
        vista === 'usuarios'
            ? (emp.name || '—')
            : `${emp.nombre || ''} ${emp.apellido || ''}`.trim()
    }}
</td>

<!-- CORREO -->
<td class="px-4 py-3">
    {{
       
    }}
</td>

<!-- ROL -->
<td class="px-4 py-3 capitalize">
    {{
        vista === 'usuarios'
            ? (emp.role?.name || 'Sin rol')
            : (emp.user?.role?.name || 'SIN ROL')
    }}
</td>

    <!-- ACCIONES -->
    <td class="px-4 py-3 flex gap-2">

<button
    v-if="vista === 'empleados' && can('usuarios.crear')"
    @click="abrirModal(emp)"
>
    ➕ Crear usuario
</button>

<button
    v-if="vista === 'usuarios' && can('usuarios.editar')"
    @click.stop="abrirModal(emp)"
>
    ✏️
</button>

<button
    v-if="vista === 'usuarios' && can('usuarios.eliminar')"
    @click.stop="eliminarUsuario(emp.id)"
>
    🗑️
</button>

    </td>

</tr>
</tbody>
    </table>
    <!-- MODAL DETALLE USUARIO -->
<div v-if="showDetalle"
    class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">

    <div class="bg-white w-[650px] max-h-[85vh] overflow-y-auto rounded-3xl p-6 shadow-xl">

        <div class="flex justify-between items-center mb-5">
            <h2 class="text-2xl font-bold text-slate-700">
                Detalle del usuario
            </h2>

            <button @click="cerrarDetalleUsuario"
                class="bg-gray-200 hover:bg-gray-300 rounded-full px-3 py-1">
                ✕
            </button>
        </div>

        <div v-if="usuarioSeleccionado" class="space-y-4">

            <div class="bg-gray-50 border rounded-xl p-4 grid gap-2 text-sm">
                <p><strong>ID:</strong> {{ usuarioSeleccionado.id }}</p>
                <p><strong>Nombre:</strong> {{ usuarioSeleccionado.name || '—' }}</p>
                <p><strong>Correo:</strong> {{ usuarioSeleccionado.email || '—' }}</p>
                <p><strong>Empleado ID:</strong> {{ usuarioSeleccionado.empleado_id || '—' }}</p>
                <p><strong>Rol:</strong> {{ usuarioSeleccionado.role?.name || 'Sin rol' }}</p>
            </div>

            <div class="bg-gray-50 border rounded-xl p-4">
                <h3 class="font-semibold mb-3 text-slate-700">
                    Permisos activados
                </h3>

                <div v-if="usuarioSeleccionado.permissions?.length"
                    class="flex flex-wrap gap-2">

                    <span
                        v-for="permiso in usuarioSeleccionado.permissions"
                        :key="permiso.id"
                        class="px-3 py-1 rounded-full bg-green-100 text-green-700 text-xs border border-green-300">
                        {{ permiso.name }}
                    </span>

                </div>

                <p v-else class="text-sm text-gray-500">
                    Este usuario no tiene permisos activados.
                </p>
            </div>

        </div>
    </div>
</div>
</div>
              



                   
    <!-- MODAL -->
    <div v-if="showModal"
        class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">

        <div class="bg-[#e9e9e9] w-[500px] rounded-3xl p-6 shadow-xl">

            <h2 class="text-3xl font-bold text-center mb-6">
                {{ editando ? 'Editar usuario' : 'Registrar usuario' }}
            </h2>

            <div class="grid gap-4 bg-white p-4 rounded-lg">

                <div>
                    <input v-model="form.name"
                    maxlength="50"
                    minlength="1"
                        placeholder="Nombre completo"
                        class="border rounded px-3 py-2 w-full">
                    <p v-if="errores.name" class="text-red-500 text-xs">{{ errores.name }}</p>
                </div>

  <select
    v-model="form.role_id"
    @change="form.permissions = permisosPorRol(form.role_id)"
    class="border rounded px-3 py-2"
>
    <option value="">
        Seleccionar rol
    </option>

    <option
        v-for="rol in roles"
        :key="rol.id"
        :value="rol.id"
    >
        {{ rol.name }}
    </option>
</select>

<!-- BOTON PERMISOS -->
<div>
    <h3 class="font-semibold text-sm mb-2">
        Permisos
    </h3>

    <button
        type="button"
        @click="showPermisosModal = true"
        class="w-full border rounded-xl px-4 py-3 bg-gray-50 hover:bg-gray-100 flex items-center justify-between"
    >
        <span class="text-sm font-medium">
            Configurar permisos
        </span>

        <span class="text-xs text-gray-500">
            {{ form.permissions.length }} seleccionados →
        </span>
    </button>
</div>

            <!-- MODAL PERMISOS -->
<div
    v-if="showPermisosModal"
    class="fixed inset-0 bg-black/50 flex items-center justify-center z-[60]"
>
    <div class="bg-white w-[750px] max-w-[95vw] max-h-[85vh] rounded-3xl shadow-xl flex flex-col">

        <!-- HEADER -->
        <div class="flex items-center justify-between px-6 py-4 border-b">
            <div>
                <h2 class="text-xl font-bold text-slate-700">
                    Configurar permisos
                </h2>
                <p class="text-sm text-gray-500">
                    Selecciona qué módulos y acciones podrá usar este usuario.
                </p>
            </div>

            <button
                type="button"
                @click="showPermisosModal = false"
                class="bg-gray-200 hover:bg-gray-300 rounded-full px-3 py-1"
            >
                ✕
            </button>
        </div>

        <!-- BODY -->
        <div class="p-6 overflow-y-auto">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div
                    v-for="(grupo, modulo) in permisosAgrupados"
                    :key="modulo"
                    class="border rounded-2xl p-4 bg-gray-50"
                >
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold capitalize text-slate-700">
                            {{ modulo }}
                        </h3>

                        <span class="text-xs text-gray-500">
                            {{ grupo.filter(p => form.permissions.includes(p.id)).length }}
                            /
                            {{ grupo.length }}
                        </span>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="perm in grupo"
                            :key="perm.id"
                            class="flex items-center justify-between bg-white px-3 py-2 rounded-xl border"
                        >
                            <span class="text-sm capitalize">
                                {{ perm.name }}
                            </span>

                            <div
                                @click="togglePermiso(perm.id)"
                                class="w-10 h-5 flex items-center rounded-full p-1 cursor-pointer transition"
                                :class="form.permissions.includes(perm.id) ? 'bg-green-500' : 'bg-gray-300'"
                            >
                                <div
                                    class="w-4 h-4 bg-white rounded-full shadow transform transition"
                                    :class="form.permissions.includes(perm.id) ? 'translate-x-5' : 'translate-x-0'"
                                ></div>
                            </div>
                        </div>

                        <p
                            v-if="!grupo.length"
                            class="text-xs text-gray-400 text-center py-2"
                        >
                            Sin permisos registrados.
                        </p>
                    </div>
                </div>
            </div>

        </div>

        <!-- FOOTER -->
        <div class="flex justify-end gap-3 px-6 py-4 border-t bg-gray-50 rounded-b-3xl">
            <button
                type="button"
                @click="showPermisosModal = false"
                class="bg-[#1f1d2b] text-white px-6 py-2 rounded-full"
            >
                Regresar
            </button>
        </div>

    </div>
</div>
                <div>
                    <input v-model="form.email"
                        class="border rounded px-3 py-2 bg-gray-100 w-full">
                    <p v-if="errores.email" class="text-red-500 text-xs">{{ errores.email }}</p>
                </div>

             <div>
    <input 
        type="password"
        v-model="form.password"
        maxlength="15"
        minlength="7"
        placeholder="Contraseña"
        class="border rounded px-3 py-2 w-full"
    >
    <p v-if="errores.password" class="text-red-500 text-xs">
        {{ errores.password }}
    </p>
</div>

                <div>
                    <input type="password"
                        v-model="form.password_confirmation"
                          maxlength="15"
                            minlength="7"
                        placeholder="Confirmar contraseña"
                        class="border rounded px-3 py-2  w-full">
                    <p v-if="errores.password_confirmation" class="text-red-500 text-xs">
                        {{ errores.password_confirmation }}
                    </p>
                </div>

                <div class="flex justify-between mt-3">
             <button
    v-if="editando ? can('usuarios.editar') : can('usuarios.crear')"
    @click="guardarEmpleado"
    class="bg-[#1f1d2b] text-white px-6 py-2 rounded-full"
>
    Guardar
</button>

                    <button @click="cerrarModal"
                        class="bg-gray-300 px-6 py-2 rounded-full">
                        Cancelar
                    </button>
                </div>

            </div>
        </div>
    </div>

</div>
</template>