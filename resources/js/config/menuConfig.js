export function generateMenu(role, permissions = [], branches = []) {
    const isAdmin = role === "Administrador";
    const isSistemas = role === "Sistemas";
    const isRH = role === "Recursos Humanos";
    const isInventario = role === "Inventario";

    const can = (permission) => permissions.includes(permission);

    const menu = [];

    menu.push({
        text: "Inicio",
        key: "inicio",
        icon: "dashboard",
        url: route("dashboard"),
    });

    /*
    |--------------------------------------------------------------------------
    | SISTEMAS
    |--------------------------------------------------------------------------
    */

    if (isAdmin || isSistemas || can("usuarios.ver") || can("roles.ver")) {
        menu.push({
            text: "Sistemas",
            key: "sistemas",
            icon: "settings",
            isOpen: false,
            children: [
                ...(isAdmin || isSistemas || can("usuarios.ver")
                    ? [
                          {
                              text: "Registro de Usuarios",
                              key: "sistemas.empleados",
                              icon: "security",
                              url: route("sistemas.empleados"),
                          },
                      ]
                    : []),
            ],
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RECURSOS HUMANOS
    |--------------------------------------------------------------------------
    */

    if (isAdmin || isRH || can("empleados.ver")) {
        menu.push({
            text: "Capital Humano",
            key: "capital-humano",
            icon: "badge",
            url: route("rh.empleados"),
        });
    }

    /*
    |--------------------------------------------------------------------------
    | INVENTARIO
    |--------------------------------------------------------------------------
    */

    const inventoryOptions = (branch) => [
        ...(isAdmin ||
        isInventario ||
        can("inventario.dashboard.ver") ||
        can("inventario.ver")
            ? [
                  {
                      text: "Dashboard",
                      key: `inventario.${branch.slug}.dashboard`,
                      icon: "dashboard",
                      url: route("inventory.dashboard"),
                  },
              ]
            : []),

        ...(isAdmin ||
        isInventario ||
        can("inventario.productos.ver") ||
        can("inventario.ver")
            ? [
                  {
                      text: "Productos",
                      key: `inventario.${branch.slug}.products`,
                      icon: "inventory",
                      url: route("inventory.branches.products.index", {
                          branch: branch.slug,
                      }),
                  },
              ]
            : []),

        ...(isAdmin ||
        isInventario ||
        can("inventario.sucursales.ver") ||
        can("inventario.ver")
            ? [
                  {
                      text: "Inventario",
                      key: `inventario.${branch.slug}.inventory`,
                      icon: "inventory_2",
                      url: route("inventario.branches.inventory", {
                          branch: branch.id,
                      }),
                  },
              ]
            : []),

        ...(isAdmin ||
        isInventario ||
        can("inventario.reports.ver") ||
        can("inventario.ver")
            ? [
                  {
                      text: "Reportes",
                      key: `inventario.${branch.slug}.reports`,
                      icon: "bar_chart",
                      url: route("inventario.branches.reports", {
                          branch: branch.id,
                      }),
                  },
              ]
            : []),

        /*
        |--------------------------------------------------------------------------
        | RUTAS COMENTADAS / PENDIENTES
        |--------------------------------------------------------------------------
        */

        // ...(isAdmin ||
        // isInventario ||
        // can("inventario.movimientos.ver") ||
        // can("inventario.ver")
        //     ? [
        //           {
        //               text: "Movimientos",
        //               key: `inventario.${branch.slug}.movements`,
        //               icon: "sync_alt",
        //               url: route("inventario.branches.movements", {
        //                   branch: branch.id,
        //               }),
        //           },
        //       ]
        //     : []),

        // ...(isAdmin ||
        // isInventario ||
        // can("inventario.caducidades.ver") ||
        // can("inventario.ver")
        //     ? [
        //           {
        //               text: "Caducidades",
        //               key: `inventario.${branch.slug}.expirations`,
        //               icon: "event_busy",
        //               url: route("inventario.branches.expirations", {
        //                   branch: branch.id,
        //               }),
        //           },
        //       ]
        //     : []),

        // ...(isAdmin ||
        // isInventario ||
        // can("inventario.transferencias.ver") ||
        // can("inventario.ver")
        //     ? [
        //           {
        //               text: "Transferencias",
        //               key: `inventario.${branch.slug}.transfers`,
        //               icon: "compare_arrows",
        //               url: route("inventario.branches.transfers", {
        //                   branch: branch.id,
        //               }),
        //           },
        //       ]
        //     : []),

        // ...(isAdmin ||
        // isInventario ||
        // can("inventario.ajustes.ver") ||
        // can("inventario.ver")
        //     ? [
        //           {
        //               text: "Ajustes",
        //               key: `inventario.${branch.slug}.adjustments`,
        //               icon: "tune",
        //               url: route("inventario.branches.adjustments", {
        //                   branch: branch.id,
        //               }),
        //           },
        //       ]
        //     : []),
    ];

    if (isAdmin || isInventario || can("inventario.ver")) {
        menu.push({
            text: "Sucursales",
            key: "sucursales",
            icon: "inventory_2",
            isOpen: false,
            children: branches.map((branch) => ({
                text: branch.name,
                key: `branch.${branch.slug}`,
                icon: "store",
                isOpen: false,
                children: inventoryOptions(branch),
            })),
        });
    }

    return menu;
}
