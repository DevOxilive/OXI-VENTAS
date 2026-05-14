<script setup>
import { ref, watch, computed } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { usePage, useForm, router } from '@inertiajs/vue3'
import { usePermissions } from '@/Composables/usePermissions'
import UserRegisterModal from '@/Components/Forms/UserRegisterModal.vue'
import UserDetailModal from '@/Components/Forms/UserDetailModal.vue'
import ActionIconButton from '@/Components/Forms/ActionIconButton.vue'

import {
  UniversalActionModal,
  ToastAlert,
  ErrorAlert,
  WarningAlert,
} from '@/Components/Modales/UniversalActionModal'

defineOptions({ layout: AdminLayout })

const cargandoEdicion = ref(false)

const page = usePage()
const { can } = usePermissions()

const empleados = computed(() => page.props.empleados || [])
const usuarios = computed(() => page.props.usuarios || [])
const roles = computed(() => page.props.roles || [])
const sucursales = computed(() => page.props.sucursales || [])
const permissions = computed(() => page.props.permissions || [])

const vista = ref(can('usuarios.ver') ? 'usuarios' : 'empleados')
const busqueda = ref('')
const porPagina = ref(10)
const pagina = ref(1)

const showModal = ref(false)
const editando = ref(false)
const userId = ref(null)
const moduloActivo = ref(null)

const showDetalle = ref(false)
const usuarioSeleccionado = ref(null)
const showPermisosModal = ref(false)

const form = useForm({
  employee_id: '',
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role_id: '',
  sucursal_ids: [],
  permissions: []
})

const errores = ref({})

function getEmployeeFullName(emp) {
  return `${emp.first_name || emp.firstName || ''} ${emp.last_name || emp.lastName || ''}`.trim()
}

function getEmployeeEmail(emp) {
  return emp.email || ''
}

const rolSeleccionado = computed(() => {
  return roles.value.find(r => String(r.id) === String(form.role_id))
})

const esRolVentas = computed(() => {
  return rolSeleccionado.value?.name === 'Ventas'
})

function permisosPorRol(roleId) {
  const rol = roles.value.find(r => String(r.id) === String(roleId))
  return rol?.permissions?.map(p => p.id) || []
}

function asignarPermisosPorRol() {
  if (cargandoEdicion.value) return

  form.permissions = permisosPorRol(form.role_id)

  if (!esRolVentas.value) {
    form.sucursal_ids = []
  }
}

function validar() {
  errores.value = {}

  if (!form.name) errores.value.name = 'Nombre requerido'

  if (!form.email) {
    errores.value.email = 'Correo requerido'
  } else if (!/^\S+@\S+\.\S+$/.test(form.email)) {
    errores.value.email = 'Correo inválido'
  }

  if (!form.role_id) {
    errores.value.role_id = 'Debes seleccionar un rol'
  }

  if (esRolVentas.value && form.sucursal_ids.length === 0) {
    errores.value.sucursal_ids = 'Selecciona al menos una sucursal para el vendedor'
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

const listaFiltrada = computed(() => {
  let data = vista.value === 'usuarios' ? usuarios.value : empleados.value

  if (busqueda.value) {
    const b = busqueda.value.toLowerCase()

    data = data.filter(emp => {
      const nombre = vista.value === 'usuarios'
        ? (emp.name || '')
        : getEmployeeFullName(emp)

      const correo = vista.value === 'usuarios'
        ? (emp.email || '')
        : getEmployeeEmail(emp)

      return nombre.toLowerCase().includes(b) || correo.toLowerCase().includes(b)
    })
  }

  return data
})

const listaActual = computed(() => {
  const limite = Number(porPagina.value)
  const inicio = (pagina.value - 1) * limite
  return listaFiltrada.value.slice(inicio, inicio + limite)
})

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
    const modulo = (p.name || '').split('.')[0]?.toLowerCase() || 'otros'

    if (!grupos[modulo]) {
      grupos[modulo] = []
    }

    grupos[modulo].push(p)
  })

  return grupos
})

function abrirDetalleUsuario(user) {
  if (vista.value !== 'usuarios') return

  usuarioSeleccionado.value = user
  showDetalle.value = true
}

function cerrarDetalleUsuario() {
  showDetalle.value = false
  usuarioSeleccionado.value = null
}

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

function abrirModal(emp = null) {
  form.reset()
  form.clearErrors()
  errores.value = {}
  showModal.value = true

  if (emp && vista.value === 'usuarios') {
    cargandoEdicion.value = true

    editando.value = true
    userId.value = emp.id

    form.employee_id = emp.employee_id || ''
    form.name = emp.name || ''
    form.email = emp.email || ''
    form.role_id = emp.role_id || ''

    form.permissions = Array.isArray(emp.permissions)
      ? emp.permissions.map(p => p.id)
      : permisosPorRol(emp.role_id)

    form.sucursal_ids = emp.sucursales?.length
      ? emp.sucursales.map(s => s.id)
      : []

    setTimeout(() => {
      cargandoEdicion.value = false
    }, 0)

  } else {
    editando.value = false
    userId.value = null

    if (emp) {
      form.employee_id = emp.id
      form.name = getEmployeeFullName(emp)
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

watch(() => form.role_id, () => {
  asignarPermisosPorRol()
})

function cerrarModal() {
  showModal.value = false
  editando.value = false
  userId.value = null
  showPermisosModal.value = false
  form.reset()
  errores.value = {}
}

function guardarEmpleado() {
  if (!validar()) {
    WarningAlert({
      title: 'Formulario incompleto',
      message: 'Revisa los campos marcados antes de continuar.',
    })

    return
  }

  form.permissions = form.permissions.filter(p => p)

  if (editando.value) {
    form.put(route('sistemas.empleados.update', userId.value), {
      preserveScroll: true,

      onSuccess: () => {
        cerrarModal()

        ToastAlert({
          icon: 'success',
          title: 'Usuario actualizado correctamente',
        })

        router.reload({ only: ['empleados', 'usuarios', 'sucursales'] })
      },

      onError: () => {
        ErrorAlert({
          title: 'Error al actualizar',
          message: 'No fue posible actualizar la información del usuario.',
        })
      }
    })
  } else {
    form.post(route('sistemas.empleados.store'), {
      preserveScroll: true,

      onSuccess: () => {
        cerrarModal()

        ToastAlert({
          icon: 'success',
          title: 'Usuario registrado correctamente',
        })

        router.visit(route('sistemas.empleados'), {
          preserveScroll: true,
          preserveState: false,
          replace: true
        })
      },

      onError: () => {
        ErrorAlert({
          title: 'Error al registrar',
          message: 'No fue posible registrar el usuario.',
        })
      }
    })
  }
}

function eliminarUsuario(id) {
  UniversalActionModal({
    title: 'Confirmar eliminación',
    message: '¿Deseas eliminar permanentemente este usuario?',
    itemName: '',
    confirmText: 'Sí, eliminar',
    cancelText: 'Cancelar',
    icon: 'warning',
    confirmButtonColor: '#ef4444',
  }).then((result) => {
    if (!result.isConfirmed) return

    form.delete(route('sistemas.empleados.destroy', id), {
      preserveScroll: true,

      onSuccess: () => {
        ToastAlert({
          icon: 'success',
          title: 'Usuario eliminado correctamente',
        })

        router.visit(route('sistemas.empleados'), {
          preserveScroll: true,
          preserveState: false,
          replace: true
        })
      },

      onError: () => {
        ErrorAlert({
          title: 'Error al eliminar',
          message: 'No fue posible eliminar el usuario.',
        })
      }
    })
  })
}
</script>

<template>
  <div class="bg-[#f6f3f7] min-h-screen rounded-3xl p-6">

    <h1 class="text-xl font-semibold text-slate-700 mb-6">
      Dashboard Registro de Usuario
    </h1>

    <div class="flex items-center justify-between mb-5 gap-4 flex-wrap">

      <div class="flex gap-3">
        <button v-if="can('usuarios.crear')" @click="vista = 'empleados'" class="px-5 py-2 rounded-xl border shadow-sm"
          :class="vista === 'empleados' ? 'bg-black text-white' : 'bg-white'">
          Empleados
        </button>

        <button v-if="can('usuarios.ver')" @click="vista = 'usuarios'" class="px-5 py-2 rounded-xl border shadow-sm"
          :class="vista === 'usuarios' ? 'bg-black text-white' : 'bg-white'">
          Usuarios
        </button>
      </div>

      <div class="flex items-center gap-3">
        <div class="bg-white rounded-full px-5 py-2 border flex items-center gap-2">
          <span class="material-symbols-outlined">search</span>

          <input v-model="busqueda" type="text" placeholder="Buscar empleado"
            class="outline-none bg-transparent text-sm" />
        </div>

        <select v-model="porPagina" class="border rounded-full px-3 py-2 text-sm bg-white">
          <option :value="5">5</option>
          <option :value="10">10</option>
          <option :value="20">20</option>
          <option :value="30">30</option>
        </select>
      </div>
    </div>

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
          <tr v-for="emp in listaActual" :key="emp.id" @click="abrirDetalleUsuario(emp)"
            class="border-t hover:bg-gray-50" :class="vista === 'usuarios' ? 'cursor-pointer' : ''">

            <td class="px-4 py-3">
              {{
                vista === 'usuarios'
                  ? (emp.name || '—')
                  : (getEmployeeFullName(emp) || 'Sin nombre registrado')
              }}
            </td>

            <td class="px-4 py-3">
              {{
                vista === 'usuarios'
                  ? (emp.email || '—')
                  : 'Sin usuario registrado'
              }}
            </td>

            <td class="px-4 py-3 capitalize">
              {{
                vista === 'usuarios'
                  ? (emp.role?.name || 'Sin rol')
                  : (emp.user?.role?.name || 'Sin usuario registrado')
              }}
            </td>

            <td class="px-4 py-3">
              <div class="flex items-center gap-2">

                <ActionIconButton v-if="vista === 'empleados' && can('usuarios.crear')" icon="person_add"
                  title="Crear usuario" variant="blue" @click.stop="abrirModal(emp)" />

                <ActionIconButton v-if="vista === 'usuarios' && can('usuarios.editar')" icon="edit"
                  title="Editar usuario" variant="amber" @click.stop="abrirModal(emp)" />

                <ActionIconButton v-if="vista === 'usuarios' && can('usuarios.eliminar')" icon="delete"
                  title="Eliminar usuario" variant="red" @click.stop="eliminarUsuario(emp.id)" />

              </div>
            </td>
          </tr>

          <tr v-if="!listaActual.length">
            <td colspan="4" class="px-4 py-10 text-center text-slate-500">
              No se encontraron registros.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <UserDetailModal v-if="showDetalle" :usuario="usuarioSeleccionado" @close="cerrarDetalleUsuario" />

    <UserRegisterModal v-if="showModal" :form="form" :errores="errores" :roles="roles" :sucursales="sucursales"
      :permisosAgrupados="permisosAgrupados" :editando="editando"
      :canGuardar="editando ? can('usuarios.editar') : can('usuarios.crear')" :esRolVentas="esRolVentas"
      @close="cerrarModal" @guardar="guardarEmpleado" @toggle-permiso="togglePermiso"
      @change-role="asignarPermisosPorRol">
    </UserRegisterModal>
  </div>
</template>