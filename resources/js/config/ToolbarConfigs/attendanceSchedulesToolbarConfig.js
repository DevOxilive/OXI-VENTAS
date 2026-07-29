export function getAttendanceSchedulesToolbarConfig({ canCreate = false } = {}) {
    return {
        icon: 'schedule',
        title: 'Horarios',
        subtitle: 'Configura jornadas y tolerancias para el registro de asistencia.',
        showSearch: false,
        showRecordsPerPage: false,
        showCounter: false,
        actions: [
            {
                id: 'create',
                label: 'Crear horario',
                icon: 'add',
                variant: 'primary',
                hidden: () => !canCreate,
            },
        ],
    }
}
