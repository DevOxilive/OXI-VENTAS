<script setup>
import GlobalTable from '@/Components/Tables/GlobalTable.vue'


const props = defineProps({
    filteredEmployees: {
        type: Array,
        default: () => []
    }
})

const emits = defineEmits(['view', 'edit', 'delete'])

const columns = [
    {
        key: 'firstName',
        label: 'Nombre',
        format: 'text',
        mobileLabel: 'Empleado',
        mobileSecondary: true,
        formatOptions: { multiline: true },
        subKey: 'lastName'
    },
    {
        key: 'lastName',
        label: 'Apellido',
        format: 'text',
        mobileLabel: 'Empleado',
        mobileSecondary: true,
        formatOptions: { multiline: true },
        subKey: 'firstName' 
    },
    {
        key: 'position',
        label: 'Puesto',
        format: 'text',
        mobileLabel: 'Puesto',
        mobileDisplay: true
    },
    {
        key: 'department',
        label: 'Departamento',
        format: 'text',
        mobileLabel: 'Dpto',
        mobileDisplay: false
    },
    {
        key: 'employmentStatus',
        label: 'Estado',
        format: 'badge',
        formatOptions: {
            statusMap: {
                'Activo': 'green',
                'Inactivo': 'red'
            }
        },
        mobileBadge: true
    },
    {
        key: 'startDate',
        label: 'Periodo de inicio',
        format: 'date',
        mobileLabel: 'Desde',
        mobileDisplay: false
    }
]

const actions = [

    {
        id: 'edit',
        label: 'Editar',
        icon: 'edit',
        variant: 'amber',
        permission: 'employees.update',
        handler: (row) => emits('edit', row)
    },
    {
        id: 'delete',
        label: 'Eliminar',
        icon: 'delete',
        variant: 'red',
        permission: 'employees.delete',
        handler: (row) => emits('delete', row)
    }
]

function handleTableAction({ action, row }) {
    if (action === 'view') emits('view', row)
    else if (action === 'edit') emits('edit', row)
    else if (action === 'delete') emits('delete', row)
}
</script>

<template>
    <GlobalTable
        :items="filteredEmployees"
        :columns="columns"
        :actions="actions"
        mobile-card-header-field="firstName"
        no-data-message="No se encontraron empleados registrados"
        @action="handleTableAction"
    />
</template>