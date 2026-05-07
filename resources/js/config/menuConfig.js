export function generateMenu(role) {
    const isAdmin = role === 'Administrador'
    const isSistemas = role === 'Sistemas'
    const isRH = role === 'Recursos Humanos'
    const isVentas = role === 'Ventas'
    const isInventario = role === 'Inventario'

    const menu = []

    /*
    |--------------------------------------------------------------------------
    | DASHBOARD GENERAL
    |--------------------------------------------------------------------------
    */
    menu.push({
        text: 'Inicio',
        icon: 'dashboard',
        url: route('dashboard')
    })

    /*
    |--------------------------------------------------------------------------
    | SISTEMAS
    |--------------------------------------------------------------------------
    */
    if (isAdmin || isSistemas) {
        menu.push({
            text: 'Sistemas',
            icon: 'settings',
            children: [
                {
                    text: 'Roles del Sistema',
                    icon: 'security',
                    url: route('sistemas.roles')
                },
                {
                    text: 'Usuarios del Sistema',
                    icon: 'group',
                    url: route('sistemas.usuarios')
                }
            ]
        })
    }

    /*
    |--------------------------------------------------------------------------
    | RECURSOS HUMANOS
    |--------------------------------------------------------------------------
    */
    if (isAdmin || isRH) {
        menu.push({
            text: 'Capital Humano',
            icon: 'badge',
            url: route('rh.empleados')
        })
    }

    /*
    |--------------------------------------------------------------------------
    | VENTAS
    |--------------------------------------------------------------------------
    */
    if (isAdmin || isVentas) {
        menu.push({
            text: 'Ventas',
            icon: 'paid',
            url: route('ventas.home')
        })
    }

    /*
    |--------------------------------------------------------------------------
    | INVENTARIO
    |--------------------------------------------------------------------------
    */
    if (isAdmin || isInventario) {
        menu.push({
            text: 'Inventario',
            icon: 'inventory_2',
            url: route('inventario.home')
        })
    }

    return menu
}