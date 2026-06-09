export function generateMenu(role, permissions = [], branches = []) {
    const isAdmin = role === "Administrador";
    const isSystems = role === "Sistemas";
    const isHumanResources = role === "Recursos Humanos";
    const isInventory = role === "Inventario";

    const can = (permission) => permissions.includes(permission);

    const menu = [];

    menu.push({
        text: "Inicio",
        key: "home",
        icon: "dashboard",
        url: route("dashboard"),
    });

    if (isAdmin || isSystems || can("users.view") || can("roles.view")) {
        menu.push({
            text: "Sistemas",
            key: "systems",
            icon: "settings",
            isOpen: false,
            children: [
                ...(isAdmin || isSystems || can("users.view")
                    ? [
                          {
                              text: "Registro de Usuarios",
                              key: "systems.users",
                              icon: "security",
                              url: route("systems.users.index"),
                          },
                          {
                              text: "Registro de Sucursales",
                              key: "branches",
                              icon: "store",
                              url: route("branches.index"),
                          },
                      ]
                    : []),
            ],
        });
    }

    if (isAdmin || isHumanResources || can("employees.view")) {
        menu.push({
            text: "Capital Humano",
            key: "human-resources",
            icon: "badge",
            url: route("rh.empleados"),
        });
    }

    if (
        isAdmin ||
        isSystems ||
        isInventory ||
        can("audits.physical-counts.view")
    ) {
        menu.push({
            text: "Auditorías",
            key: "audits",
            icon: "fact_check",
            isOpen: false,
            children: [
                {
                    text: "Conteo físico",
                    key: "audits.physical-counts",
                    icon: "inventory_2",
                    url: route("audits.physical-counts.index"),
                },
            ],
        });
    }

 const inventoryOptions = (branch) => [
    //  //   ...(isAdmin ||
    //     isInventory ||
    //     can("inventory.dashboard.view") ||
    //     can("inventory.view")
    //         ? [
    //               {
    //                   text: "Dashboard",
    //                   key: `inventory.${branch.slug}.dashboard`,
    //                   icon: "dashboard",
    //                   url: route("inventory.dashboard"),
    //               },
    //           ]
    //         : []),

        ...(isAdmin ||
        isInventory ||
        can("inventory.products.view") ||
        can("inventory.view")
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
        isInventory ||
        can("inventory.branches.view") ||
        can("inventory.view")
            ? [
                  {
                      text: "Inventario",
                      key: `inventory.${branch.slug}.inventory`,
                      icon: "inventory_2",
                      url: route("inventario.branches.inventory", {
                          branch: branch.id,
                      }),
                  },
              ]
            : []),

        ...(isAdmin ||
        isInventory ||
        can("inventory.purchase-reports.view") ||
        can("inventory.view")
            ? [
                  {
                      text: "Reporte de compra",
                      key: `inventory.${branch.slug}.purchase-report`,
                      icon: "shopping_cart",
                      url: route("inventario.branches.purchase-reports.index", {
                          branch: branch.id,
                      }),
                  },
              ]
            : []),

        // ...(isAdmin ||
        // isInventory ||
        // can("inventory.reports.view") ||
        // can("inventory.view")
        //     ? [
        //           {
        //               text: "Reportes",
        //               key: `inventory.${branch.slug}.reports`,
        //               icon: "bar_chart",
        //               url: route("inventario.branches.reports", {
        //                   branch: branch.id,
        //               }),
        //           },
        //       ]
        //     : []),
    ];

    if (isAdmin || isInventory || can("inventory.view")) {
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
                })),
        });
    }

    return menu;
}
