export const employeeTableConfig = {
    columns: [
        {
            key: "fullName",
            label: "Empleado",
            format: "text",
            mobileLabel: "Empleado",
            mobileSecondary: true,
        },
        {
            key: "position",
            label: "Puesto",
            format: "text",
            mobileLabel: "Puesto",
            mobileDisplay: true,
        },
        {
            key: "department",
            label: "Departamento",
            format: "text",
            mobileLabel: "Dpto",
            mobileDisplay: true,
        },
        {
            key: "employmentStatus",
            label: "Estado",
            format: "badge",
            formatOptions: {
                statusMap: {
                    Activo: "green",
                    Inactivo: "red",
                    Pendiente: "amber",
                },
            },
            mobileBadge: true,
        },
        {
            key: "startDate",
            label: "Inicio",
            format: "date",
            mobileLabel: "Desde",
            mobileDisplay: true,
        },
    ],

    actions: [
        {
            id: "view",
            label: "Ver",
            icon: "visibility",
            variant: "blue",
            permission: "employees.view",
            mobile: "button",
        },
        {
            id: "edit",
            label: "Editar",
            icon: "edit",
            variant: "amber",
            permission: "employees.update",
            mobile: "button",
        },
        {
            id: "delete",
            label: "Eliminar",
            icon: "delete",
            variant: "red",
            permission: "employees.delete",
            mobile: "button",
        },
    ],

    mobileCardHeaderField: "fullName",
    noDataMessage: "No se encontraron empleados registrados",
    rowKey: "id",
};
