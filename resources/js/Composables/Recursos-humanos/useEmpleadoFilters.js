import { ref, computed } from 'vue'

export function useEmpleadoFilters(empleadosDB) {
    const filtroEstado = ref('')
    const filtroDepartamento = ref('')
    const filtroPuesto = ref('')
    const registrosAMostrar = ref(10)
    const busqueda = ref('')

    const empleadosFiltrados = computed(() => {
        return empleadosDB.filter((empleado) => {
            const nombreCompleto = `${empleado.nombre} ${empleado.apellido}`.toLowerCase()

            const coincideBusqueda =
                nombreCompleto.includes(busqueda.value.toLowerCase()) ||
                empleado.puesto?.toLowerCase().includes(busqueda.value.toLowerCase()) ||
                empleado.departamento?.toLowerCase().includes(busqueda.value.toLowerCase())

            const coincideEstado = filtroEstado.value ? empleado.estado === filtroEstado.value : true
            const coincideDepartamento = filtroDepartamento.value ? empleado.departamento === filtroDepartamento.value : true
            const coincidePuesto = filtroPuesto.value ? empleado.puesto === filtroPuesto.value : true

            return coincideBusqueda && coincideEstado && coincideDepartamento && coincidePuesto
        }).slice(0, registrosAMostrar.value)
    })

    return {
        filtroEstado,
        filtroDepartamento,
        filtroPuesto,
        registrosAMostrar,
        busqueda,
        empleadosFiltrados
    }
}