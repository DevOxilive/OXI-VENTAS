export function generateMenu(role, permissions = []) {
    const can = (permiso) => permissions.includes(permiso);

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
                      key: `inventario.${branch.key}.dashboard`,
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
                      key: `inventario.${branch.key}.productos`,
                      icon: "inventory",
                      url: route("inventario.productos"),
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
                      key: `inventario.${branch.key}.inventory`,
                      icon: "inventory_2",
                      url: route("inventario.branches.inventory", {
                          branch: branch.id,
                      }),
                  },
              ]
            : []),

        // ...(isAdmin ||
        // isInventario ||
        // can("inventario.caducidades.ver") ||
        // can("inventario.ver")
        //     ? [
        //           {
        //               text: "Caducidades",
        //               key: `inventario.${branch.key}.caducidades`,
        //               icon: "event_busy",
        //               url: route("inventario.caducidades"),
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

    const branches = [
        { id: 1, text: "Ajusco", key: "ajusco", icon: "store" },
        { id: 2, text: "Diana", key: "diana", icon: "store" },
        { id: 3, text: "Lago", key: "lago", icon: "store" },
        { id: 4, text: "Cecilia", key: "cecilia", icon: "store" },
    ];

    if (isAdmin || isInventario || can("inventario.ver")) {
        menu.push({
            text: "Sucursales",
            key: "sucursales",
            icon: "inventory_2",
            isOpen: false,
            children: branches.map((branch) => ({
                text: branch.text,
                key: `branch.${branch.key}`,
                icon: branch.icon,
                isOpen: false,
                children: inventoryOptions(branch),
            })),
        });
    }

    return menu;
}
