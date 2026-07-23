import { computed } from "vue";

const permissionSectionOrder = [
    "home",
    "human-resources",
    "systems",
    "branches",
    "sales",
    "printers",
];

const permissionModuleOrder = [
    "dashboard",
    "employees",
    "departments",
    "positions",
    "attendance",
    "attendance.schedules",
    "attendance.schedule-assignments",
    "attendance.incidents",
    "users",
    "branches",
    "files",
    "inventory.products",
    "inventory.branches",
    "inventory.purchase-reports",
    "inventory.purchase-orders",
    "audits",
    "inventory",
    "sales",
    "systems.tickets",
    "systems.cash-closure-tickets",
    "systems.labels",
];

const permissionSectionsMap = {
    home: "Inicio",
    "human-resources": "Capital Humano",
    systems: "Sistemas",
    branches: "Sucursales",
    sales: "Ventas",
    printers: "Impresoras",
};

const permissionModules = {
    dashboard: {
        label: "Dashboard ejecutivo",
        section: "home",
    },
    employees: {
        label: "Registro de empleados",
        section: "human-resources",
    },
    departments: { label: "Registro de departamentos", section: "human-resources" },
    positions: { label: "Registro de puestos", section: "human-resources" },
    attendance: {
        label: "Asistencias",
        section: "human-resources",
    },
    "attendance.schedules": {
        label: "Horarios",
        section: "human-resources",
    },
    "attendance.schedule-assignments": {
        label: "Asignación de horarios",
        section: "human-resources",
    },
    "attendance.incidents": {
        label: "Incidencias",
        section: "human-resources",
    },
    users: {
        label: "Registro de usuarios",
        section: "systems",
    },
    branches: {
        label: "Registro de sucursales",
        section: "systems",
    },
    files: {
        label: "Exportaciones",
        section: "systems",
    },
    "inventory.products": {
        label: "Productos",
        section: "branches",
    },
    "inventory.branches": {
        label: "Stock",
        section: "branches",
    },
    "inventory.purchase-reports": {
        label: "Listas de compra",
        section: "branches",
    },
    "inventory.purchase-orders": {
        label: "Ordenes generales",
        section: "branches",
    },
    audits: {
        label: "Auditorias",
        section: "branches",
    },
    inventory: {
        label: "Reportes",
        section: "branches",
    },
    sales: {
        label: "Ventas",
        section: "sales",
    },
    "systems.tickets": {
        label: "Tickets",
        section: "printers",
    },
    "systems.cash-closure-tickets": {
        label: "Tickets de corte",
        section: "printers",
    },
    "systems.labels": {
        label: "Etiquetas",
        section: "printers",
    },
};

const permissionLabels = {
    "dashboard.executive.view": "Ver dashboard ejecutivo",

    "employees.view": "Ver empleados y consultar sus datos",
    "employees.create": "Registrar empleados nuevos",
    "employees.update": "Editar información de empleados",
    "employees.delete": "Eliminar empleados",
    "departments.view": "Ver registro de departamentos",
    "departments.create": "Crear departamentos",
    "departments.update": "Editar departamentos",
    "departments.delete": "Eliminar departamentos",
    "positions.view": "Ver registro de puestos",
    "positions.create": "Crear puestos",
    "positions.update": "Editar puestos",
    "positions.delete": "Eliminar puestos",

    "attendance.view": "Ver métricas, filtros y registros de asistencia",
    "attendance.register": "Registrar entrada, comida y salida",
    "attendance.manage": "Ver fotografías y ubicaciones de asistencia",
    "attendance.export.excel": "Exportar registros de asistencia en Excel",
    "attendance.export.pdf": "Exportar registros de asistencia en PDF",
    "attendance.corrections.request": "Solicitar corrección de un registro de asistencia",
    "attendance.corrections.review": "Aprobar o rechazar correcciones de asistencia",
    "attendance.schedules.view": "Ver horarios configurados",
    "attendance.schedules.create": "Crear horarios",
    "attendance.schedules.update": "Editar horarios",
    "attendance.schedules.delete": "Eliminar horarios",
    "attendance.schedule-assignments.view": "Ver horarios asignados al personal",
    "attendance.schedule-assignments.create": "Asignar horarios al personal",
    "attendance.schedule-assignments.update": "Editar asignaciones de horarios",
    "attendance.schedule-assignments.delete": "Desactivar asignaciones de horarios",
    "attendance.incidents.view": "Ver incidencias registradas",
    "attendance.incidents.create": "Registrar incidencias",
    "attendance.incidents.update": "Editar incidencias pendientes",
    "attendance.incidents.delete": "Eliminar incidencias pendientes",
    "attendance.incidents.approve": "Aprobar incidencias pendientes",
    "attendance.incidents.reject": "Rechazar incidencias pendientes",

    "users.view": "Ver modulo de usuarios",
    "users.create": "Crear usuarios",
    "users.update": "Editar usuarios",
    "users.delete": "Eliminar usuarios",

    "branches.view": "Ver modulo de sucursales",
    "branches.create": "Crear sucursales",
    "branches.update": "Editar sucursales",
    "branches.delete": "Eliminar sucursales",
    "branches.access-all": "Acceder a todas las sucursales",

    "sales.view": "Ver modulo de ventas",
    "sales.create": "Crear ventas",
    "sales.update": "Editar ventas",
    "sales.delete": "Eliminar ventas",
    "sales.reports": "Ver reportes de ventas",
    "sales.cash-closures.view": "Ver modulo de cortes de caja",
    "sales.cash-closures.create": "Crear cortes de caja",
    "sales.cash-closures.update": "Editar cortes de caja",
    "sales.cash-closures.delete": "Eliminar cortes de caja",

    "inventory.products.view": "Ver modulo de productos",
    "inventory.products.create": "Crear productos",
    "inventory.products.update": "Editar productos",
    "inventory.products.delete": "Eliminar productos",

    "inventory.branches.view": "Ver modulo de stock y movimientos",
    "inventory.branches.create": "Registrar entradas de stock",
    "inventory.branches.update": "Registrar salidas y ajustes de stock",
    "inventory.branches.delete": "Editar lotes y configuracion de stock",

    "audits.physical-counts.view": "Ver modulo de auditorias",
    "audits.physical-counts.count": "Capturar conteos de auditoria",
    "audits.physical-counts.reports": "Ver reportes de auditoria",
    "audits.physical-counts.view-stock": "Ver stock en auditorias",
    "audits.physical-counts.create": "Crear auditorias",
    "audits.physical-counts.update": "Editar auditorias",
    "audits.physical-counts.delete": "Eliminar auditorias",

    "inventory.purchase-reports.view": "Ver modulo de listas/reportes de compra",
    "inventory.purchase-reports.create": "Crear reportes de compra",
    "inventory.purchase-reports.update": "Editar reportes de compra",
    "inventory.purchase-reports.delete": "Eliminar reportes de compra",
    "inventory.purchase-orders.view": "Consultar ordenes generales",
    "inventory.purchase-orders.create": "Generar ordenes generales",
    "inventory.purchase-orders.update": "Dar seguimiento a compras",
    "inventory.purchase-orders.history": "Consultar compras completadas",

    "inventory.view": "Ver modulo de reportes de inventario",
    "inventory.create": "Crear reportes de inventario",
    "inventory.update": "Editar reportes de inventario",
    "inventory.delete": "Eliminar reportes de inventario",

    "files.export": "Exportar archivos",
    "systems.tickets.view": "Ver modulo de tickets",
    "systems.tickets.update": "Editar configuracion de tickets",
    "systems.cash-closure-tickets.view": "Ver modulo de tickets de corte",
    "systems.cash-closure-tickets.update": "Editar configuracion de tickets de corte",
    "systems.labels.view": "Ver modulo de etiquetas",
    "systems.labels.update": "Editar configuracion de etiquetas",

    "system.center.access": "Acceder al Centro de Administración",
    "system.audit.view": "Consultar Auditoría del Sistema",
    "system.audit.export": "Exportar auditorías",
    "system.audit.filter-advanced": "Usar filtros avanzados de auditoría",
    "system.trash.view": "Consultar Papelera Global",
    "system.trash.restore": "Restaurar registros eliminados",
    "system.trash.force-delete": "Eliminar registros definitivamente",
    "system.trash.empty": "Vaciar y depurar la Papelera Global",
    "system.roles.manage": "Administrar roles",
    "system.permissions.manage": "Administrar permisos",
    "system.super-administrators.manage": "Administrar Super Administradores",
    "system.settings.manage": "Administrar configuración crítica",
    "system.integrations.manage": "Administrar integraciones",
    "system.tools.access": "Acceder a herramientas del sistema",
    "system.monitoring.view": "Consultar monitoreo del sistema",
    "system.statistics.view": "Consultar estadísticas avanzadas",
    "system.logs.view": "Consultar registros del sistema",
    "system.maintenance.manage": "Administrar mantenimiento",
    "system.records.view-all": "Consultar todos los registros del sistema",
};

const branchScopedPermissionNames = [
    "inventory.view",
    "inventory.create",
    "inventory.update",
    "inventory.delete",
];

function getPermissionModule(permissionName = "") {
    if (permissionName.startsWith("dashboard.")) {
        return "dashboard";
    }

    if (permissionName.startsWith("attendance.schedule-assignments.")) {
        return "attendance.schedule-assignments";
    }

    if (permissionName.startsWith("attendance.schedules.")) {
        return "attendance.schedules";
    }

    if (permissionName.startsWith("attendance.incidents.")) {
        return "attendance.incidents";
    }

    if (permissionName.startsWith("systems.tickets.")) {
        return "systems.tickets";
    }

    if (permissionName.startsWith("systems.cash-closure-tickets.")) {
        return "systems.cash-closure-tickets";
    }

    if (permissionName.startsWith("systems.labels.")) {
        return "systems.labels";
    }

    if (permissionName.startsWith("inventory.products.")) {
        return "inventory.products";
    }

    if (permissionName.startsWith("inventory.branches.")) {
        return "inventory.branches";
    }

    if (permissionName.startsWith("inventory.purchase-reports.")) {
        return "inventory.purchase-reports";
    }

    if (permissionName.startsWith("inventory.purchase-orders.")) {
        return "inventory.purchase-orders";
    }

    if (permissionName.startsWith("audits.")) {
        return "audits";
    }

    return permissionName.split(".")[0]?.toLowerCase();
}

function createOrderedModules() {
    return permissionModuleOrder.reduce((groups, module) => {
        groups[module] = [];
        return groups;
    }, {});
}

function buildSectionCollection(groupedModules) {
    return permissionSectionOrder
        .map((sectionKey) => ({
            key: sectionKey,
            label: permissionSectionsMap[sectionKey] || sectionKey,
            modules: permissionModuleOrder
                .filter((moduleKey) => permissionModules[moduleKey]?.section === sectionKey)
                .map((moduleKey) => ({
                    key: moduleKey,
                    label: permissionModules[moduleKey]?.label || moduleKey,
                    permissions: groupedModules[moduleKey] || [],
                }))
                .filter((module) => module.permissions.length),
        }))
        .filter((section) => section.modules.length);
}

export function requiresBranchAssignments(roleName = "", permissionNames = []) {
    if (["Administrador", "Super Administrador"].includes(roleName)) {
        return false;
    }

    if (roleName === "Ventas") {
        return true;
    }

    return permissionNames.some((permissionName = "") => {
        return (
            permissionName.startsWith("sales.") ||
            permissionName.startsWith("inventory.products.") ||
            permissionName.startsWith("inventory.branches.") ||
            permissionName.startsWith("inventory.purchase-reports.") ||
            permissionName.startsWith("inventory.purchase-orders.") ||
            permissionName.startsWith("audits.physical-counts.") ||
            branchScopedPermissionNames.includes(permissionName)
        );
    });
}

export function usePermissionLabels(permissions) {
    const groupedPermissions = computed(() => {
        const groups = createOrderedModules();

        permissions.value.forEach((permission) => {
            const module = getPermissionModule(permission.name || "");

            if (!groups[module]) {
                return;
            }

            groups[module].push(permission);
        });

        return groups;
    });

    const permissionSections = computed(() => {
        return buildSectionCollection(groupedPermissions.value);
    });

    function permissionLabel(permissionName) {
        return permissionLabels[permissionName] || "Permiso sin etiqueta disponible";
    }

    function moduleLabel(module) {
        return permissionModules[module]?.label || module;
    }

    function sectionLabel(section) {
        return permissionSectionsMap[section] || section;
    }

    return {
        groupedPermissions,
        permissionSections,
        permissionLabel,
        moduleLabel,
        sectionLabel,
    };
}
