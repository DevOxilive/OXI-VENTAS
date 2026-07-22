const statusOptions = {
    statusMap: {
        Activo: "green",
        Inactivo: "red",
    },
};

export const departmentTableConfig = {
    columns: [
        {
            key: "name",
            label: "Nombre del departamento",
            format: "text",
            mobileSecondary: true,
        },
        {
            key: "positionsCount",
            label: "Puestos",
            format: "number",
            mobileLabel: "Puestos",
            mobileDisplay: true,
        },
        {
            key: "employeesCount",
            label: "Empleados",
            format: "number",
            mobileLabel: "Empleados",
            mobileDisplay: true,
        },
    ],
    actions: [
        {
            id: "view",
            label: "Ver",
            icon: "visibility",
            variant: "blue",
            permission: "departments.view",
            mobile: "button",
        },
        {
            id: "edit",
            label: "Editar",
            icon: "edit",
            variant: "amber",
            permission: "departments.update",
            mobile: "button",
        },
        {
            id: "delete",
            label: "Eliminar",
            icon: "delete",
            variant: "red",
            permission: "departments.delete",
            mobile: "button",
        },
    ],
    mobileCardHeaderField: "name",
    noDataMessage: "No se encontraron departamentos registrados",
    rowKey: "id",
};

export const positionTableConfig = {
    columns: [
        {
            key: "name",
            label: "Puesto",
            format: "text",
            mobileSecondary: true,
        },
        {
            key: "departmentName",
            label: "Departamento",
            format: "text",
            mobileLabel: "Departamento",
            mobileDisplay: true,
        },
        {
            key: "employeesCount",
            label: "Empleados",
            format: "number",
            mobileLabel: "Empleados",
            mobileDisplay: true,
        },
        {
            key: "status",
            label: "Estado",
            format: "badge",
            formatOptions: statusOptions,
            mobileBadge: true,
        },
    ],
    actions: [
        {
            id: "view",
            label: "Ver",
            icon: "visibility",
            variant: "blue",
            permission: "positions.view",
            mobile: "button",
        },
        {
            id: "edit",
            label: "Editar",
            icon: "edit",
            variant: "amber",
            permission: "positions.update",
            mobile: "button",
        },
        {
            id: "delete",
            label: "Eliminar",
            icon: "delete",
            variant: "red",
            permission: "positions.delete",
            mobile: "button",
        },
    ],
    mobileCardHeaderField: "name",
    noDataMessage: "No se encontraron puestos registrados",
    rowKey: "id",
};
