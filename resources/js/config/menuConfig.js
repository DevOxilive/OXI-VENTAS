export function generateMenu(role, permissions = []) {
    const can = (permiso) => permissions.includes(permiso);

    const isAdmin = role === "Administrador";
    const isSistemas = role === "Sistemas";
    const isRH = role === "Recursos Humanos";
    const isVentas = role === "Ventas";
    const isInventario = role === "Inventario";

    const menu = [];

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD GENERAL
    |--------------------------------------------------------------------------
    */
    menu.push({
        text: "Inicio",
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
            icon: "settings",
            isOpen: true,

            children: [
                ...(isAdmin || isSistemas || can("usuarios.ver")
                    ? [
                          {
                              text: "Registro de Usuarios",
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
            icon: "badge",
            url: route("rh.empleados"),
        });
    }

    /*
    |--------------------------------------------------------------------------
    | VENTAS
    |--------------------------------------------------------------------------
    */
    if (isAdmin || isVentas || can("ventas.ver")) {
        menu.push({
            text: "Ventas",
            icon: "paid",
            url: route("ventas.home"),
        });
    }

    /*
|--------------------------------------------------------------------------
| INVENTARIO
|--------------------------------------------------------------------------
*/
    if (isAdmin || isInventario || can("inventario.ver")) {
        menu.push({
            text: "Inventario",
            icon: "inventory_2",
            isOpen: true,

            children: [
                ...(isAdmin || isInventario || can("inventario.dashboard.ver")
                    ? [
                          {
                              text: "Dashboard",
                              icon: "dashboard",
                              url: route("inventario.dashboard"),
                          },
                      ]
                    : []),

                ...(isAdmin || isInventario || can("inventario.productos.ver")
                    ? [
                          {
                              text: "Productos",
                              icon: "inventory",
                              url: route("inventario.productos"),
                          },
                      ]
                    : []),

                ...(isAdmin || isInventario || can("inventario.movimientos.ver")
                    ? [
                          {
                              text: "Movimientos",
                              icon: "sync_alt",
                              url: route("inventario.movimientos"),
                          },
                      ]
                    : []),

                ...(isAdmin || isInventario || can("inventario.caducidades.ver")
                    ? [
                          {
                              text: "Caducidades",
                              icon: "event_busy",
                              url: route("inventario.caducidades"),
                          },
                      ]
                    : []),

                ...(isAdmin ||
                isInventario ||
                can("inventario.transferencias.ver")
                    ? [
                          {
                              text: "Transferencias",
                              icon: "compare_arrows",
                              url: route("inventario.transferencias"),
                          },
                      ]
                    : []),

                ...(isAdmin || isInventario || can("inventario.ajustes.ver")
                    ? [
                          {
                              text: "Ajustes",
                              icon: "tune",
                              url: route("inventario.ajustes"),
                          },
                      ]
                    : []),

                ...(isAdmin || isInventario || can("inventario.reportes.ver")
                    ? [
                          {
                              text: "Reportes",
                              icon: "bar_chart",
                              url: route("inventario.reportes"),
                          },
                      ]
                    : []),
            ],
        });
    }

    return menu;
}
