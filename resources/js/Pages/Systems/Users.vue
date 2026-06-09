<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import { usePage, useForm, router } from '@inertiajs/vue3'

import {
  usePermissions,
  updateLivePermissions
} from '@/Composables/usePermissions'

import AdminLayout from '@/Layouts/AdminLayout.vue'
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

const page = usePage()
const { can } = usePermissions()

const isLoadingEdit = ref(false)

const employees = computed(() => page.props.employees || [])
const users = computed(() => page.props.users || [])
const roles = computed(() => page.props.roles || [])
const branches = computed(() => page.props.branches || [])
const permissions = computed(() => page.props.permissions || [])

const viewMode = ref(can('users.view') ? 'users' : 'employees')
const search = ref('')
const perPage = ref(10)
const currentPage = ref(1)

const showModal = ref(false)
const isEditing = ref(false)
const selectedUserId = ref(null)
const activeModule = ref(null)

const showUserDetail = ref(false)
const selectedUser = ref(null)
const showPermissionsModal = ref(false)

const errors = ref({})

const form = useForm({
  employee_id: '',
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role_id: '',
  branch_ids: [],
  permissions: [],
})

function getEmployeeFullName(employee) {
  return `${employee.first_name || employee.firstName || ''} ${employee.last_name || employee.lastName || ''}`.trim()
}

function getEmployeeEmail(employee) {
  return employee.email || ''
}

const selectedRole = computed(() => {
  return roles.value.find((role) => String(role.id) === String(form.role_id))
})

const isSalesRole = computed(() => {
  return selectedRole.value?.name === 'Ventas'
})

function getPermissionsByRole(roleId) {
  const role = roles.value.find((role) => String(role.id) === String(roleId))

  return role?.permissions?.map((permission) => permission.id) || []
}

function assignPermissionsByRole() {
  if (isLoadingEdit.value) return

  form.permissions = getPermissionsByRole(form.role_id)

  if (!isSalesRole.value) {
    form.branch_ids = []
  }
}

function validateForm() {
  errors.value = {}

  if (!form.name) errors.value.name = 'Nombre requerido'

  if (!form.email) {
    errors.value.email = 'Correo requerido'
  } else if (!/^\S+@\S+\.\S+$/.test(form.email)) {
    errors.value.email = 'Correo inválido'
  }

  if (!form.role_id) {
    errors.value.role_id = 'Debes seleccionar un rol'
  }

  if (isSalesRole.value && form.branch_ids.length === 0) {
    errors.value.branch_ids = 'Selecciona al menos una sucursal para el vendedor'
  }

  if (!isEditing.value || form.password) {
    if (!form.password) {
      errors.value.password = 'Contraseña requerida'
    } else if (form.password.length < 7) {
      errors.value.password = 'Mínimo 7 caracteres'
    } else if (form.password.length > 15) {
      errors.value.password = 'Máximo 15 caracteres'
    }

    if (form.password !== form.password_confirmation) {
      errors.value.password_confirmation = 'No coincide'
    }
  }

  return Object.keys(errors.value).length === 0
}

const filteredList = computed(() => {
  let data = viewMode.value === 'users' ? users.value : employees.value

  if (search.value) {
    const searchText = search.value.toLowerCase()

    data = data.filter((item) => {
      const name = viewMode.value === 'users'
        ? (item.name || '')
        : getEmployeeFullName(item)

      const email = viewMode.value === 'users'
        ? (item.email || '')
        : getEmployeeEmail(item)

      return (
        name.toLowerCase().includes(searchText) ||
        email.toLowerCase().includes(searchText)
      )
    })
  }

  return data
})

const currentList = computed(() => {
  const limit = Number(perPage.value)
  const start = (currentPage.value - 1) * limit

  return filteredList.value.slice(start, start + limit)
})

const groupedPermissions = computed(() => {
  const groups = {
    employees: [],
    roles: [],
    users: [],
    files: [],
    sales: [],
    inventory: [],
    audits: [],
  }

  permissions.value.forEach((permission) => {
    const module = (permission.name || '').split('.')[0]?.toLowerCase() || 'others'

    if (!groups[module]) return

    groups[module].push(permission)
  })

  return groups
})

function openUserDetail(user) {
  if (viewMode.value !== 'users') return

  selectedUser.value = user
  showUserDetail.value = true
}

function closeUserDetail() {
  showUserDetail.value = false
  selectedUser.value = null
}

function toggleModule(module) {
  activeModule.value = activeModule.value === module ? null : module
}

function togglePermission(id) {
  if (form.permissions.includes(id)) {
    form.permissions = form.permissions.filter((permissionId) => permissionId !== id)
    return
  }

  form.permissions = [...form.permissions, id]
}

function enableModule(module) {
  const ids = groupedPermissions.value[module].map((permission) => permission.id)

  form.permissions = [
    ...new Set([
      ...form.permissions,
      ...ids,
    ]),
  ]
}

function disableModule(module) {
  const ids = groupedPermissions.value[module].map((permission) => permission.id)

  form.permissions = form.permissions.filter((id) => !ids.includes(id))
}

function openModal(item = null) {
  form.reset()
  form.clearErrors()
  errors.value = {}
  showModal.value = true

  if (item && viewMode.value === 'users') {
    isLoadingEdit.value = true
    isEditing.value = true
    selectedUserId.value = item.id

    form.employee_id = item.employee_id || ''
    form.name = item.name || ''
    form.email = item.email || ''
    form.role_id = item.role_id || ''

    form.permissions = Array.isArray(item.permissions)
      ? item.permissions.map((permission) => permission.id)
      : getPermissionsByRole(item.role_id)

    form.branch_ids = item.branches?.length
      ? item.branches.map((branch) => branch.id)
      : []

    setTimeout(() => {
      isLoadingEdit.value = false
    }, 0)

    return
  }

  isEditing.value = false
  selectedUserId.value = null

  if (item) {
    form.employee_id = item.id
    form.name = getEmployeeFullName(item)
  }
}

watch(() => form.name, (newName) => {
  if (!newName) {
    form.email = ''
    return
  }

  const emailName = newName
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\s+/g, '.')
    .replace(/[^a-z0-9.]/g, '')

  form.email = `${emailName}@oxilive.com.mx`
})

watch(() => form.role_id, () => {
  assignPermissionsByRole()
})

function closeModal() {
  showModal.value = false
  isEditing.value = false
  selectedUserId.value = null
  showPermissionsModal.value = false
  form.reset()
  errors.value = {}
}

function saveUser() {
  if (!validateForm()) {
    WarningAlert({
      title: 'Formulario incompleto',
      message: 'Revisa los campos marcados antes de continuar.',
    })

    return
  }

  form.permissions = form.permissions.filter((permission) => permission)

  if (isEditing.value) {
    form.put(route('systems.users.update', selectedUserId.value), {
      preserveScroll: true,

      onSuccess: () => {
        closeModal()

        ToastAlert({
          icon: 'success',
          title: 'Usuario actualizado correctamente',
        })

        router.reload({ only: ['employees', 'users', 'branches'] })
      },

      onError: () => {
        ErrorAlert({
          title: 'Error al actualizar',
          message: 'No fue posible actualizar la información del usuario.',
        })
      },
    })

    return
  }

  form.post(route('systems.users.store'), {
    preserveScroll: true,

    onSuccess: () => {
      closeModal()

      ToastAlert({
        icon: 'success',
        title: 'Usuario registrado correctamente',
      })

      router.visit(route('systems.users.index'), {
        preserveScroll: true,
        preserveState: false,
        replace: true,
      })
    },

    onError: () => {
      ErrorAlert({
        title: 'Error al registrar',
        message: 'No fue posible registrar el usuario.',
      })
    },
  })
}

function deleteUser(id) {
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

    form.delete(route('systems.users.destroy', id), {
      preserveScroll: true,

      onSuccess: () => {
        ToastAlert({
          icon: 'success',
          title: 'Usuario eliminado correctamente',
        })

        router.visit(route('systems.users.index'), {
          preserveScroll: true,
          preserveState: false,
          replace: true,
        })
      },

      onError: () => {
        ErrorAlert({
          title: 'Error al eliminar',
          message: 'No fue posible eliminar el usuario.',
        })
      },
    })
  })
}

function reloadSystem() {
  router.reload({
    only: [
      'employees',
      'users',
      'roles',
      'permissions',
      'branches',
    ],
    preserveScroll: true,
    preserveState: true,
  })
}

onMounted(() => {
  if (!window.Echo) return

  window.Echo.channel('systems')
    .listen('.EmployeeChanged', () => {
      reloadSystem()
    })
    .listen('.UserChanged', (event) => {
      if (page.props.auth.user.id === event.userId) {
        updateLivePermissions({
          permissions: event.permissions,
          role: event.role,
        })
      }

      if (
        showModal.value &&
        selectedUserId.value === event.userId
      ) {
        const updatedPermissions = permissions.value
          .filter((permission) => event.permissions.includes(permission.name))
          .map((permission) => permission.id)

        form.permissions = updatedPermissions
      }

      reloadSystem()
    })
})

onBeforeUnmount(() => {
  if (!window.Echo) return

  window.Echo.leave('systems')
})
</script>

<template>
  <div class="bg-[#f6f3f7] min-h-screen rounded-3xl p-6">
    <h1 class="text-xl font-semibold text-slate-700 mb-6">
      Dashboard Registro de Usuarios
    </h1>

    <div class="flex items-center justify-between mb-5 gap-4 flex-wrap">
      <div class="flex gap-3">
        <button v-if="can('users.create')" @click="viewMode = 'employees'" class="px-5 py-2 rounded-xl border shadow-sm"
          :class="viewMode === 'employees' ? 'bg-black text-white' : 'bg-white'">
          Empleados
        </button>

        <button v-if="can('users.view')" @click="viewMode = 'users'" class="px-5 py-2 rounded-xl border shadow-sm"
          :class="viewMode === 'users' ? 'bg-black text-white' : 'bg-white'">
          Usuarios
        </button>
      </div>

      <div class="flex items-center gap-3">
        <div class="bg-white rounded-full px-5 py-2 border flex items-center gap-2">
          <span class="material-symbols-outlined">search</span>

          <input v-model="search" type="text" placeholder="Buscar empleado"
            class="outline-none bg-transparent text-sm" />
        </div>

        <select v-model="perPage" class="border rounded-full px-3 py-2 text-sm bg-white">
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
          <tr v-for="item in currentList" :key="item.id" @click="openUserDetail(item)" class="border-t hover:bg-gray-50"
            :class="viewMode === 'users' ? 'cursor-pointer' : ''">
            <td class="px-4 py-3">
              {{
                viewMode === 'users'
                  ? (item.name || '—')
                  : (getEmployeeFullName(item) || 'Sin nombre registrado')
              }}
            </td>

            <td class="px-4 py-3">
              {{
                viewMode === 'users'
                  ? (item.email || '—')
                  : 'Sin usuario registrado'
              }}
            </td>

            <td class="px-4 py-3 capitalize">
              {{
                viewMode === 'users'
                  ? (item.role?.name || 'Sin rol')
                  : (item.user?.role?.name || 'Sin usuario registrado')
              }}
            </td>

            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <ActionIconButton v-if="viewMode === 'employees' && can('users.create')" icon="person_add"
                  title="Crear usuario" variant="blue" @click.stop="openModal(item)" />

                <ActionIconButton v-if="viewMode === 'users' && can('users.update')" icon="edit" title="Editar usuario"
                  variant="amber" @click.stop="openModal(item)" />

                <ActionIconButton v-if="viewMode === 'users' && can('users.delete')" icon="delete"
                  title="Eliminar usuario" variant="red" @click.stop="deleteUser(item.id)" />
              </div>
            </td>
          </tr>

          <tr v-if="!currentList.length">
            <td colspan="4" class="px-4 py-10 text-center text-slate-500">
              No se encontraron registros.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <UserDetailModal v-if="showUserDetail" :user="selectedUser" @close="closeUserDetail" />

    <UserRegisterModal v-if="showModal" :form="form" :errors="errors" :roles="roles" :branches="branches"
      :groupedPermissions="groupedPermissions" :isEditing="isEditing"
      :canSave="isEditing ? can('users.update') : can('users.create')" :isSalesRole="isSalesRole" @close="closeModal"
      @save="saveUser" @toggle-permission="togglePermission" @change-role="assignPermissionsByRole" />
  </div>
</template>