import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import {
    UniversalActionModal,
    ToastAlert,
    ErrorAlert
} from '@/Components/Modales/UniversalActionModal'

export function useEmpleadoActions() {
    const showModal = ref(false)
    const modoModal = ref('create')
    const empleadoSeleccionado = ref(null)

    function abrirModalGeneral() {
        modoModal.value = 'create'
        empleadoSeleccionado.value = null
        showModal.value = true
    }

    function abrirModalEditar(empleado) {
        modoModal.value = 'edit'
        empleadoSeleccionado.value = empleado
        showModal.value = true
    }

    function cerrarModal() {
        showModal.value = false
    }

    function eliminarEmpleado(empleado) {
        UniversalActionModal({
            title: 'Confirmar eliminación',
            message: '¿Deseas eliminar permanentemente a',
            itemName: `${empleado.nombre} ${empleado.apellido}`,
            confirmText: 'Sí, eliminar'
        }).then((result) => {
            if (result.isConfirmed) {
                router.delete(route('rh.empleados.destroy', empleado.id), {
                    onSuccess: () => {
                        ToastAlert({
                            icon: 'success',
                            title: 'Empleado eliminado correctamente'
                        })
                    },
                    onError: () => {
                        ErrorAlert({
                            title: 'Error al eliminar',
                            message: 'No fue posible eliminar el empleado'
                        })
                    }
                })
            }
        })
    }

    return {
        showModal,
        modoModal,
        empleadoSeleccionado,
        abrirModalGeneral,
        abrirModalEditar,
        cerrarModal,
        eliminarEmpleado
    }
}