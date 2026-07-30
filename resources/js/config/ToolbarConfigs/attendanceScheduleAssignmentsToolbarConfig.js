export function getAttendanceScheduleAssignmentsToolbarConfig({
    canCreate = false,
    perPage = 30,
    total = 0,
} = {}) {
    return {
        icon: 'assignment_ind',
        title: 'Asignación de horarios',
        subtitle: 'Asigna un horario y define los días laborables de cada empleado.',
        showSearch: false,
        showRecordsPerPage: true,
        recordsPerPage: Number(perPage),
        recordsPerPageOptions: [10, 30, 50, 100],
        showCounter: true,
        filteredRecords: Number(total),
        totalRecords: Number(total),
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
