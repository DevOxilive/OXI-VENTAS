<script setup>
import { ref, watch, computed } from 'vue'
import SistemasLayout from '@/Layouts/SistemasLayout.vue'
import { usePage, useForm, router } from '@inertiajs/vue3'

defineOptions({ layout: SistemasLayout })

// 🔥 PAGE
const page = usePage()

// 🔥 DATA BACKEND
const empleados = computed(() => page.props.empleados || [])
const usuarios = computed(() => page.props.usuarios || [])
const roles = computed(() => page.props.roles || [])
const permissions = computed(() => page.props.permissions || [])
const authPermissions = computed(() => page.props.auth.permissions || [])

// 🔥 UI
const vista = ref('empleados')
const busqueda = ref('')
const porPagina = ref(10)
const pagina = ref(1)

// 🔥 MODAL 
const showModal = ref(false)
const editando = ref(false)
const userId = ref(null)
const moduloActivo = ref(null)


// 🔥 FORM
const form = useForm({
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

    // 🔐 CONTRASEÑA
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
        ? usuarios.value      // ✅ usuarios reales
        : empleados.value     // ✅ empleados reales (BD)

    if (busqueda.value) {
        const b = busqueda.value.toLowerCase()

        data = data.filter(emp =>
            (emp.name || (emp.nombre + ' ' + emp.apellido))?.toLowerCase().includes(b) ||
            (emp.email || emp.correo)?.toLowerCase().includes(b)
        )
    }

    return data
})

const listaActual = computed(() => {
    const inicio = (pagina.value - 1) * porPagina.value
    return listaFiltrada.value.slice(inicio, inicio + porPagina.value)
})

// ✅ PERMISOS
const permisosAgrupados = computed(() => {
    const grupos = {}

    permissions.value.forEach(p => {
        const partes = (p.name || '').split('.')
        const modulo = partes[0] || 'otros'

        if (!grupos[modulo]) grupos[modulo] = []
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

// 🔥 MODAL
function abrirModal(emp = null) {
    form.reset()
    form.clearErrors()
    errores.value = {}
    showModal.value = true

    if (emp && vista.value === 'usuarios') {
        // ✏️ EDITAR USUARIO
        editando.value = true
        userId.value = emp.id

        form.name = emp.name
        form.email = emp.email
        form.role_id = emp.role_id || ''
        form.permissions = (emp.permissions || []).map(p => p.id)

    } else {
        // ➕ CREAR USUARIO DESDE EMPLEADO
        editando.value = false
        userId.value = null

        if (emp) {
            // 🔥 AQUÍ ESTABA TU ERROR
            form.name = (emp.nombre + ' ' + emp.apellido)
        }
    }
}

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
                router.reload({ only: ['empleados'] })
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
}

watch(() => form.name, (n) => {
    if (!editando.value && n) {
        form.email = n
            .toLowerCase()
            .trim()
            .replace(/\s+/g, '.')
            .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
            + '@oxilive.com.mx'
    }
})
</script>

<template>
<div class="bg-[#f6f3f7] min-h-screen rounded-3xl p-6">

    <h1 class="text-xl font-semibold text-slate-700 mb-6">
        Dashboard Empleados
    </h1>

    <!-- HEADER -->
    <div class="flex items-center justify-between mb-5 gap-4 flex-wrap">

        <div class="flex gap-3">
            <button @click="vista = 'empleados'"
                class="px-5 py-2 rounded-xl border shadow-sm"
                :class="vista === 'empleados' ? 'bg-black text-white' : 'bg-white'">
                Empleados
            </button>

            <button @click="vista = 'usuarios'"
                class="px-5 py-2 rounded-xl border shadow-sm"
                :class="vista === 'usuarios' ? 'bg-black text-white' : 'bg-white'">
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
    class="border-t hover:bg-gray-50">

    <!-- NOMBRE -->
    <td class="px-4 py-3">
        {{ vista === 'usuarios'
            ? emp.name
            : emp.nombre + ' ' + emp.apellido }}
    </td>

    <!-- CORREO -->
   <!-- CORREO -->
<td class="px-4 py-3">
    {{ vista === 'usuarios'
        ? emp.email
        : '—' }}
</td>
    <!-- ROL -->
    <td class="px-4 py-3 capitalize">
        {{ vista === 'usuarios'
            ? (emp.role?.name || 'Sin rol')
            : 'SIN ROL' }}
    </td>

    <!-- ACCIONES -->
    <td class="px-4 py-3 flex gap-2">

        <button v-if="vista === 'usuarios'" @click="abrirModal(emp)">✏️</button>

        <button v-if="vista === 'usuarios'" @click="eliminarUsuario(emp.id)">🗑️</button>

        <button v-if="vista === 'empleados'" @click="abrirModal(emp)">
            ➕ Crear usuario
        </button>

    </td>

</tr>
</tbody>
    </table>
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

                <select v-model="form.role_id"
                    class="border rounded px-3 py-2">
                    <option value="">Seleccionar rol</option>
                    <option v-for="rol in roles" :key="rol.id" :value="rol.id">
                        {{ rol.name }}
                    </option>
                </select>

                <!-- PERMISOS (INTOCABLE) -->
                <div>
                    <h3 class="font-semibold text-sm mb-4">Permisos</h3>

                    <div class="flex flex-wrap gap-2 mb-4">
                        <button
                            v-for="(grupo, modulo) in permisosAgrupados"
                            :key="modulo"
                            @click="toggleModulo(modulo)"
                            class="px-4 py-2 text-sm rounded-full border transition capitalize"
                            :class="moduloActivo === modulo
                                ? 'bg-black text-white border-black'
                                : 'bg-white hover:bg-gray-100'">
                            {{ modulo }}
                        </button>
                    </div>

                    <div v-if="moduloActivo"
                        class="bg-gray-50 border rounded-xl p-4 max-h-64 overflow-y-auto">

                        <div class="space-y-2">
                            <div v-for="perm in permisosAgrupados[moduloActivo]" 
                                :key="perm.id"
                                class="flex items-center justify-between bg-white px-3 py-2 rounded-lg border">

                                <span class="text-sm capitalize">
                                    {{ perm.name }}
                                </span>

                                <div @click="togglePermiso(perm.id)"
                                    class="w-10 h-5 flex items-center rounded-full p-1 cursor-pointer transition"
                                    :class="form.permissions.includes(perm.id) ? 'bg-green-500' : 'bg-gray-300'">

                                    <div class="w-4 h-4 bg-white rounded-full shadow transform transition"
                                        :class="form.permissions.includes(perm.id) ? 'translate-x-5' : 'translate-x-0'">
                                    </div>

                                </div>

                            </div>
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
                    <button @click="guardarEmpleado"
                        class="bg-[#1f1d2b] text-white px-6 py-2 rounded-full">
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