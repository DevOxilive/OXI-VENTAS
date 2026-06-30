<script setup>
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import { usePage, useForm, router } from '@inertiajs/vue3'
import { usePermissionLabels } from '@/Composables/usePermissionLabels'
import UserToolbar from '@/Components/Systems/UserToolbar.vue'
import UserTable from '@/Components/Systems/UserTable.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import {
  usePermissions,
  updateLivePermissions
} from '@/Composables/usePermissions'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import UserRegisterModal from '@/Components/Systems/UserRegisterModal.vue'
import UserDetailModal from '@/Components/Systems/UserDetailModal.vue'
import { WarningAlert } from '@/Components/Modales/UniversalActionModal'
import { confirmModalAction, getModalRequestOptions } from '@/Components/Modales/useModalConfig'
defineOptions({ layout: AdminLayout })

const page = usePage()
const { can } = usePermissions()

const isLoadingEdit = ref(false)

const employeesDB = computed(() => page.props.employeesDB || {})
const usersDB = computed(() => page.props.usersDB || {})

const employees = computed(() => employeesDB.value.data || [])
const users = computed(() => usersDB.value.data || [])

const roles = computed(() => page.props.roles || [])
const branches = computed(() => page.props.branches || [])
const permissions = computed(() => page.props.permissions || [])
const filters = computed(() => page.props.filters || {})

const search = ref(filters.value.search || '')
const recordsPerPage = ref(filters.value.perPage || 50)

const {
  groupedPermissions,
  permissionLabel,
  moduleLabel,
} = usePermissionLabels(permissions)

const canSeeUsersTab = computed(() =>
  can('users.view') ||
  can('users.update') ||
  can('users.delete')
)

const canSeeEmployeesTab = computed(() =>
  can('users.create')
)

const viewMode = ref(
  filters.value.view ||
  (
    canSeeUsersTab.value
      ? 'users'
      : canSeeEmployeesTab.value
        ? 'employees'
        : 'users'
  )
)

function changeViewMode(mode) {
  viewMode.value = mode
}

const showModal = ref(false)
const isEditing = ref(false)
const selectedUserId = ref(null)
const activeModule = ref(null)

const showUserDetail = ref(false)
const selectedUser = ref(null)

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

let systemsChannel = null
let handleEmployeeChanged = null
let handleUserChanged = null

function getEmployeeFullName(employee) {
  return `${employee.first_name || employee.firstName || ''} ${employee.last_name || employee.lastName || ''}`.trim()
}

function getEmployeeEmail(employee) {
  return employee.email || ''
}

function buildSuggestedEmail(name) {
  const normalized = (name || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^A-Za-z\s]/g, ' ')
    .trim()

  if (!normalized) {
    return ''
  }

  const parts = normalized.split(/\s+/).filter(Boolean)
  const firstName = (parts[0] || '').toLowerCase()
  const lastNameInitials = parts
    .slice(1)
    .map((part) => part.charAt(0).toUpperCase())
    .join('')

  return `${firstName}${lastNameInitials}@oxilive.com.mx`
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

function getEffectivePermissionIds(userLike) {
  const rolePermissionIds = userLike?.role?.permissions?.map((permission) => permission.id) || []
  const allowedPermissionIds = userLike?.permissions
    ?.filter((permission) => (permission.pivot?.mode || 'allow') === 'allow')
    .map((permission) => permission.id) || []
  const deniedPermissionIds = userLike?.permissions
    ?.filter((permission) => permission.pivot?.mode === 'deny')
    .map((permission) => permission.id) || []

  return [...new Set([
    ...rolePermissionIds,
    ...allowedPermissionIds,
  ])].filter((permissionId) => !deniedPermissionIds.includes(permissionId))
}

const lockedPermissionIds = computed(() => {
  return getPermissionsByRole(form.role_id).map((id) => Number(id))
})

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

const normalizedList = computed(() => {
  const data = viewMode.value === 'users'
    ? users.value
    : employees.value

  return data.map((item) => {
    if (viewMode.value === 'users') {
      return {
        ...item,
        displayName: item.name || '—',
        displayEmail: item.email || '—',
        displayRole: item.role?.name || 'Sin rol',
      }
    }

    return {
      ...item,
      displayName: getEmployeeFullName(item) || 'Sin nombre registrado',
      displayEmail: getEmployeeEmail(item) || 'Sin correo registrado',
      displayRole: 'Sin usuario registrado',
    }
  })
})

const activePaginator = computed(() => {
  return viewMode.value === 'users'
    ? usersDB.value
    : employeesDB.value
})

function reloadUsers() {
  router.get(route('systems.users.index'), {
    search: search.value,
    per_page: recordsPerPage.value,
    view: viewMode.value,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function handlePageChange(url) {
  if (!url) return

  router.get(url, {}, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

watch(search, () => {
  reloadUsers()
})

watch(recordsPerPage, () => {
  reloadUsers()
})

watch(viewMode, () => {
  reloadUsers()
})

const userTableActionHandlers = {
  view: (row) => {
    if (!can('users.view')) return
    openUserDetail(row)
  },
  createUser: (row) => {
    if (!can('users.create')) return
    openModal(row)
  },
  edit: (row) => {
    if (!can('users.update')) return
    openModal(row)
  },
  delete: (row) => {
    if (!can('users.delete')) return
    deleteUser(row.id)
  },
}

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

    form.permissions = getEffectivePermissionIds(item)

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
  if (isEditing.value) return

  if (!newName) {
    form.email = ''
    return
  }

  form.email = buildSuggestedEmail(newName)
})

watch(() => form.role_id, () => {
  if (isEditing.value) return

  assignPermissionsByRole()
})

function closeModal() {
  showModal.value = false
  isEditing.value = false
  selectedUserId.value = null
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

  form.transform((data) => ({
    ...data,
    permissions: form.permissions.filter((permission) => permission),
  }))

  if (isEditing.value) {
    form.put(route('systems.users.update', selectedUserId.value), getModalRequestOptions({
      mode: 'update',
      entityName: 'Usuario',
      close: closeModal,
      successTitle: 'Usuario actualizado correctamente',
      errorTitle: 'Error al actualizar',
      errorMessage: 'No fue posible actualizar la informaci?n del usuario.',
      onSuccess: () => {
        reloadUsers()
      },
      onFinish: () => {
        form.transform((data) => data)
      },
    }))

    return
  }

  form.post(route('systems.users.store'), getModalRequestOptions({
    mode: 'create',
    entityName: 'Usuario',
    close: closeModal,
    successTitle: 'Usuario registrado correctamente',
    errorTitle: 'Error al registrar',
    errorMessage: 'No fue posible registrar el usuario.',
    onSuccess: () => {
      reloadUsers()
    },
    onFinish: () => {
      form.transform((data) => data)
    },
  }))
}
function deleteUser(id) {
  confirmModalAction({
    mode: 'delete',
    entityName: 'usuario',
    title: 'Confirmar eliminaci�n',
    message: '?Deseas eliminar permanentemente este usuario?',
    confirmText: 'S?, eliminar',
    cancelText: 'Cancelar',
    confirmButtonColor: '#ef4444',
  }).then((result) => {
    if (!result.isConfirmed) return

    form.delete(route('systems.users.destroy', id), getModalRequestOptions({
      mode: 'delete',
      entityName: 'Usuario',
      successTitle: 'Usuario eliminado correctamente',
      errorTitle: 'Error al eliminar',
      errorMessage: 'No fue posible eliminar el usuario.',
      onSuccess: () => {
        reloadUsers()
      },
    }))
  })
}
function reloadSystem() {
  router.reload({
    only: [
      'employeesDB',
      'usersDB',
      'roles',
      'permissions',
      'branches',
      'filters',
    ],
    preserveScroll: true,
    preserveState: true,
  })
}

onMounted(() => {
  if (!window.Echo) return

  handleEmployeeChanged = () => {
    reloadSystem()
  }

  handleUserChanged = (event) => {
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
  }

  systemsChannel = window.Echo.channel('systems')
    .listen('.employee.changed', handleEmployeeChanged)
    .listen('.UserChanged', handleUserChanged)
})

onBeforeUnmount(() => {
  if (!systemsChannel) return

  if (handleEmployeeChanged) {
    systemsChannel.stopListening('.employee.changed', handleEmployeeChanged)
  }

  if (handleUserChanged) {
    systemsChannel.stopListening('.UserChanged', handleUserChanged)
  }
})
</script>
<template>
  <PageLayout>
    <template #toolbar>
      <UserToolbar :view-mode="viewMode" :search="search" :records-per-page="recordsPerPage"
        :filtered-records="normalizedList.length" :total-records="activePaginator?.total || 0"
        @update:active-tab="changeViewMode" @update:search="search = $event"
        @update:records-per-page="recordsPerPage = $event" />
    </template>

    <UserTable :items="normalizedList" :pagination="activePaginator" :view-mode="viewMode" :can="can"
      :action-handlers="userTableActionHandlers" @page-change="handlePageChange" />

    <UserDetailModal v-if="showUserDetail && can('users.view')" :user="selectedUser" :permissions="permissions"
      @close="closeUserDetail" />

    <UserRegisterModal v-if="showModal" :form="form" :errors="errors" :roles="roles" :branches="branches"
      :groupedPermissions="groupedPermissions" :isEditing="isEditing"
      :canSave="isEditing ? can('users.update') : can('users.create')" :isSalesRole="isSalesRole"
      :locked-permission-ids="lockedPermissionIds"
      :module-label="moduleLabel" :permission-label="permissionLabel" @close="closeModal" @save="saveUser"
      @toggle-permission="togglePermission" @change-role="assignPermissionsByRole" />
  </PageLayout>
</template>
