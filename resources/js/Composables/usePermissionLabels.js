import { computed } from 'vue'

const permissionModules = {
  employees: 'Empleados',
  users: 'Usuarios',
  branches: 'Sucursales',
  'inventory.products': 'Productos',
  'inventory.branches': 'Inventario por sucursal',
  'inventory.purchase-reports': 'Reportes de compra',
  inventory: 'Inventario general',
  audits: 'Auditorias',
  files: 'Archivos',
}

const permissionLabels = {
  'employees.view': 'Ver empleados',
  'employees.create': 'Crear empleados',
  'employees.update': 'Editar empleados',
  'employees.delete': 'Eliminar empleados',

  'users.view': 'Ver usuarios',
  'users.create': 'Crear usuarios',
  'users.update': 'Editar usuarios',
  'users.delete': 'Eliminar usuarios',

  'branches.view': 'Ver sucursales',
  'branches.create': 'Crear sucursales',
  'branches.update': 'Editar sucursales',
  'branches.delete': 'Eliminar sucursales',

  'inventory.view': 'Ver inventario general',
  'inventory.create': 'Crear inventario',
  'inventory.update': 'Editar inventario',
  'inventory.delete': 'Eliminar inventario',

  'inventory.products.view': 'Ver productos',
  'inventory.products.create': 'Crear productos',
  'inventory.products.update': 'Editar productos',
  'inventory.products.delete': 'Eliminar productos',

  'inventory.branches.view': 'Ver inventario por sucursal',
  'inventory.branches.create': 'Crear inventario por sucursal',
  'inventory.branches.update': 'Editar inventario por sucursal',
  'inventory.branches.delete': 'Eliminar inventario por sucursal',

  'inventory.purchase-reports.view': 'Ver reportes de compra',
  'inventory.purchase-reports.create': 'Crear reportes de compra',
  'inventory.purchase-reports.update': 'Editar reportes de compra',
  'inventory.purchase-reports.delete': 'Eliminar reportes de compra',

  'audits.physical-counts.view': 'Ver auditorias',
  'audits.physical-counts.count': 'Capturar conteos de auditoria',
  'audits.physical-counts.reports': 'Ver reportes de auditoria',
  'audits.physical-counts.view-stock': 'Ver stock en auditorias',
  'audits.physical-counts.create': 'Crear auditorias',
  'audits.physical-counts.update': 'Editar auditorias',
  'audits.physical-counts.delete': 'Eliminar auditorias',

  'files.export': 'Exportar archivos',
}

function getPermissionModule(permissionName = '') {
  if (permissionName.startsWith('inventory.products.')) return 'inventory.products'
  if (permissionName.startsWith('inventory.branches.')) return 'inventory.branches'
  if (permissionName.startsWith('inventory.purchase-reports.')) return 'inventory.purchase-reports'

  return permissionName.split('.')[0]?.toLowerCase()
}

export function usePermissionLabels(permissions) {
  const groupedPermissions = computed(() => {
    const groups = {
      employees: [],
      users: [],
      branches: [],
      'inventory.products': [],
      'inventory.branches': [],
      'inventory.purchase-reports': [],
      inventory: [],
      audits: [],
      files: [],
    }

    permissions.value.forEach((permission) => {
      const module = getPermissionModule(permission.name || '')

      if (!groups[module]) return

      groups[module].push(permission)
    })

    return groups
  })

  function permissionLabel(permissionName) {
    return permissionLabels[permissionName] || permissionName
  }

  function moduleLabel(module) {
    return permissionModules[module] || module
  }

  return {
    groupedPermissions,
    permissionLabel,
    moduleLabel,
  }
}
