export const employeeTableConfig = {
  columns: [
    {
      key: 'search',
      label: 'Empleado',
      format: 'text',
      mobileLabel: 'Empleado',
      mobileSecondary: true,
    },
    {
      key: 'firstName',
      label: 'Nombre',
      format: 'text',
      mobileDisplay: false,
    },
    {
      key: 'position',
      label: 'Puesto',
      format: 'text',
      mobileLabel: 'Puesto',
      mobileSecondary: true,
    },
    {
      key: 'department',
      label: 'Departamento',
      format: 'text',
      mobileLabel: 'Dpto',
      mobileDisplay: false,
    },
    {
      key: 'employmentStatus',
      label: 'Estado',
      format: 'badge',
      formatOptions: {
        statusMap: {
          'Activo': 'green',
          'Inactivo': 'red',
          'Pendiente': 'amber'
        }
      },
      mobileBadge: true,
    },
    {
      key: 'startDate',
      label: 'Inicio',
      format: 'date',
      mobileLabel: 'Desde',
      mobileDisplay: false,
    }
  ],

  actions: [
    {
      id: 'view',
      label: 'Ver',
      icon: 'visibility',
      variant: 'blue',
      permission: 'employees.view',
      mobile: 'button'
    },
    {
      id: 'edit',
      label: 'Editar',
      icon: 'edit',
      variant: 'amber',
      permission: 'employees.update',
      mobile: 'button'
    },
    {
      id: 'delete',
      label: 'Eliminar',
      icon: 'delete',
      variant: 'red',
      permission: 'employees.delete',
      mobile: 'button'
    }
  ],

  mobileCardHeaderField: 'firstName',
  noDataMessage: 'No se encontraron empleados registrados',
  rowKey: 'id'
}
