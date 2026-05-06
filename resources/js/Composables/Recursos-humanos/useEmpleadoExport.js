export function useEmpleadoExport(filtroEstado, filtroDepartamento, filtroPuesto, busqueda) {
    function exportarExcel() {
        const params = new URLSearchParams()

        if (filtroEstado.value) params.append('estado', filtroEstado.value)
        if (filtroDepartamento.value) params.append('departamento', filtroDepartamento.value)
        if (filtroPuesto.value) params.append('puesto', filtroPuesto.value)
        if (busqueda.value) params.append('busqueda', busqueda.value)

        const url = route('rh.empleados.exportarExcel') + '?' + params.toString()
        window.open(url, '_blank')
    }

    return { exportarExcel }
}