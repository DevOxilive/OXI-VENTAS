export function generateMenu(role, permissions = [], branches = []) {
    const can = (permission) => permissions.includes(permission);
    const canAny = (permissionList) => permissionList.some((permission) => can(permission));
    const canRegisterSalesAttendance =
        ["Ventas", "Vendedor"].includes(role) && can("attendance.register");

    const modulePermissions = {
        employees: ["employees.view", "employees.create", "employees.update", "employees.delete"],
        organizationStructure: ["departments.view", "departments.create", "departments.update", "departments.delete", "positions.view", "positions.create", "positions.update", "positions.delete"],
        users: ["users.view", "users.create", "users.update", "users.delete"],
        branches: ["branches.view", "branches.create", "branches.update", "branches.delete"],
        products: [
            "inventory.products.view",
            "inventory.products.create",
            "inventory.products.update",
            "inventory.products.delete",
        ],
        branchInventory: [
            "inventory.branches.view",
            "inventory.branches.stock-in",
            "inventory.branches.stock-out",
            "inventory.branches.stock-adjust",
            "inventory.branches.batches.update",
            "inventory.branches.config.update",
        ],
        purchaseReports: [
            "sales.purchase-lists.view",
            "sales.purchase-lists.create",
            "sales.purchase-lists.update",
            "sales.purchase-lists.delete",
            "sales.purchase-orders.view",
            "sales.purchase-orders.receive",
        ],
        purchaseOrders: [
            "inventory.purchase-orders.source.view",
            "inventory.purchase-orders.source.update",
            "inventory.purchase-orders.source.review",
            "inventory.purchase-orders.source.transfer",
            "inventory.purchase-orders.general.view",
            "inventory.purchase-orders.general.create",
            "inventory.purchase-orders.general.update",
            "inventory.purchase-orders.general.complete",
        ],
        audits: [
            "audits.physical-counts.count",
            "audits.physical-counts.view-stock",
            "audits.physical-counts.create",
            "audits.physical-counts.close",
            "audits.physical-counts.reopen",
            "audits.physical-counts.finalize",
            "audits.physical-counts.participants",
            "audits.physical-counts.apply",
            "audits.physical-counts.delete",
        ],
        sales: ["sales.view", "sales.create", "sales.update", "sales.delete"],
        cashClosures: [
            "sales.cash-closures.view",
            "sales.cash-closures.create",
            "sales.cash-closures.update",
            "sales.cash-closures.delete",
        ],
        salesReports: ["reports.sales.view"],
        auditReports: ["reports.audits.view"],
        cashClosureReports: ["reports.cash-closures.view"],
        inventoryReports: ["reports.inventory.view"],
        movementReports: ["reports.movements.view"],
        tickets: ["systems.tickets.view", "systems.tickets.update"],
        cashClosureTickets: [
            "systems.cash-closure-tickets.view",
            "systems.cash-closure-tickets.update",
        ],
        labels: ["systems.labels.view", "systems.labels.update", "systems.labels.print"],
        attendance: [
            "attendance.view",
            "attendance.register",
            "attendance.export.excel",
            "attendance.export.pdf",
        ],
        attendanceSchedules: ["attendance.schedules.view", "attendance.schedules.create", "attendance.schedules.update", "attendance.schedules.delete"],
        attendanceScheduleAssignments: ["attendance.schedule-assignments.view", "attendance.schedule-assignments.create", "attendance.schedule-assignments.update", "attendance.schedule-assignments.delete"],
        attendanceIncidents: ["attendance.incidents.view", "attendance.incidents.create", "attendance.incidents.update", "attendance.incidents.delete", "attendance.incidents.approve", "attendance.incidents.reject"],
        systemAdministration: ["system.center.access"],
    };

    const canUse = (moduleKey) => canAny(modulePermissions[moduleKey] || []);

    const menu = [];

    menu.push({
        text: "Inicio",
        key: "home",
        icon: "dashboard",
        url: route("dashboard"),
    });

    /*
    |--------------------------------------------------------------------------
    | CAPITAL HUMANO
    |--------------------------------------------------------------------------
    */
    if (canUse("employees") || canUse("organizationStructure") || can("attendance.view") || can("attendance.export.excel") || can("attendance.export.pdf") || canUse("attendanceSchedules") || canUse("attendanceScheduleAssignments") || canUse("attendanceIncidents")) {
        menu.push({
            text: "Capital Humano",
            key: "human-resources",
            icon: "badge",
            isOpen: false,
            children: [
                ...(canUse("employees")
                    ? [{
                        text: "Registro de empleados",
                        key: "human-resources.employees",
                        icon: "group",
                        url: route("human-resources.employees.index"),
                    }]
                    : []),
                ...(canUse("organizationStructure")
                    ? [{
                        text: "Registro de Departamentos",
                        key: "human-resources.departments",
                        icon: "account_tree",
                        url: route("human-resources.departments.index"),
                    }]
                    : []),
                ...(canUse("attendanceSchedules") ? [{ text: "Horarios", key: "human-resources.attendance-schedules", icon: "schedule", url: route("human-resources.attendance-schedules.index") }] : []),
                ...(canUse("attendanceScheduleAssignments") ? [{ text: "Asignación de horarios", key: "human-resources.attendance-schedule-assignments", icon: "assignment_ind", url: route("human-resources.attendance-schedule-assignments.index") }] : []),
                ...(can("attendance.view") || can("attendance.export.excel") || can("attendance.export.pdf")
                    ? [{
                        text: "Asistencias",
                        key: "human-resources.attendance",
                        icon: "fact_check",
                        url: route("human-resources.attendance.index"),
                    }]
                    : []),
                ...(canUse("attendanceIncidents") ? [{ text: "Incidencias", key: "human-resources.attendance-incidents", icon: "event_note", url: route("human-resources.attendance-incidents.index") }] : []),
            ],
        });
    }

    /*
    |--------------------------------------------------------------------------
    | SISTEMAS
    |--------------------------------------------------------------------------
    */
    if (canUse("users") || canUse("branches")) {
        menu.push({
            text: "Sistemas",
            key: "systems",
            icon: "settings",
            isOpen: false,
            children: [
                ...(canUse("users")
                    ? [
                          {
                              text: "Registro de Usuarios",
                              key: "systems.users",
                              icon: "security",
                              url: route("systems.users.index"),
                          },
                      ]
                    : []),

                ...(canUse("branches")
                    ? [
                          {
                              text: "Registro de Sucursales",
                              key: "systems.branches",
                              icon: "store",
                              url: route("branches.index"),
                          },
                      ]
                    : []),
            ],
        });
    }

    /*
    |--------------------------------------------------------------------------
    | SUCURSALES / INVENTARIO / AUDITORÍAS POR SUCURSAL
    |--------------------------------------------------------------------------
    */
    const inventoryOptions = (branch) => [
        ...(canUse("products")
            ? [
                  {
                      text: "Productos",
                      key: `inventory.${branch.slug}.products`,
                      icon: "inventory",
                      url: route("inventory.branches.products.index", {
                          branch: branch.slug,
                      }),
                  },
              ]
            : []),

        ...(canUse("branchInventory")
            ? [
                  {
                      text: "Inventario",
                      key: `inventory.${branch.slug}.inventory`,
                      icon: "inventory_2",
                      url: route("inventory.branches.inventory", {
                          branch: branch.id,
                      }),
                  },
              ]
            : []),

        ...(canUse("purchaseOrders")
            ? [
                  {
                      text: "Órdenes de compra generales",
                      key: `inventory.${branch.slug}.general-purchase-orders`,
                      icon: "receipt_long",
                      url: route("inventory.branches.reports.purchase-orders", {
                          branch: branch.id,
                      }),
                  },
              ]
            : []),

        ...(canUse("audits")
            ? [
                  {
                      text: "Auditorías",
                      key: `inventory.${branch.slug}.physical-counts`,
                      icon: "fact_check",
                      url: route("audits.physical-counts.index", {
                          branch: branch.slug,
                      }),
                  },
              ]
            : []),

    ];

    const canSeeBranchesSection =
        can("inventory.products.view") ||
        can("inventory.products.create") ||
        can("inventory.products.update") ||
        can("inventory.products.delete") ||
        can("inventory.branches.view") ||
        can("inventory.branches.stock-in") ||
        can("inventory.branches.stock-out") ||
        can("inventory.branches.stock-adjust") ||
        can("inventory.branches.batches.update") ||
        can("inventory.branches.config.update") ||
        canUse("purchaseOrders") ||
        can("audits.physical-counts.count") ||
        can("audits.physical-counts.view-stock") ||
        can("audits.physical-counts.create") ||
        can("audits.physical-counts.close") ||
        can("audits.physical-counts.reopen") ||
        can("audits.physical-counts.finalize") ||
        can("audits.physical-counts.participants") ||
        can("audits.physical-counts.apply") ||
        can("audits.physical-counts.delete") ||
        can("inventory.branches.view") ||
        canUse("products") ||
        canUse("branchInventory") ||
        canUse("purchaseOrders") ||
        canUse("audits");

    if (canSeeBranchesSection) {
        menu.push({
            text: "Sucursales",
            key: "branches",
            icon: "inventory_2",
            isOpen: false,
            children: branches
                .filter((branch) => branch.slug)
                .map((branch) => ({
                    text: branch.name,
                    key: branch.slug,
                    slug: branch.slug,
                    color: branch.color,
                    bgColor: branch.color,
                    icon: "store",
                    isBranch: true,
                    isOpen: false,
                    children: inventoryOptions(branch),
                }))
                .filter((branchItem) => branchItem.children.length),
        });
    }

    const purchaseListsMenuItem = {
        text: "Lista de compra",
        key: "sales.purchase-lists",
        icon: "shopping_cart",
        url: route("ventas.purchase-reports.index"),
    };
    const branchPurchaseOrdersMenuItem = {
        text: "Órdenes de compra",
        key: "sales.purchase-orders",
        icon: "shopping_bag",
        url: route("ventas.purchase-orders.index"),
    };
    const canUsePurchaseLists = canAny([
        "sales.purchase-lists.view",
        "sales.purchase-lists.create",
        "sales.purchase-lists.update",
        "sales.purchase-lists.delete",
    ]);
    const canUsePurchaseOrderTracking = canAny([
        "sales.purchase-orders.view",
        "sales.purchase-orders.receive",
    ]);

    if (
        canUse("sales") ||
        canUse("cashClosures") ||
        canUsePurchaseLists ||
        canUsePurchaseOrderTracking ||
        canRegisterSalesAttendance
    ) {
        menu.push({
            text: "Ventas",
            key: "sales",
            icon: "point_of_sale",
            isOpen: false,
            children: [
                ...(canUse("sales")
                    ? [{
                        text: "Punto de venta",
                        key: "sales.pos",
                        icon: "point_of_sale",
                        url: route("ventas.home"),
                    }]
                    : []),
                ...(canUse("cashClosures")
                    ? [
                          {
                              text: "Corte de caja",
                              key: "sales.cash-closures",
                              icon: "payments",
                              url: route("ventas.cash-closures.index"),
                          },
                      ]
                    : []),
                ...(canUsePurchaseLists
                    ? [purchaseListsMenuItem]
                    : []),
                ...(canUsePurchaseOrderTracking
                    ? [branchPurchaseOrdersMenuItem]
                    : []),
                ...(canRegisterSalesAttendance
                    ? [{
                        text: "Asistencia",
                        key: "sales.attendance",
                        icon: "fact_check",
                        url: route("ventas.attendance.index"),
                    }]
                    : []),
            ],
        });
    }

    const reportMenuItems = [
        ...(canUse("salesReports")
            ? [{
                text: "Reportes de ventas",
                key: "reports.sales",
                icon: "monitoring",
                url: route("inventory.reports.sales"),
            }]
            : []),
        ...(canUse("cashClosureReports")
            ? [{
                text: "Reportes de cortes",
                key: "reports.cash-closures",
                icon: "summarize",
                url: route("inventory.reports.select", { report: "cash-closures" }),
            }]
            : []),
        ...(canUse("auditReports")
            ? [{
                text: "Reportes de auditoría",
                key: "reports.audits",
                icon: "fact_check",
                url: route("inventory.reports.select", { report: "audits" }),
            }]
            : []),
        ...(canUse("inventoryReports")
            ? [{
                text: "Reportes de inventario",
                key: "reports.inventory",
                icon: "inventory_2",
                url: route("inventory.reports.select", { report: "inventory" }),
            }]
            : []),
        ...(canUse("movementReports")
            ? [{
                text: "Reportes de movimientos",
                key: "reports.movements",
                icon: "sync_alt",
                url: route("inventory.reports.select", { report: "movements" }),
            }]
            : []),
    ];

    if (reportMenuItems.length) {
        menu.push({
            text: "Reportes",
            key: "reports",
            icon: "bar_chart",
            isOpen: false,
            children: reportMenuItems,
        });
    }

    if (
        canUse("tickets") ||
        canUse("cashClosureTickets") ||
        canUse("labels")
    ) {
        menu.push({
            text: "Impresoras",
            key: "printers",
            icon: "print",
            isOpen: false,
            children: [
                ...(canUse("tickets")
                    ? [
                          {
                              text: "Tickets",
                              key: "printers.tickets",
                              icon: "receipt_long",
                              url: route("printers.tickets.index"),
                          },
                      ]
                    : []),
                ...(canUse("cashClosureTickets")
                    ? [
                          {
                              text: "Tickets de corte",
                              key: "printers.cash-closure-tickets",
                              icon: "payments",
                              url: route("printers.cash-closure-tickets.index"),
                          },
                      ]
                    : []),
                ...(canUse("labels")
                    ? [
                          {
                              text: "Etiquetas",
                              key: "printers.labels",
                              icon: "barcode",
                              url: route("printers.labels.index"),
                          },
                      ]
                    : []),
            ],
        });
    }

    if (canUse("systemAdministration")) {
        menu.push({
            text: "Centro de Administración",
            key: "system-administration",
            icon: "admin_panel_settings",
            url: route("system-administration.index"),
        });
    }

    return menu;
}
