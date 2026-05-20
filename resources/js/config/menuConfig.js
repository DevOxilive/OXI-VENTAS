export function generateMenu(role, permissions = []) {
    const can = (permiso) => permissions.includes(permiso)

    const isAdmin = role === 'Administrador'
    const isSistemas = role === 'Sistemas'
    const isRH = role === 'Recursos Humanos'
    const isVentas = role === 'Ventas'
    const isInventario = role === 'Inventario'

    const menu = []

    menu.push({
        text: 'Inicio',
        icon: 'dashboard',
        url: route('dashboard'),
    })

    if (isAdmin || isSistemas || can('usuarios.ver') || can('roles.ver')) {
        menu.push({
            text: 'Sistemas',
            icon: 'settings',
            isOpen: false,
            children: [
                ...(isAdmin || isSistemas || can('usuarios.ver')
                    ? [{
                        text: 'Registro de Usuarios',
                        icon: 'security',
                        url: route('sistemas.empleados'),
                    }]
                    : []),
            ],
        })
    }

    if (isAdmin || isRH || can('empleados.ver')) {
        menu.push({
            text: 'Capital Humano',
            icon: 'badge',
            url: route('rh.empleados'),
        })
    }

    if (isAdmin || isInventario || can('inventario.ver')) {
        menu.push({
            text: 'Inventario',
            icon: 'inventory_2',
            isOpen: false,
            children: [
                {
                    text: 'Dashboard',
                    icon: 'dashboard',
                    url: route('inventory.dashboard'),
                },
                {
                    text: 'Productos',
                    icon: 'inventory',
                    url: route('inventory.products.index'),
                },
                {
                    text: 'Movimientos',
                    icon: 'sync_alt',
                    url: route('inventory.movements'),
                },
                {
                    text: 'Caducidades',
                    icon: 'event_busy',
                    url: route('inventory.expirations'),
                },
                {
                    text: 'Transferencias',
                    icon: 'compare_arrows',
                    url: route('inventory.transfers'),
                },
                {
                    text: 'Ajustes',
                    icon: 'tune',
                    url: route('inventory.adjustments'),
                },
                {
                    text: 'Reportes',
                    icon: 'bar_chart',
                    url: route('inventory.reports'),
                },
            ],
        })
    }

    return menu
}