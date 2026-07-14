export function getPhysicalCountReportsToolbarConfig({
    branch,
    form,
    branches = [],
    audits = [],
    users = [],
    categories = [],
} = {}) {
    const selectedAudit = audits.find((audit) => String(audit.id) === String(form.physical_count_id))
    const participantOptions = selectedAudit
        ? selectedAudit.participants || []
        : audits.flatMap((audit) => audit.participants || [])
    const scopedUsers = form.user_scope === 'participants'
        ? uniqueUsers(participantOptions)
        : users

    return {
        title: 'Reportes de auditoría',
        subtitle: branch?.name
            ? `Sucursal ${branch.name}`
            : 'Todas las sucursales accesibles',
        backButton: true,
        backLabel: 'Centro de reportes',
        showSearch: false,
        showRecordsPerPage: true,
        showCounter: false,
        filters: [
            {
                key: 'branch',
                label: 'Sucursal',
                placeholder: 'Todas las sucursales',
                value: form.branch,
                options: branches,
                optionLabel: 'name',
                optionValue: 'slug',
            },
            {
                key: 'physical_count_id',
                label: 'Auditoría',
                placeholder: 'Todas las auditorías',
                value: form.physical_count_id,
                options: audits.map((audit) => ({
                    label: `${audit.name} - ${audit.folio}`,
                    value: audit.id,
                })),
            },
            {
                key: 'user_scope',
                label: 'Lista usuarios',
                placeholder: 'Participantes',
                value: form.user_scope,
                options: [
                    { label: 'Participantes de auditoría', value: 'participants' },
                    { label: 'Todos los usuarios', value: 'all' },
                ],
            },
            {
                key: 'user_ids',
                label: 'Usuario',
                placeholder: 'Todos los usuarios',
                type: 'multiselect',
                value: form.user_ids,
                options: scopedUsers,
                optionLabel: 'name',
                optionValue: 'id',
            },
            {
                key: 'category_id',
                label: 'Categoría',
                placeholder: 'Todas las categorías',
                value: form.category_id,
                options: categories,
                optionLabel: 'name',
                optionValue: 'id',
            },
            {
                key: 'status',
                label: 'Resultado',
                placeholder: 'Todos los resultados',
                value: form.status,
                options: [
                    { label: 'Macheado', value: 'matched' },
                    { label: 'Faltantes', value: 'missing' },
                    { label: 'Sobrantes', value: 'surplus' },
                    { label: 'No encontrado', value: 'not_found' },
                ],
            },
            {
                key: 'date_scope',
                label: 'Lectura',
                placeholder: 'Tipo de fecha',
                value: form.date_scope,
                options: [
                    { label: 'Por día', value: 'day' },
                    { label: 'Por mes', value: 'month' },
                    { label: 'Por año', value: 'year' },
                ],
            },
            {
                key: 'report_date',
                label: 'Fecha',
                type: 'date',
                value: form.report_date,
            },
            {
                key: 'search',
                label: 'Búsqueda',
                type: 'text',
                field: 'toolbar_search',
                placeholder: 'Código, producto, categoría, folio o usuario...',
                value: form.search ?? '',
                fullWidth: true,
            },
        ],
        actions: [
            {
                id: 'clear',
                label: 'Limpiar',
                icon: 'restart_alt',
                variant: 'slate',
            },
            {
                id: 'excel',
                label: 'Excel',
                icon: 'table_view',
                variant: 'green',
            },
            {
                id: 'pdf',
                label: 'PDF',
                icon: 'picture_as_pdf',
                variant: 'red',
            },
        ],
        tabs: [
            { key: 'summary', label: 'Resumen', icon: 'dashboard' },
            { key: 'branches', label: 'Sucursales', icon: 'store' },
            { key: 'audits', label: 'Auditorias', icon: 'assignment' },
            { key: 'detail', label: 'Detalle', icon: 'list_alt' },
            { key: 'users', label: 'Usuarios', icon: 'group' },
            { key: 'categories', label: 'Categorías', icon: 'category' },
            { key: 'differences', label: 'Diferencias', icon: 'analytics' },
        ],
    }
}

function uniqueUsers(users = []) {
    const seen = new Set()

    return users.filter((user) => {
        if (!user?.id || seen.has(user.id)) return false

        seen.add(user.id)
        return true
    })
}
