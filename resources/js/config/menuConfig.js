export function generateMenu(role, permissions = [], branches = []) {  

    const isAdmin = role === "Administrador";
    const isSistemas = role === "Sistemas";
    const isRH = role === "Recursos Humanos";
    const isVentas = role === "Ventas";
    const isInventario = role === "Inventario";

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
                      url: route("inventario.dashboard"),
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
                     key: `inventory.${branch.slug}.products`,
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
                      text: "Movimientos",
                      key: `inventory.${branch.slug}.movements`,
                      icon: "sync_alt",
                      url: route("inventario.sucursales", {
                     branch: branch.slug 
                      }),
                  },
              ]
            : []),

        ...(isAdmin ||
        isInventario ||
        can("inventario.caducidades.ver") ||
        can("inventario.ver")
            ? [
                  {
                      text: "Caducidades",
                      key: `inventario.${branch.slug}.caducidades`,
                      icon: "event_busy",
                      url: route("inventario.caducidades"),
                  },
              ]
            : []),

        // ...(isAdmin ||
        // isInventario ||
        // can("inventario.transferencias.ver") ||
        // can("inventario.ver")
        //     ? [
        //           {
        //               text: "Transferencias",
        //               key: `inventario.${branch.key}.transferencias`,
        //               icon: "compare_arrows",
        //               url: route("inventario.transferencias"),
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
        //               key: `inventario.${branch.key}.ajustes`,
        //               icon: "tune",
        //               url: route("inventario.ajustes"),
        //           },
        //       ]
        //     : []),

        ...(isAdmin ||
        isInventario ||
        can("inventario.reports.ver") ||
        can("inventario.ver")
            ? [
                  {
                      text: "Reportes",
                      key: `inventario.${branch.key}.reports`,
                      icon: "bar_chart",
                      url: route("inventario.branches.reports", {
                          branch: branch.id,
                      }),
                  },
              ]
            : []),
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
