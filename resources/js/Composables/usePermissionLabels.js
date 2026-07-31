import { computed } from "vue";

const permissionSectionOrder = [
    "home",
    "human-resources",
    "systems",
    "branches",
    "sales",
    "reports",
    "printers",
    "administration",
];

const permissionModuleOrder = [
    "dashboard",
    "employees",
    "organization-structure",
    "attendance",
    "attendance.schedules",
    "attendance.schedule-assignments",
    "attendance.incidents",
    "users",
    "branches",
    "files",
    "inventory.products",
    "inventory.branches",
    "inventory.purchase-orders",
    "inventory.general-purchase-orders",
    "audits",
    "sales",
    "sales.purchase-lists",
    "sales.purchase-orders",
    "reports.audits",
    "reports.cash-closures",
    "reports.inventory",
    "reports.movements",
    "systems.tickets",
    "systems.cash-closure-tickets",
    "systems.labels",
    "system.center",
    "system.audit",
    "system.trash",
    "system.access-control",
    "system.configuration",
    "system.monitoring",
    "system.records",
];

const permissionSectionsMap = {
    home: "Inicio",
    "human-resources": "Capital Humano",
    systems: "Sistemas",
    branches: "Sucursales",
    sales: "Ventas",
    reports: "Reportes",
    printers: "Impresoras",
    administration: "Administración",
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
    "organization-structure": {
        label: "Registro de Puestos y Departamentos",
        section: "human-resources",
    },
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
    "inventory.purchase-orders": {
        label: "Órdenes de compra",
        section: "branches",
    },
    "inventory.general-purchase-orders": {
        label: "Órdenes de compra generales",
        section: "branches",
    },
    audits: {
        label: "Auditorias",
        section: "branches",
    },
    sales: {
        label: "Ventas",
        section: "sales",
    },
    "sales.purchase-lists": {
        label: "Listas de compra",
        section: "sales",
    },
    "sales.purchase-orders": {
        label: "Seguimiento de Órdenes de compra",
        section: "sales",
    },
    "reports.audits": {
        label: "Reportes de auditoría",
        section: "reports",
    },
    "reports.cash-closures": {
        label: "Reportes de cortes",
        section: "reports",
    },
    "reports.inventory": {
        label: "Reportes de inventario",
        section: "reports",
    },
    "reports.movements": {
        label: "Reportes de movimientos",
        section: "reports",
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
    "system.center": {
        label: "Centro de Administración",
        section: "administration",
    },
    "system.audit": {
        label: "Auditoría y registros",
        section: "administration",
    },
    "system.trash": {
        label: "Papelera global",
        section: "administration",
    },
    "system.access-control": {
        label: "Roles y control de acceso",
        section: "administration",
    },
    "system.configuration": {
        label: "Configuración y herramientas",
        section: "administration",
    },
    "system.monitoring": {
        label: "Monitoreo y estadísticas",
        section: "administration",
    },
    "system.records": {
        label: "Acceso global a registros",
        section: "administration",
    },
};

const permissionLabels = {
    "dashboard.executive.view": "Ver dashboard ejecutivo",

    "employees.view": "Ver registros de empleados",
    "employees.create": "Crear empleados",
    "employees.update": "Editar empleados",
    "employees.delete": "Eliminar empleados",

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
    "attendance.schedule-assignments.delete": "Eliminar asignaciones de horarios",
    "attendance.incidents.view": "Ver incidencias registradas",
    "attendance.incidents.create": "Registrar incidencias",
    "attendance.incidents.update": "Editar incidencias pendientes",
    "attendance.incidents.delete": "Eliminar incidencias pendientes",
    "attendance.incidents.approve": "Aprobar incidencias pendientes",
    "attendance.incidents.reject": "Rechazar incidencias pendientes",

    "departments.view": "Ver registro de departamentos",
    "departments.create": "Crear departamentos",
    "departments.update": "Editar departamentos",
    "departments.delete": "Eliminar departamentos",
    "positions.view": "Ver registro de puestos",
    "positions.create": "Crear puestos",
    "positions.update": "Editar puestos",
    "positions.delete": "Eliminar puestos",

    "users.view": "Ver usuarios",
    "users.create": "Crear usuarios",
    "users.update": "Editar usuarios",
    "users.delete": "Eliminar usuarios",

    "branches.view": "Ver sucursales",
    "branches.create": "Crear sucursales",
    "branches.update": "Editar sucursales",
    "branches.delete": "Eliminar sucursales",

    "sales.view": "Ver ventas registradas",
    "sales.create": "Crear ventas",
    "sales.update": "Editar ventas",
    "sales.delete": "Eliminar ventas",
    "sales.reports": "Ver reportes de ventas",
    "sales.cash-closures.view": "Ver cortes de caja registrados",
    "sales.cash-closures.create": "Crear cortes de caja",
    "sales.cash-closures.update": "Editar cortes de caja",
    "sales.cash-closures.delete": "Eliminar cortes de caja",
    "reports.audits.view": "Ver reportes de auditoría",
    "reports.audits.export.excel": "Exportar reportes de auditoria en Excel",
    "reports.audits.export.pdf": "Exportar reportes de auditoria en PDF",
    "reports.cash-closures.view": "Ver reportes de cortes",
    "reports.cash-closures.create": "Crear cortes desde reportes",
    "reports.cash-closures.update": "Editar cortes desde reportes",
    "reports.cash-closures.delete": "Eliminar cortes desde reportes",
    "reports.inventory.view": "Ver reportes de inventario",
    "reports.inventory.export.excel": "Exportar reportes de inventario en Excel",
    "reports.inventory.export.pdf": "Exportar reportes de inventario en PDF",
    "reports.movements.view": "Ver reportes de movimientos",
    "reports.movements.export.excel": "Exportar reportes de movimientos en Excel",
    "reports.movements.export.pdf": "Exportar reportes de movimientos en PDF",

    "inventory.products.view": "Ver productos registrados",
    "inventory.products.create": "Crear productos",
    "inventory.products.update": "Editar productos",
    "inventory.products.delete": "Eliminar productos",

    "inventory.branches.view": "Ver existencias y movimientos de stock",
    "inventory.branches.stock-in": "Registrar entradas de stock",
    "inventory.branches.stock-out": "Registrar salidas de stock",
    "inventory.branches.stock-adjust": "Registrar ajustes de stock",
    "inventory.branches.batches.update": "Editar lotes de productos",
    "inventory.branches.config.update": "Editar configuración de stock",

    "audits.physical-counts.count": "Capturar conteos de auditoría",
    "audits.physical-counts.view-stock": "Ver stock en auditorías",
    "audits.physical-counts.create": "Crear auditorías",
    "audits.physical-counts.close": "Cerrar auditoría",
    "audits.physical-counts.reopen": "Reabrir auditoría",
    "audits.physical-counts.finalize": "Finalizar auditoría",
    "audits.physical-counts.participants": "Agregar participantes",
    "audits.physical-counts.apply": "Aplicar auditoría",
    "audits.physical-counts.delete": "Eliminar auditorías",

    "sales.purchase-lists.view": "Ver listas de compra",
    "sales.purchase-lists.create": "Crear listas de compra",
    "sales.purchase-lists.update": "Editar listas de compra",
    "sales.purchase-lists.delete": "Eliminar listas de compra",
    "sales.purchase-orders.view": "Ver seguimiento de sus Órdenes de compra",
    "sales.purchase-orders.receive": "Confirmar recepción de sus Órdenes de compra",
    "inventory.purchase-orders.source.view": "Ver Órdenes de compra",
    "inventory.purchase-orders.source.update": "Editar Órdenes de compra",
    "inventory.purchase-orders.source.review": "Revisar Órdenes de compra",
    "inventory.purchase-orders.source.transfer": "Transferir Órdenes de compra",
    "inventory.purchase-orders.general.view": "Ver Órdenes de compra generales",
    "inventory.purchase-orders.general.create": "Crear Órdenes de compra generales",
    "inventory.purchase-orders.general.update": "Editar Órdenes de compra generales",
    "inventory.purchase-orders.general.complete": "Aplicar Órdenes de compra generales",

    "files.export": "Exportar archivos",
    "systems.tickets.view": "Ver configuración actual de tickets",
    "systems.tickets.update": "Editar configuracion de tickets",
    "systems.cash-closure-tickets.view": "Ver configuración actual de tickets de corte",
    "systems.cash-closure-tickets.update": "Editar configuracion de tickets de corte",
    "systems.labels.view": "Ver configuración actual de etiquetas",
    "systems.labels.update": "Editar configuracion de etiquetas",
    "systems.labels.print": "Imprimir etiquetas de productos",

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
    "branches.access-all": "Acceder a todas las sucursales",
};

export function getPermissionModule(permissionName = "") {
    if (permissionName.startsWith("dashboard.")) {
        return "dashboard";
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

    if (permissionName === "system.center.access") {
        return "system.center";
    }

    if (permissionName.startsWith("system.audit.") || permissionName === "system.logs.view") {
        return "system.audit";
    }

    if (permissionName.startsWith("system.trash.")) {
        return "system.trash";
    }

    if (
        permissionName === "system.roles.manage"
        || permissionName === "system.permissions.manage"
        || permissionName === "system.super-administrators.manage"
    ) {
        return "system.access-control";
    }

    if (
        permissionName === "system.settings.manage"
        || permissionName === "system.integrations.manage"
        || permissionName === "system.tools.access"
        || permissionName === "system.maintenance.manage"
    ) {
        return "system.configuration";
    }

    if (permissionName === "system.monitoring.view" || permissionName === "system.statistics.view") {
        return "system.monitoring";
    }

    if (permissionName === "system.records.view-all") {
        return "system.records";
    }

    if (permissionName.startsWith("inventory.products.")) {
        return "inventory.products";
    }

    if (permissionName.startsWith("inventory.branches.")) {
        return "inventory.branches";
    }

    if (permissionName.startsWith("sales.purchase-lists.")) {
        return "sales.purchase-lists";
    }

    if (permissionName === "attendance.view" || permissionName === "attendance.register" || permissionName === "attendance.manage" || permissionName.startsWith("attendance.export.") || permissionName.startsWith("attendance.corrections.")) {
        return "attendance";
    }

    if (permissionName.startsWith("attendance.schedules.")) {
        return "attendance.schedules";
    }

    if (permissionName.startsWith("attendance.schedule-assignments.")) {
        return "attendance.schedule-assignments";
    }

    if (permissionName.startsWith("attendance.incidents.")) {
        return "attendance.incidents";
    }

    if (permissionName.startsWith("departments.") || permissionName.startsWith("positions.")) {
        return "organization-structure";
    }

    if (permissionName.startsWith("sales.purchase-orders.")) {
        return "sales.purchase-orders";
    }

    if (permissionName.startsWith("inventory.purchase-orders.source.")) {
        return "inventory.purchase-orders";
    }

    if (permissionName.startsWith("inventory.purchase-orders.general.")) {
        return "inventory.general-purchase-orders";
    }

    if (permissionName.startsWith("reports.")) {
        const [, reportType] = permissionName.split(".");

        return `reports.${reportType}`;
    }

    if (permissionName.startsWith("audits.")) {
        return "audits";
    }

    return permissionName.split(".")[0]?.toLowerCase();
}

export function auditPermissionCatalog(permissionNames = []) {
    const counts = permissionNames.reduce((result, permissionName) => {
        result[permissionName] = (result[permissionName] || 0) + 1;
        return result;
    }, {});

    return {
        duplicates: Object.entries(counts)
            .filter(([, count]) => count > 1)
            .map(([permissionName]) => permissionName),
        missingLabels: permissionNames.filter((permissionName) => !permissionLabels[permissionName]),
        missingModules: permissionNames.filter((permissionName) => {
            const module = getPermissionModule(permissionName);
            return !permissionModules[module] || !permissionModuleOrder.includes(module);
        }),
    };
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
            permissionName.startsWith("sales.purchase-lists.") ||
            permissionName.startsWith("sales.purchase-orders.") ||
            permissionName.startsWith("inventory.purchase-orders.") ||
            permissionName.startsWith("audits.physical-counts.") ||
            permissionName.startsWith("reports.")
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
