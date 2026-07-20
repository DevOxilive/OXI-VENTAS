<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'

import AdminLayout from '@/Layouts/AdminLayout.vue'
import PageLayout from '@/Layouts/PageLayout.vue'
import { usePermissions } from '@/Composables/usePermissions'

defineOptions({ layout: AdminLayout })

const props = defineProps({
    dashboardUser: {
        type: Object,
        default: () => ({
            name: '',
            role: 'Usuario',
        }),
    },
    assignedBranches: {
        type: Array,
        default: () => [],
    },
})

const page = usePage()
const { canAny, permissions } = usePermissions()
let userChannel = null
let handleUserChanged = null

const roleName = computed(() => props.dashboardUser?.role || 'Usuario')
const userName = computed(() => props.dashboardUser?.name || 'Equipo')
const firstBranch = computed(() => props.assignedBranches?.[0] || null)

const roleKey = computed(() => {
    const normalized = roleName.value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')

    if (normalized.includes('venta')) return 'sales'
    if (normalized.includes('inventario')) return 'inventory'
    if (normalized.includes('sistema')) return 'systems'
    if (normalized.includes('humano')) return 'human'

    return 'general'
})

const roleProfiles = {
    sales: {
        title: 'Lista para vender con ritmo',
        subtitle: 'Tu punto de venta, cortes y tickets estan listos para operar sin perder el paso.',
        accent: 'Ventas',
        icon: 'point_of_sale',
        scene: 'sale',
        statLabel: 'Sucursal activa',
        statValue: () => firstBranch.value?.name || 'Selecciona punto de venta',
    },
    inventory: {
        title: 'Inventario bajo control',
        subtitle: 'Cuenta, revisa stock y manten cada sucursal ordenada desde tu panel de trabajo.',
        accent: 'Inventario',
        icon: 'inventory_2',
        scene: 'inventory',
        statLabel: 'Sucursales',
        statValue: () => props.assignedBranches.length || 'Sin asignar',
    },
    systems: {
        title: 'Sistema listo para administrar',
        subtitle: 'Usuarios, permisos, tickets e impresoras quedan a un clic para mantener todo fino.',
        accent: 'Sistemas',
        icon: 'settings',
        scene: 'systems',
        statLabel: 'Herramientas',
        statValue: () => 'Configuracion',
    },
    human: {
        title: 'Equipo bien acompanado',
        subtitle: 'Gestiona accesos y registros del personal con una vista limpia para avanzar rapido.',
        accent: 'Capital Humano',
        icon: 'badge',
        scene: 'human',
        statLabel: 'Area',
        statValue: () => 'Personal',
    },
    general: {
        title: 'Tu espacio de trabajo esta listo',
        subtitle: 'Aqui tienes un inicio ligero con accesos directos a lo que tu rol puede usar.',
        accent: 'Inicio',
        icon: 'dashboard',
        scene: 'general',
        statLabel: 'Rol',
        statValue: () => roleName.value,
    },
}

const profile = computed(() => roleProfiles[roleKey.value] || roleProfiles.general)

const assignedBranchesSummary = computed(() => {
    if (!props.assignedBranches.length) {
        return ''
    }

    const visibleNames = props.assignedBranches
        .slice(0, 2)
        .map((branch) => branch.name)
        .join(', ')
    const remaining = props.assignedBranches.length - 2

    return remaining > 0 ? `${visibleNames} +${remaining}` : visibleNames
})

const heroMetaCards = computed(() => [
    {
        label: profile.value.statLabel,
        value: profile.value.statValue(),
    },
    {
        label: 'Rol',
        value: roleName.value,
    },
    ...(assignedBranchesSummary.value
        ? [
            {
                label: 'Sucursales asignadas',
                value: assignedBranchesSummary.value,
            },
        ]
        : []),
])

const permissionLabelMap = {
    'employees.view': 'Ver empleados',
    'employees.create': 'Crear empleados',
    'employees.update': 'Editar empleados',
    'employees.delete': 'Eliminar empleados',
    'users.view': 'Ver usuarios',
    'users.create': 'Crear usuarios',
    'users.update': 'Editar usuarios',
    'users.delete': 'Eliminar usuarios',
    'branches.view': 'Ver sucursales',
    'branches.create': 'Crear sucursales',
    'branches.update': 'Editar sucursales',
    'branches.delete': 'Eliminar sucursales',
    'sales.view': 'Ver ventas',
    'sales.create': 'Crear ventas',
    'sales.update': 'Editar ventas',
    'sales.delete': 'Eliminar ventas',
    'sales.reports': 'Reportes de ventas',
    'sales.cash-closures.view': 'Ver cortes',
    'sales.cash-closures.create': 'Crear cortes',
    'sales.cash-closures.update': 'Editar cortes',
    'sales.cash-closures.delete': 'Eliminar cortes',
    'inventory.products.view': 'Ver productos',
    'inventory.products.create': 'Crear productos',
    'inventory.products.update': 'Editar productos',
    'inventory.products.delete': 'Eliminar productos',
    'inventory.branches.view': 'Ver stock',
    'inventory.branches.create': 'Entradas',
    'inventory.branches.update': 'Salidas y ajustes',
    'inventory.branches.delete': 'Eliminar stock',
    'inventory.purchase-reports.view': 'Ver compras',
    'inventory.purchase-reports.create': 'Crear compras',
    'inventory.purchase-reports.update': 'Editar compras',
    'inventory.purchase-reports.delete': 'Eliminar compras',
    'audits.physical-counts.view': 'Ver auditorias',
    'audits.physical-counts.count': 'Capturar conteos',
    'audits.physical-counts.reports': 'Reportes auditoria',
    'audits.physical-counts.view-stock': 'Ver stock',
    'audits.physical-counts.create': 'Crear auditorias',
    'audits.physical-counts.update': 'Editar auditorias',
    'audits.physical-counts.delete': 'Eliminar auditorias',
    'inventory.view': 'Reportes inventario',
    'inventory.create': 'Crear reportes',
    'inventory.update': 'Editar reportes',
    'inventory.delete': 'Eliminar reportes',
    'systems.tickets.view': 'Ver tickets',
    'systems.tickets.update': 'Editar tickets',
    'systems.cash-closure-tickets.view': 'Ver tickets de corte',
    'systems.cash-closure-tickets.update': 'Editar tickets de corte',
    'systems.labels.view': 'Ver etiquetas',
    'systems.labels.update': 'Editar etiquetas',
    'files.export': 'Exportar archivos',
}

const permissionCatalog = [
    {
        key: 'sales',
        title: 'Ventas',
        icon: 'point_of_sale',
        permissions: ['sales.view', 'sales.create', 'sales.update', 'sales.delete', 'sales.reports'],
    },
    {
        key: 'cashClosures',
        title: 'Cortes de caja',
        icon: 'payments',
        permissions: ['sales.cash-closures.view', 'sales.cash-closures.create', 'sales.cash-closures.update', 'sales.cash-closures.delete'],
    },
    {
        key: 'printers',
        title: 'Impresoras',
        icon: 'print',
        permissions: ['systems.tickets.view', 'systems.tickets.update', 'systems.cash-closure-tickets.view', 'systems.cash-closure-tickets.update', 'systems.labels.view', 'systems.labels.update'],
    },
    {
        key: 'users',
        title: 'Usuarios',
        icon: 'manage_accounts',
        permissions: ['users.view', 'users.create', 'users.update', 'users.delete'],
    },
    {
        key: 'branches',
        title: 'Sucursales',
        icon: 'store',
        permissions: ['branches.view', 'branches.create', 'branches.update', 'branches.delete'],
    },
    {
        key: 'inventory',
        title: 'Inventario',
        icon: 'inventory_2',
        permissions: ['inventory.products.view', 'inventory.products.create', 'inventory.products.update', 'inventory.products.delete', 'inventory.branches.view', 'inventory.branches.create', 'inventory.branches.update', 'inventory.branches.delete'],
    },
    {
        key: 'audits',
        title: 'Auditorias',
        icon: 'fact_check',
        permissions: ['audits.physical-counts.view', 'audits.physical-counts.count', 'audits.physical-counts.reports', 'audits.physical-counts.view-stock', 'audits.physical-counts.create', 'audits.physical-counts.update', 'audits.physical-counts.delete'],
    },
    {
        key: 'purchases',
        title: 'Listas de compra',
        icon: 'shopping_cart',
        permissions: ['inventory.purchase-reports.view', 'inventory.purchase-reports.create', 'inventory.purchase-reports.update', 'inventory.purchase-reports.delete'],
    },
    {
        key: 'human',
        title: 'Capital Humano',
        icon: 'badge',
        permissions: ['employees.view', 'employees.create', 'employees.update', 'employees.delete'],
    },
    {
        key: 'reports',
        title: 'Reportes y archivos',
        icon: 'bar_chart',
        permissions: ['inventory.view', 'inventory.create', 'inventory.update', 'inventory.delete', 'files.export'],
    },
]

const permissionAction = (permission) => {
    const first = firstBranch.value

    const branchHref = (resolver) => (first ? resolver(first) : null)

    const actionMap = {
        'employees.view': { icon: 'visibility', href: route('human-resources.employees.index') },
        'employees.create': { icon: 'add', href: route('human-resources.employees.index') },
        'employees.update': { icon: 'edit', href: route('human-resources.employees.index') },
        'employees.delete': { icon: 'delete', href: route('human-resources.employees.index') },
        'users.view': { icon: 'visibility', href: route('systems.users.index') },
        'users.create': { icon: 'person_add', href: route('systems.users.index') },
        'users.update': { icon: 'edit', href: route('systems.users.index') },
        'users.delete': { icon: 'delete', href: route('systems.users.index') },
        'branches.view': { icon: 'visibility', href: route('branches.index') },
        'branches.create': { icon: 'add_business', href: route('branches.index') },
        'branches.update': { icon: 'edit_location', href: route('branches.index') },
        'branches.delete': { icon: 'delete', href: route('branches.index') },
        'sales.view': { icon: 'visibility', href: route('ventas.home') },
        'sales.create': { icon: 'add_shopping_cart', href: route('ventas.home') },
        'sales.update': { icon: 'edit', href: route('ventas.home') },
        'sales.delete': { icon: 'delete', href: route('ventas.home') },
        'sales.reports': { icon: 'bar_chart', href: route('ventas.home') },
        'sales.cash-closures.view': { icon: 'visibility', href: route('ventas.cash-closures.index') },
        'sales.cash-closures.create': { icon: 'add_card', href: route('ventas.cash-closures.index') },
        'sales.cash-closures.update': { icon: 'edit', href: route('ventas.cash-closures.index') },
        'sales.cash-closures.delete': { icon: 'delete', href: route('ventas.cash-closures.index') },
        'inventory.products.view': { icon: 'visibility', href: branchHref((branch) => route('inventory.branches.products.index', { branch: branch.slug })) },
        'inventory.products.create': { icon: 'add_box', href: branchHref((branch) => route('inventory.branches.products.index', { branch: branch.slug })) },
        'inventory.products.update': { icon: 'edit_square', href: branchHref((branch) => route('inventory.branches.products.index', { branch: branch.slug })) },
        'inventory.products.delete': { icon: 'delete', href: branchHref((branch) => route('inventory.branches.products.index', { branch: branch.slug })) },
        'inventory.branches.view': { icon: 'visibility', href: branchHref((branch) => route('inventory.branches.inventory', { branch: branch.id })) },
        'inventory.branches.create': { icon: 'input', href: branchHref((branch) => route('inventory.branches.inventory', { branch: branch.id })) },
        'inventory.branches.update': { icon: 'sync_alt', href: branchHref((branch) => route('inventory.branches.inventory', { branch: branch.id })) },
        'inventory.branches.delete': { icon: 'delete', href: branchHref((branch) => route('inventory.branches.inventory', { branch: branch.id })) },
        'inventory.purchase-reports.view': { icon: 'visibility', href: branchHref((branch) => route('inventory.branches.purchase-reports.index', { branch: branch.id })) },
        'inventory.purchase-reports.create': { icon: 'add_shopping_cart', href: branchHref((branch) => route('inventory.branches.purchase-reports.index', { branch: branch.id })) },
        'inventory.purchase-reports.update': { icon: 'edit', href: branchHref((branch) => route('inventory.branches.purchase-reports.index', { branch: branch.id })) },
        'inventory.purchase-reports.delete': { icon: 'delete', href: branchHref((branch) => route('inventory.branches.purchase-reports.index', { branch: branch.id })) },
        'audits.physical-counts.view': { icon: 'visibility', href: branchHref((branch) => route('audits.physical-counts.index', { branch: branch.slug })) },
        'audits.physical-counts.count': { icon: 'checklist', href: branchHref((branch) => route('audits.physical-counts.index', { branch: branch.slug })) },
        'audits.physical-counts.reports': { icon: 'bar_chart', href: branchHref((branch) => route('audits.physical-counts.reports', { branch: branch.slug })) },
        'audits.physical-counts.view-stock': { icon: 'inventory_2', href: branchHref((branch) => route('audits.physical-counts.index', { branch: branch.slug })) },
        'audits.physical-counts.create': { icon: 'add_task', href: branchHref((branch) => route('audits.physical-counts.index', { branch: branch.slug })) },
        'audits.physical-counts.update': { icon: 'edit_note', href: branchHref((branch) => route('audits.physical-counts.index', { branch: branch.slug })) },
        'audits.physical-counts.delete': { icon: 'delete', href: branchHref((branch) => route('audits.physical-counts.index', { branch: branch.slug })) },
        'inventory.view': { icon: 'bar_chart', href: branchHref((branch) => route('inventory.branches.reports', { branch: branch.id })) },
        'inventory.create': { icon: 'add_chart', href: branchHref((branch) => route('inventory.branches.reports', { branch: branch.id })) },
        'inventory.update': { icon: 'edit', href: branchHref((branch) => route('inventory.branches.reports', { branch: branch.id })) },
        'inventory.delete': { icon: 'delete', href: branchHref((branch) => route('inventory.branches.reports', { branch: branch.id })) },
        'systems.tickets.view': { icon: 'visibility', href: route('printers.tickets.index') },
        'systems.tickets.update': { icon: 'edit', href: route('printers.tickets.index') },
        'systems.cash-closure-tickets.view': { icon: 'visibility', href: route('printers.cash-closure-tickets.index') },
        'systems.cash-closure-tickets.update': { icon: 'edit', href: route('printers.cash-closure-tickets.index') },
        'systems.labels.view': { icon: 'visibility', href: route('printers.labels.index') },
        'systems.labels.update': { icon: 'edit', href: route('printers.labels.index') },
        'files.export': { icon: 'download', href: branchHref((branch) => route('inventory.branches.reports', { branch: branch.id })) },
    }

    const action = actionMap[permission] || { icon: 'arrow_forward', href: null }

    return {
        key: permission,
        label: permissionLabelMap[permission] || permission,
        icon: action.icon,
        href: action.href,
    }
}

const permissionGroups = computed(() =>
    permissionCatalog
        .map((group) => ({
            ...group,
            activePermissions: group.permissions
                .filter((permission) => permissions.value.includes(permission))
                .map((permission) => permissionAction(permission)),
        }))
        .filter((group) => group.activePermissions.length)
)

const allQuickActions = computed(() => [
    {
        id: 'pos',
        label: 'Punto de venta',
        description: 'Abrir venta y cobrar productos.',
        icon: 'point_of_sale',
        routeName: 'ventas.home',
        visible: canAny(['sales.view', 'sales.create', 'sales.update', 'sales.delete', 'sales.reports']),
    },
    {
        id: 'cash-closure',
        label: 'Corte de caja',
        description: 'Cuadrar efectivo y tarjeta por caja.',
        icon: 'payments',
        routeName: 'ventas.cash-closures.index',
        visible: canAny([
            'sales.cash-closures.view',
            'sales.cash-closures.create',
            'sales.cash-closures.update',
            'sales.cash-closures.delete',
        ]),
    },
    {
        id: 'products',
        label: 'Productos',
        description: 'Administrar catalogo y stock base.',
        icon: 'inventory',
        href: firstBranch.value
            ? route('inventory.branches.products.index', { branch: firstBranch.value.slug })
            : null,
        visible: Boolean(firstBranch.value) && canAny([
            'inventory.products.view',
            'inventory.products.create',
            'inventory.products.update',
            'inventory.products.delete',
        ]),
    },
    {
        id: 'inventory',
        label: 'Inventario',
        description: 'Entradas, salidas y movimientos.',
        icon: 'inventory_2',
        href: firstBranch.value
            ? route('inventory.branches.inventory', { branch: firstBranch.value.id })
            : null,
        visible: Boolean(firstBranch.value) && canAny([
            'inventory.branches.view',
            'inventory.branches.create',
            'inventory.branches.update',
            'inventory.branches.delete',
        ]),
    },
    {
        id: 'audits',
        label: 'Auditorias',
        description: 'Conteos fisicos y validaciones.',
        icon: 'fact_check',
        href: firstBranch.value
            ? route('audits.physical-counts.index', { branch: firstBranch.value.slug })
            : null,
        visible: Boolean(firstBranch.value) && canAny([
            'audits.physical-counts.view',
            'audits.physical-counts.count',
            'audits.physical-counts.create',
            'audits.physical-counts.update',
            'audits.physical-counts.delete',
        ]),
    },
    {
        id: 'users',
        label: 'Usuarios',
        description: 'Roles, permisos y accesos.',
        icon: 'manage_accounts',
        routeName: 'systems.users.index',
        visible: canAny(['users.view', 'users.create', 'users.update', 'users.delete']),
    },
    {
        id: 'tickets',
        label: 'Tickets',
        description: 'Plantillas de venta y corte.',
        icon: 'receipt_long',
        routeName: canAny(['systems.cash-closure-tickets.view', 'systems.cash-closure-tickets.update'])
            ? 'printers.cash-closure-tickets.index'
            : 'printers.tickets.index',
        visible: canAny([
            'systems.tickets.view',
            'systems.tickets.update',
            'systems.cash-closure-tickets.view',
            'systems.cash-closure-tickets.update',
        ]),
    },
    {
        id: 'employees',
        label: 'Empleados',
        description: 'Registro del personal.',
        icon: 'badge',
        routeName: 'human-resources.employees.index',
        visible: canAny(['employees.view', 'employees.create', 'employees.update', 'employees.delete']),
    },
])

const quickActions = computed(() =>
    allQuickActions.value
        .filter((action) => action.visible)
        .filter((action) => !['products', 'inventory', 'audits'].includes(action.id))
        .slice(0, 6)
        .map((action) => ({
            ...action,
            href: action.href || route(action.routeName),
        }))
)

onMounted(() => {
    if (!window.Echo || !page.props.auth?.user?.id) {
        return
    }

    handleUserChanged = () => {
        router.reload({
            preserveScroll: true,
            preserveState: false,
        })
    }

    userChannel = window.Echo.channel(`users.${page.props.auth.user.id}`)
        .listen('.UserChanged', handleUserChanged)
})

onBeforeUnmount(() => {
    if (userChannel && handleUserChanged) {
        userChannel.stopListening('.UserChanged', handleUserChanged)
    }
})
</script>

<template>
    <Head title="Inicio" />

    <PageLayout>
        <section class="role-dashboard-shell">
            <div class="hero-panel">
                <div class="hero-copy">
                    <div class="role-pill">
                        <span class="material-symbols-outlined">{{ profile.icon }}</span>
                        {{ profile.accent }}
                    </div>

                    <h1>Bienvenida, {{ userName }}</h1>
                    <p class="hero-subtitle">{{ profile.title }}</p>
                    <p class="hero-description">{{ profile.subtitle }}</p>

                    <div class="hero-meta">
                        <div
                            v-for="meta in heroMetaCards"
                            :key="meta.label"
                        >
                            <span>{{ meta.label }}</span>
                            <strong>{{ meta.value }}</strong>
                        </div>
                    </div>
                </div>

                <div class="mascot-stage" :class="`scene-${profile.scene}`">
                    <span class="confetti confetti-one"></span>
                    <span class="confetti confetti-two"></span>
                    <span class="confetti confetti-three"></span>
                    <span class="confetti confetti-four"></span>

                    <div class="mascot-orbit">
                        <div class="mascot-card">
                            <img src="/icons/super-kay-source.png" alt="Super Kay" class="mascot-logo">
                        </div>
                    </div>

                    <div class="scene-prop prop-primary">
                        <span class="material-symbols-outlined scene-sale">point_of_sale</span>
                        <span class="material-symbols-outlined scene-inventory">inventory_2</span>
                        <span class="material-symbols-outlined scene-systems">desktop_windows</span>
                        <span class="material-symbols-outlined scene-human">badge</span>
                        <span class="material-symbols-outlined scene-general">waving_hand</span>
                    </div>

                    <div class="scene-prop prop-secondary">
                        <span class="material-symbols-outlined scene-sale">credit_score</span>
                        <span class="material-symbols-outlined scene-inventory">fact_check</span>
                        <span class="material-symbols-outlined scene-systems">terminal</span>
                        <span class="material-symbols-outlined scene-human">groups</span>
                        <span class="material-symbols-outlined scene-general">celebration</span>
                    </div>

                    <div class="scene-prop prop-tertiary">
                        <span class="material-symbols-outlined scene-sale">receipt_long</span>
                        <span class="material-symbols-outlined scene-inventory">qr_code_scanner</span>
                        <span class="material-symbols-outlined scene-systems">memory</span>
                        <span class="material-symbols-outlined scene-human">how_to_reg</span>
                        <span class="material-symbols-outlined scene-general">bolt</span>
                    </div>

                    <div class="scene-prop prop-quaternary">
                        <span class="material-symbols-outlined scene-sale">payments</span>
                        <span class="material-symbols-outlined scene-inventory">checklist</span>
                        <span class="material-symbols-outlined scene-systems">code</span>
                        <span class="material-symbols-outlined scene-human">workspace_premium</span>
                        <span class="material-symbols-outlined scene-general">star</span>
                    </div>

                    <div class="stage-floor"></div>
                </div>
            </div>

            <div class="content-grid">
                <section class="quick-actions-panel">
                    <div class="section-heading">
                        <div>
                            <p>Permisos</p>
                            <h2>Accesos por modulo</h2>
                        </div>
                    </div>

                    <div v-if="permissionGroups.length" class="permission-grid">
                        <details
                            v-for="group in permissionGroups"
                            :key="group.key"
                            class="permission-card"
                        >
                            <summary class="permission-card-header">
                                <span class="material-symbols-outlined">{{ group.icon }}</span>
                                <strong>{{ group.title }}</strong>
                                <span class="material-symbols-outlined chevron">keyboard_arrow_down</span>
                            </summary>

                            <div class="permission-chip-list">
                                <Link
                                    v-for="permission in group.activePermissions.filter((item) => item.href)"
                                    :key="permission.key"
                                    :href="permission.href"
                                    class="permission-chip"
                                >
                                    <span class="material-symbols-outlined">{{ permission.icon }}</span>
                                    <strong>{{ permission.label }}</strong>
                                    <span class="material-symbols-outlined arrow">arrow_forward</span>
                                </Link>
                                <div
                                    v-for="permission in group.activePermissions.filter((item) => !item.href)"
                                    :key="permission.key"
                                    class="permission-chip is-disabled"
                                >
                                    <span class="material-symbols-outlined">{{ permission.icon }}</span>
                                    <strong>{{ permission.label }}</strong>
                                    <span class="material-symbols-outlined arrow">lock</span>
                                </div>
                            </div>
                        </details>
                    </div>

                    <div v-else class="empty-actions">
                        <span class="material-symbols-outlined">lock</span>
                        <p>Tu rol aun no tiene permisos activos asignados.</p>
                    </div>

                    <div v-if="quickActions.length" class="global-access">
                        <div class="section-heading compact-heading">
                            <div>
                                <p>Herramientas</p>
                                <h2>Accesos de tu rol</h2>
                            </div>
                        </div>

                        <div class="quick-action-grid">
                            <Link
                                v-for="action in quickActions"
                                :key="action.id"
                                :href="action.href"
                                class="quick-action-card"
                            >
                                <span class="material-symbols-outlined">{{ action.icon }}</span>
                                <div>
                                    <strong>{{ action.label }}</strong>
                                    <small>{{ action.description }}</small>
                                </div>
                                <span class="material-symbols-outlined arrow">arrow_forward</span>
                            </Link>
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </PageLayout>
</template>

<style scoped>
.role-dashboard-shell {
    display: grid;
    gap: 1rem;
}

.hero-panel {
    align-items: stretch;
    background:
        radial-gradient(circle at 78% 18%, color-mix(in srgb, var(--primary) 12%, transparent), transparent 24rem),
        radial-gradient(circle at 12% 0%, color-mix(in srgb, var(--primary) 9%, transparent), transparent 18rem),
        linear-gradient(135deg, var(--background) 0%, color-mix(in srgb, var(--secondary) 74%, var(--background)) 58%, var(--background) 100%);
    border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--secondary));
    border-radius: 1.5rem;
    box-shadow: 0 22px 58px color-mix(in srgb, var(--text) 10%, transparent);
    display: grid;
    gap: 1.5rem;
    grid-template-columns: minmax(0, 1.05fr) minmax(320px, 0.95fr);
    min-height: 27rem;
    overflow: hidden;
    padding: clamp(1.25rem, 3vw, 2.25rem);
    position: relative;
}

:global(.dark) .hero-panel {
    background:
        radial-gradient(circle at 78% 18%, color-mix(in srgb, var(--primary) 26%, transparent), transparent 24rem),
        radial-gradient(circle at 12% 0%, rgba(255, 60, 74, 0.16), transparent 18rem),
        linear-gradient(135deg, #120001 0%, #050101 58%, #100003 100%);
    border-color: rgba(230, 0, 18, 0.28);
    box-shadow: 0 28px 70px rgba(0, 0, 0, 0.3);
}

.hero-copy {
    align-self: center;
    max-width: 43rem;
    position: relative;
    z-index: 2;
}

.role-pill {
    align-items: center;
    background: color-mix(in srgb, var(--primary) 10%, var(--background));
    border: 1px solid color-mix(in srgb, var(--primary) 28%, var(--secondary));
    border-radius: 999px;
    color: var(--primary);
    display: inline-flex;
    font-size: 0.78rem;
    font-weight: 800;
    gap: 0.45rem;
    letter-spacing: 0.08em;
    padding: 0.55rem 0.9rem;
    text-transform: uppercase;
}

.role-pill .material-symbols-outlined {
    font-size: 1.15rem;
}

h1 {
    color: var(--text);
    font-size: clamp(2.1rem, 5vw, 4.7rem);
    font-weight: 950;
    letter-spacing: 0;
    line-height: 0.98;
    margin-top: 1.15rem;
}

.hero-subtitle {
    color: var(--text);
    font-size: clamp(1.15rem, 2vw, 1.65rem);
    font-weight: 850;
    margin-top: 1rem;
}

.hero-description {
    color: color-mix(in srgb, var(--text) 72%, transparent);
    font-size: 1rem;
    line-height: 1.7;
    margin-top: 0.7rem;
    max-width: 35rem;
}

.hero-meta {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    margin-top: 1.4rem;
    max-width: 46rem;
}

.hero-meta div {
    background: color-mix(in srgb, var(--secondary) 62%, transparent);
    border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--secondary));
    border-radius: 1rem;
    padding: 0.95rem 1rem;
}

.hero-meta span {
    color: color-mix(in srgb, var(--text) 52%, transparent);
    display: block;
    font-size: 0.72rem;
    font-weight: 800;
    letter-spacing: 0.13em;
    text-transform: uppercase;
}

.hero-meta strong {
    color: var(--text);
    display: block;
    font-size: 1.05rem;
    margin-top: 0.35rem;
}

:global(.dark) .role-pill {
    background: rgba(230, 0, 18, 0.14);
    border-color: rgba(255, 45, 62, 0.34);
    color: #ff4b58;
}

:global(.dark) h1,
:global(.dark) .hero-subtitle,
:global(.dark) .hero-meta strong {
    color: #ffffff;
}

:global(.dark) .hero-description {
    color: rgba(255, 255, 255, 0.72);
}

:global(.dark) .hero-meta div {
    background: rgba(255, 255, 255, 0.045);
    border-color: rgba(255, 45, 62, 0.24);
}

:global(.dark) .hero-meta span {
    color: rgba(255, 255, 255, 0.52);
}

.mascot-stage {
    align-items: center;
    display: flex;
    justify-content: center;
    min-height: 22rem;
    position: relative;
}

.mascot-stage::before {
    background: radial-gradient(circle, color-mix(in srgb, var(--primary) 14%, transparent), transparent 68%);
    border-radius: 999px;
    content: "";
    height: min(90%, 28rem);
    position: absolute;
    width: min(90%, 28rem);
}

.mascot-orbit {
    animation: mascot-float 4s ease-in-out infinite;
    position: relative;
    z-index: 3;
}

.mascot-card {
    align-items: center;
    background: var(--background);
    border: 1px solid color-mix(in srgb, var(--primary) 22%, var(--secondary));
    border-radius: 2rem;
    box-shadow:
        0 28px 70px color-mix(in srgb, var(--text) 16%, transparent),
        0 0 56px color-mix(in srgb, var(--primary) 14%, transparent);
    display: flex;
    height: clamp(11rem, 22vw, 15rem);
    justify-content: center;
    overflow: visible;
    padding: 1.2rem;
    position: relative;
    transform: rotate(-4deg);
    width: clamp(11rem, 22vw, 15rem);
}

.mascot-logo {
    animation: mascot-party 1.4s ease-in-out infinite;
    border-radius: 1.4rem;
    box-shadow: 0 18px 34px rgba(0, 0, 0, 0.36);
    height: 100%;
    object-fit: contain;
    width: 100%;
}

.scene-prop {
    align-items: center;
    background: color-mix(in srgb, var(--secondary) 80%, var(--background));
    border: 1px solid color-mix(in srgb, var(--primary) 24%, var(--secondary));
    border-radius: 1.35rem;
    box-shadow:
        0 20px 48px color-mix(in srgb, var(--text) 14%, transparent),
        inset 0 0 24px color-mix(in srgb, var(--primary) 8%, transparent);
    color: var(--primary);
    display: flex;
    height: 4.8rem;
    justify-content: center;
    position: absolute;
    width: 4.8rem;
    z-index: 4;
}

:global(.dark) .mascot-card {
    background: #0b0304;
    border-color: rgba(255, 45, 62, 0.34);
    box-shadow:
        0 28px 70px rgba(0, 0, 0, 0.46),
        0 0 56px rgba(230, 0, 18, 0.18);
}

:global(.dark) .scene-prop {
    background: rgba(25, 2, 5, 0.84);
    border-color: rgba(255, 45, 62, 0.32);
    box-shadow:
        0 20px 48px rgba(0, 0, 0, 0.34),
        inset 0 0 24px rgba(230, 0, 18, 0.08);
    color: #ff2d3d;
}

.scene-prop .material-symbols-outlined {
    display: none;
    font-size: 2.35rem;
}

.prop-primary {
    animation: prop-pop 3.2s ease-in-out infinite;
    right: 8%;
    top: 13%;
}

.prop-secondary {
    animation: prop-pop 3.2s 0.55s ease-in-out infinite;
    bottom: 16%;
    left: 7%;
}

.prop-tertiary {
    animation: prop-pop 3.2s 0.95s ease-in-out infinite;
    left: 18%;
    top: 21%;
}

.prop-quaternary {
    animation: prop-pop 3.2s 1.35s ease-in-out infinite;
    bottom: 27%;
    right: 18%;
}

.scene-sale .scene-sale,
.scene-inventory .scene-inventory,
.scene-systems .scene-systems,
.scene-human .scene-human,
.scene-general .scene-general {
    display: block;
}

.confetti {
    animation: confetti-fall 3.8s linear infinite;
    background: var(--primary);
    border-radius: 999px;
    display: block;
    height: 0.65rem;
    position: absolute;
    width: 0.65rem;
}

.confetti-one {
    left: 12%;
    top: 10%;
}

.confetti-two {
    animation-delay: 0.6s;
    background: var(--accent);
    right: 19%;
    top: 4%;
}

.confetti-three {
    animation-delay: 1.1s;
    bottom: 22%;
    right: 9%;
}

.confetti-four {
    animation-delay: 1.7s;
    background: var(--accent);
    bottom: 14%;
    left: 24%;
}

.stage-floor {
    background: rgba(230, 0, 18, 0.34);
    border-radius: 999px;
    bottom: 7%;
    filter: blur(3px);
    height: 1.1rem;
    opacity: 0.85;
    position: absolute;
    width: min(70%, 24rem);
}

.content-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: minmax(0, 1fr);
}

.quick-actions-panel,
.branch-panel {
    background: var(--background);
    border: 1px solid var(--secondary);
    border-radius: 1.25rem;
    padding: 1.1rem;
}

.compact-heading {
    margin-bottom: 0.8rem;
    margin-top: 1.2rem;
}

.section-heading {
    align-items: center;
    display: flex;
    justify-content: space-between;
    margin-bottom: 1rem;
}

.section-heading p {
    color: color-mix(in srgb, var(--text) 56%, transparent);
    font-size: 0.72rem;
    font-weight: 850;
    letter-spacing: 0.13em;
    text-transform: uppercase;
}

.section-heading h2 {
    color: var(--text);
    font-size: 1.25rem;
    font-weight: 900;
    margin-top: 0.2rem;
}

.quick-action-grid {
    display: grid;
    gap: 0.75rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.global-access {
    margin-top: 0.35rem;
}

.quick-action-card {
    align-items: center;
    background: var(--secondary);
    border: 1px solid color-mix(in srgb, var(--primary) 12%, var(--secondary));
    border-radius: 1rem;
    color: var(--text);
    display: grid;
    gap: 0.75rem;
    grid-template-columns: auto minmax(0, 1fr) auto;
    min-height: 6rem;
    padding: 0.95rem;
    text-decoration: none;
    transition: border-color 0.2s ease, transform 0.2s ease, background-color 0.2s ease;
}

.quick-action-card:hover {
    background: var(--background);
    border-color: var(--primary);
    transform: translateY(-2px);
}

.quick-action-card > .material-symbols-outlined:first-child {
    align-items: center;
    background: color-mix(in srgb, var(--primary) 10%, var(--background));
    border-radius: 0.9rem;
    color: var(--primary);
    display: flex;
    font-size: 1.65rem;
    height: 2.8rem;
    justify-content: center;
    width: 2.8rem;
}

.quick-action-card strong,
.quick-action-card small {
    display: block;
    min-width: 0;
}

.quick-action-card strong {
    font-size: 0.98rem;
    font-weight: 850;
}

.quick-action-card small {
    color: color-mix(in srgb, var(--text) 62%, transparent);
    font-size: 0.78rem;
    line-height: 1.35;
    margin-top: 0.25rem;
}

.quick-action-card .arrow {
    color: color-mix(in srgb, var(--text) 42%, transparent);
    font-size: 1.2rem;
}

.permission-grid {
    align-items: start;
    display: grid;
    gap: 0.9rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.permission-card {
    align-self: start;
    background:
        linear-gradient(135deg, rgba(230, 0, 18, 0.08), transparent 48%),
        var(--secondary);
    border: 1px solid color-mix(in srgb, var(--primary) 20%, var(--secondary));
    border-radius: 1.05rem;
    min-height: 0;
    overflow: hidden;
    padding: 0;
}

.permission-card-header {
    align-items: center;
    color: var(--text);
    cursor: pointer;
    display: flex;
    gap: 0.8rem;
    list-style: none;
    min-height: 4.3rem;
    padding: 1rem;
}

.permission-card-header::-webkit-details-marker {
    display: none;
}

.permission-card-header .material-symbols-outlined {
    align-items: center;
    background: color-mix(in srgb, var(--primary) 12%, var(--background));
    border-radius: 0.85rem;
    color: var(--primary);
    display: flex;
    font-size: 1.3rem;
    height: 2.4rem;
    justify-content: center;
    width: 2.4rem;
}

.permission-card-header strong {
    display: block;
    font-size: 1rem;
    font-weight: 900;
    min-width: 0;
}

.permission-card-header .chevron {
    background: transparent;
    color: color-mix(in srgb, var(--text) 58%, transparent);
    font-size: 1.45rem;
    height: auto;
    margin-left: auto;
    transition: transform 0.2s ease;
    width: auto;
}

.permission-card[open] .permission-card-header .chevron {
    transform: rotate(180deg);
}

.permission-chip-list {
    display: grid;
    gap: 0.45rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    padding: 0 1rem 1rem;
}

.permission-chip {
    align-items: center;
    background:
        linear-gradient(135deg, color-mix(in srgb, var(--primary) 8%, transparent), transparent 70%),
        var(--background);
    border: 1px solid color-mix(in srgb, var(--primary) 18%, var(--secondary));
    border-radius: 0.85rem;
    color: color-mix(in srgb, var(--text) 82%, transparent);
    display: grid;
    gap: 0.45rem;
    grid-template-columns: auto minmax(0, 1fr) auto;
    min-height: 3rem;
    padding: 0.5rem;
    text-decoration: none;
    transition: border-color 0.2s ease, transform 0.2s ease, background-color 0.2s ease;
}

.permission-chip:hover {
    background: color-mix(in srgb, var(--primary) 8%, var(--background));
    border-color: var(--primary);
    transform: translateY(-2px);
}

.permission-chip > .material-symbols-outlined:first-child {
    align-items: center;
    background: color-mix(in srgb, var(--primary) 12%, var(--secondary));
    border-radius: 0.75rem;
    color: var(--primary);
    display: flex;
    font-size: 1.15rem;
    height: 2rem;
    justify-content: center;
    width: 2rem;
}

.permission-chip strong {
    color: var(--text);
    font-size: 0.76rem;
    font-weight: 850;
    line-height: 1.2;
    min-width: 0;
}

.permission-chip .arrow {
    color: color-mix(in srgb, var(--text) 48%, transparent);
    font-size: 1.1rem;
}

.permission-chip.is-disabled {
    cursor: not-allowed;
    opacity: 0.58;
}

.permission-chip.is-disabled:hover {
    background: var(--background);
    border-color: color-mix(in srgb, var(--primary) 18%, var(--secondary));
    transform: none;
}

.empty-actions {
    align-items: center;
    background: var(--secondary);
    border: 1px dashed color-mix(in srgb, var(--text) 20%, transparent);
    border-radius: 1rem;
    color: color-mix(in srgb, var(--text) 62%, transparent);
    display: flex;
    gap: 0.7rem;
    min-height: 7rem;
    padding: 1rem;
}

.empty-actions.compact {
    min-height: 5rem;
}

@keyframes mascot-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-12px);
    }
}

@keyframes mascot-party {
    0%,
    100% {
        transform: rotate(-2deg) scale(1);
    }
    50% {
        transform: rotate(3deg) scale(1.035);
    }
}

@keyframes prop-pop {
    0%,
    100% {
        transform: translateY(0) rotate(0deg);
    }
    50% {
        transform: translateY(-10px) rotate(4deg);
    }
}

@keyframes confetti-fall {
    0% {
        opacity: 0;
        transform: translateY(-18px) rotate(0deg);
    }
    20% {
        opacity: 1;
    }
    100% {
        opacity: 0;
        transform: translateY(130px) rotate(220deg);
    }
}

@media (max-width: 1180px) {
    .hero-panel {
        grid-template-columns: 1fr;
    }

    .quick-action-grid,
    .permission-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .hero-panel {
        min-height: auto;
    }

    .hero-meta,
    .quick-action-grid,
    .permission-grid {
        grid-template-columns: 1fr;
    }

    .mascot-stage {
        min-height: 18rem;
    }

    .scene-prop {
        height: 4rem;
        width: 4rem;
    }
}
</style>
