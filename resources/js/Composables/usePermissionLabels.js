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
    "users",
    "branches",
    "files",
    "inventory.products",
    "inventory.branches",
    "inventory.purchase-reports",
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
        label: "Reporte de compra",
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

    "employees.view": "Ver modulo de empleados",
    "employees.create": "Crear empleados",
    "employees.update": "Editar empleados",
    "employees.delete": "Eliminar empleados",

    "users.view": "Ver modulo de usuarios",
    "users.create": "Crear usuarios",
    "users.update": "Editar usuarios",
    "users.delete": "Eliminar usuarios",

    "branches.view": "Ver modulo de sucursales",
    "branches.create": "Crear sucursales",
    "branches.update": "Editar sucursales",
    "branches.delete": "Eliminar sucursales",

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
    if (roleName === "Administrador") {
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
        return permissionLabels[permissionName] || permissionName;
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
