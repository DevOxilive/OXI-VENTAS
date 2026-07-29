export function getAttendanceScheduleAssignmentsToolbarConfig({ canCreate = false } = {}) {
    return {
        icon: 'assignment_ind',
        title: 'Asignación de horarios',
        subtitle: 'Asigna un horario y define los días laborables de cada empleado.',
        showSearch: false,
        showRecordsPerPage: false,
        showCounter: false,
        actions: [
            {
                id: 'create',
                label: 'Nueva asignación',
                icon: 'add',
                variant: 'primary',
                hidden: () => !canCreate,
            },
        ],
    }
}
