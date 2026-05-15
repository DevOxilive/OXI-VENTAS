import { ref, computed, unref } from "vue";

export function useEmployeeFilters(employeesDB) {
    const statusFilter = ref("");
    const departmentFilter = ref("");
    const positionFilter = ref("");
    const recordsToShow = ref(10);
    const search = ref("");

    const filteredEmployees = computed(() => {
        return unref(employeesDB)
            .filter((employee) => {
                const fullName =
                    `${employee.firstName} ${employee.lastName}`.toLowerCase();

                const matchesSearch =
                    fullName.includes(search.value.toLowerCase()) ||
                    employee.position
                        ?.toLowerCase()
                        .includes(search.value.toLowerCase()) ||
                    employee.department
                        ?.toLowerCase()
                        .includes(search.value.toLowerCase());

                const matchesStatus = statusFilter.value
                    ? employee.employmentStatus === statusFilter.value
                    : true;

                const matchesDepartment = departmentFilter.value
                    ? employee.department === departmentFilter.value
                    : true;

                const matchesPosition = positionFilter.value
                    ? employee.position === positionFilter.value
                    : true;

                return (
                    matchesSearch &&
                    matchesStatus &&
                    matchesDepartment &&
                    matchesPosition
                );
            })
            .slice(0, recordsToShow.value);
    });

    return {
        statusFilter,
        departmentFilter,
        positionFilter,
        recordsToShow,
        search,
        filteredEmployees,
    };
}
