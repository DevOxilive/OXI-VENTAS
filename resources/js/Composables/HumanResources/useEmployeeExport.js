export function useEmployeeExport(
    statusFilter,
    departmentFilter,
    positionFilter,
    search,
    startDateFromFilter,
    startDateToFilter,
) {
    function exportExcel() {
        const params = new URLSearchParams();

        if (statusFilter.value) {
            params.append("employmentStatus", statusFilter.value);
        }

        if (departmentFilter.value) {
            params.append("department", departmentFilter.value);
        }

        if (positionFilter.value) {
            params.append("position", positionFilter.value);
        }

        if (search.value) {
            params.append("search", search.value);
        }

        if (startDateFromFilter.value) {
            params.append("startDateFrom", startDateFromFilter.value);
        }

        if (startDateToFilter.value) {
            params.append("startDateTo", startDateToFilter.value);
        }

        const url =
            route("human-resources.employees.export-excel") +
            "?" +
            params.toString();

        window.open(url, "_blank");
    }

    return { exportExcel };
}
