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

    const inventoryOptions = (branchKey) => [
        ...(isAdmin ||
        isInventario ||
        can("inventario.dashboard.ver") ||
        can("inventario.ver")
            ? [
                  {
                      text: "Dashboard",
                      key: `inventario.${branchKey}.dashboard`,
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
                      key: `inventario.${branchKey}.productos`,
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
                      text: "Movimientos",
                      key: `inventario.${branchKey}.movimientos`,
                      icon: "sync_alt",
                      url: route("inventario.sucursales", {
                          branch: branchKey,
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
                      key: `inventario.${branchKey}.caducidades`,
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
        //               key: `inventario.${branchKey}.transferencias`,
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
        //               key: `inventario.${branchKey}.ajustes`,
        //               icon: "tune",
        //               url: route("inventario.ajustes"),
        //           },
        //       ]
        //     : []),

        // ...(isAdmin ||
        // isInventario ||
        // can("inventario.reportes.ver") ||
        // can("inventario.ver")
        //     ? [
        //           {
        //               text: "Reportes",
        //               key: `inventario.${branchKey}.reportes`,
        //               icon: "bar_chart",
        //               url: route("inventario.reportes"),
        //           },
        //       ]
        //     : []),
    ];

    const branches = [
        { text: "Ajusco", key: "ajusco", icon: "store" },
        { text: "Diana", key: "diana", icon: "store" },
        { text: "Lago", key: "lago", icon: "store" },
        { text: "Cecilia", key: "cecilia", icon: "store" },
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
                children: inventoryOptions(branch.key),
            })),
        });
    }

    return menu;
}
