export function generateMenu(role, permissions = [], branches = []) {
    const isAdmin = role === "Administrador";

    const can = (permission) => permissions.includes(permission);
    const canAny = (permissionList) => permissionList.some((permission) => can(permission));

    const modulePermissions = {
        employees: ["employees.view", "employees.create", "employees.update", "employees.delete"],
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
            "inventory.branches.create",
            "inventory.branches.update",
            "inventory.branches.delete",
        ],
        purchaseReports: [
            "inventory.purchase-reports.view",
            "inventory.purchase-reports.create",
            "inventory.purchase-reports.update",
            "inventory.purchase-reports.delete",
        ],
        audits: [
            "audits.physical-counts.view",
            "audits.physical-counts.count",
            "audits.physical-counts.reports",
            "audits.physical-counts.view-stock",
            "audits.physical-counts.create",
            "audits.physical-counts.update",
            "audits.physical-counts.delete",
        ],
        sales: ["sales.view", "sales.create", "sales.update", "sales.delete", "sales.reports"],
        cashClosures: [
            "sales.cash-closures.view",
            "sales.cash-closures.create",
            "sales.cash-closures.update",
            "sales.cash-closures.delete",
        ],
        tickets: ["systems.tickets.view", "systems.tickets.update"],
        cashClosureTickets: [
            "systems.cash-closure-tickets.view",
            "systems.cash-closure-tickets.update",
        ],
        labels: ["systems.labels.view", "systems.labels.update"],
        inventoryReports: ["inventory.view", "inventory.create", "inventory.update", "inventory.delete"],
    };

    const canUse = (moduleKey) => isAdmin || canAny(modulePermissions[moduleKey] || []);

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
    if (canUse("employees")) {
        menu.push({
            text: "Capital Humano",
            key: "human-resources",
            icon: "badge",
            isOpen: false,
            children: [
                {
                    text: "Registro de empleados",
                    key: "human-resources.employees",
                    icon: "group",
                    url: route("human-resources.employees.index"),
                },
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

        ...(canUse("purchaseReports")
            ? [
                  {
                      text: "Listas de compra",
                      key: `inventory.${branch.slug}.purchase-report`,
                      icon: "shopping_cart",
                      url: route("inventory.branches.purchase-reports.index", {
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

        ...(isAdmin ||
        can("audits.physical-counts.reports") ||
        can("sales.cash-closures.view") ||
        can("sales.cash-closures.create") ||
        can("inventory.view") ||
        can("inventory.branches.view") ||
        can("inventory.purchase-orders.view") ||
        can("inventory.purchase-orders.create") ||
        can("inventory.purchase-orders.update") ||
        can("inventory.purchase-orders.history")
        ...(canUse("audits") ||
        canUse("cashClosures") ||
        canUse("inventoryReports") ||
        canUse("branchInventory") ||
        canUse("purchaseReports")
            ? [
                  {
                      text: "Reportes",
                      key: `inventory.${branch.slug}.reports`,
                      icon: "bar_chart",
                      url: route("inventory.branches.reports", {
                          branch: branch.id,
                      }),
                  },
              ]
            : []),
    ];

    const canSeeBranchesSection =
        isAdmin ||
        can("inventory.products.view") ||
        can("inventory.products.create") ||
        can("inventory.products.update") ||
        can("inventory.products.delete") ||
        can("inventory.branches.view") ||
        can("inventory.branches.create") ||
        can("inventory.branches.update") ||
        can("inventory.branches.delete") ||
        can("inventory.purchase-reports.view") ||
        can("inventory.purchase-reports.create") ||
        can("inventory.purchase-reports.update") ||
        can("inventory.purchase-reports.delete") ||
        can("inventory.purchase-orders.view") ||
        can("inventory.purchase-orders.create") ||
        can("inventory.purchase-orders.update") ||
        can("inventory.purchase-orders.history") ||
        can("audits.physical-counts.view") ||
        can("audits.physical-counts.count") ||
        can("audits.physical-counts.reports") ||
        can("audits.physical-counts.create") ||
        can("audits.physical-counts.update") ||
        can("audits.physical-counts.delete") ||
        can("sales.cash-closures.view") ||
        can("sales.cash-closures.create") ||
        can("inventory.view") ||
        can("inventory.branches.view");
        canUse("products") ||
        canUse("branchInventory") ||
        canUse("purchaseReports") ||
        canUse("audits") ||
        canUse("cashClosures") ||
        canUse("inventoryReports");

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

    if (
        canUse("sales") ||
        canUse("cashClosures")
    ) {
        menu.push({
            text: "Ventas",
            key: "sales",
            icon: "point_of_sale",
            isOpen: false,
            children: [
                {
                    text: "Punto de venta",
                    key: "sales.pos",
                    icon: "point_of_sale",
                    url: route("ventas.home"),
                },
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
            ],
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

    return menu;
}
