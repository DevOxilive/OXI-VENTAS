export function generateMenu(role, permissions = [], branches = []) {
    const isAdmin = role === "Administrador";

    const can = (permission) => permissions.includes(permission);

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
    if (
        isAdmin ||
        can("employees.view") ||
        can("employees.create") ||
        can("employees.update") ||
        can("employees.delete")
    ) {
        menu.push({
            text: "Capital Humano",
            key: "human-resources",
            icon: "badge",
            url: route("human-resources.employees.index"),
        });
    }

    /*
    |--------------------------------------------------------------------------
    | SISTEMAS
    |--------------------------------------------------------------------------
    */
    if (
        isAdmin ||
        can("users.view") ||
        can("users.create") ||
        can("users.update") ||
        can("users.delete") ||
        can("roles.view") ||
        can("roles.create") ||
        can("roles.update") ||
        can("roles.delete") ||
        can("branches.view") ||
        can("branches.create") ||
        can("branches.update") ||
        can("branches.delete")
    ) {
        menu.push({
            text: "Sistemas",
            key: "systems",
            icon: "settings",
            isOpen: false,
            children: [
                ...(isAdmin ||
                can("users.view") ||
                can("users.create") ||
                can("users.update") ||
                can("users.delete")
                    ? [
                          {
                              text: "Registro de Usuarios",
                              key: "systems.users",
                              icon: "security",
                              url: route("systems.users.index"),
                          },
                      ]
                    : []),

                ...(isAdmin ||
                can("branches.view") ||
                can("branches.create") ||
                can("branches.update") ||
                can("branches.delete")
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
        ...(isAdmin ||
        can("inventory.products.view") ||
        can("inventory.products.create") ||
        can("inventory.products.update") ||
        can("inventory.products.delete")
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

        ...(isAdmin ||
        can("inventory.branches.view") ||
        can("inventory.branches.create") ||
        can("inventory.branches.update") ||
        can("inventory.branches.delete")
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

        // ...(isAdmin ||
        // can("inventory.purchase-reports.view") ||
        // can("inventory.purchase-reports.create") ||
        // can("inventory.purchase-reports.update") ||
        // can("inventory.purchase-reports.delete")
        //     ? [
        //           {
        //               text: "Reporte de compra",
        //               key: `inventory.${branch.slug}.purchase-report`,
        //               icon: "shopping_cart",
        //               url: route("inventory.branches.purchase-reports.index", {
        //                   branch: branch.id,
        //               }),
        //           },
        //       ]
        //     : []),

        ...(isAdmin ||
        can("audits.physical-counts.view") ||
        can("audits.physical-counts.count") ||
        can("audits.physical-counts.create") ||
        can("audits.physical-counts.update") ||
        can("audits.physical-counts.delete")
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
        can("inventory.view") ||
        can("inventory.branches.view")
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
        can("audits.physical-counts.view") ||
        can("audits.physical-counts.count") ||
        can("audits.physical-counts.reports") ||
        can("audits.physical-counts.create") ||
        can("audits.physical-counts.update") ||
        can("audits.physical-counts.delete") ||
        can("inventory.view") ||
        can("inventory.branches.view");

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

    return menu;
}
