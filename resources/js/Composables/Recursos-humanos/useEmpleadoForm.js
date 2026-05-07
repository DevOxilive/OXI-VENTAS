import { useForm } from '@inertiajs/vue3'
import { reactive, computed, watch } from 'vue'
import {
    WarningAlert,
    ToastAlert,
    ErrorAlert
} from '@/Components/Modales/UniversalActionModal'
import { validateSingleField, validateForm } from '@/Validation/schemaBuilder'

const empleadoFields = [
    'nombre',
    'apellido',
    'puesto',
    'departamento',
    'estado',
    'correo',
    'telefono',
    'domicilio',
    'fechaInicio',
    'banco',
    'cuenta',
    'grado',
    'especialidad',
    'tipoContrato',
    'nss',
    'rfc'
]

export function useEmpleadoForm(props, emit) {
    const departamentos = [
        'Inventario',
        'Sistemas',
        'Recursos Humanos',
        'Ventas'
    ]

    const empleado = useForm({
        nombre: '',
        apellido: '',
        puesto: '',
        departamento: '',
        estado: 'Activo',
        correo: '',
        telefono: '',
        domicilio: '',
        fechaInicio: '',
        banco: '',
        cuenta: '',
        grado: '',
        especialidad: '',
        tipoContrato: '',
        antiguedad: '',
        nss: '',
        rfc: ''
    })

    const frontendErrors = reactive({})


    function cargarDatosEdicion() {
        const esEdicion = ['edit', 'editar'].includes(props.modo)

        if (!esEdicion || !props.empleadoEditar) return

        empleado.defaults({
            nombre: props.empleadoEditar.nombre || '',
            apellido: props.empleadoEditar.apellido || '',
            puesto: props.empleadoEditar.puesto || '',
            departamento: props.empleadoEditar.departamento || '',
            estado: props.empleadoEditar.estado || 'Activo',
            correo: props.empleadoEditar.correo || '',
            telefono: props.empleadoEditar.telefono || '',
            domicilio: props.empleadoEditar.domicilio || '',
            fechaInicio: props.empleadoEditar.fechaInicio || '',
            banco: props.empleadoEditar.banco || '',
            cuenta: props.empleadoEditar.cuenta || '',
            grado: props.empleadoEditar.grado || '',
            especialidad: props.empleadoEditar.especialidad || '',
            tipoContrato: props.empleadoEditar.tipoContrato || '',
            antiguedad: props.empleadoEditar.antiguedad || '',
            nss: props.empleadoEditar.nss || '',
            rfc: props.empleadoEditar.rfc || ''
        })

        empleado.reset()
    }

    function validarCampo(campo) {
        if (!campo) return

        frontendErrors[campo] = validateSingleField(campo, empleado[campo])
    }

    function validarFormularioCompleto() {
        empleadoFields.forEach(field => {
            frontendErrors[field] = ''
        })

        const errors = validateForm(empleadoFields, empleado.data())

        Object.entries(errors).forEach(([field, message]) => {
            frontendErrors[field] = message
        })

        return Object.keys(errors).length === 0
    }

    const resumenErrores = computed(() =>
        Object.values(frontendErrors).filter(error => error !== '')
    )

    watch(() => empleado.rfc, nuevo => {
        if (!nuevo) return
        empleado.rfc = nuevo.toUpperCase()
    })

    watch(() => empleado.fechaInicio, nuevo => {
        if (!nuevo) {
            empleado.antiguedad = ''
            return
        }

        const ingreso = new Date(nuevo)
        const hoy = new Date()

        let years = hoy.getFullYear() - ingreso.getFullYear()
        let months = hoy.getMonth() - ingreso.getMonth()

        if (months < 0) {
            years--
            months += 12
        }

        empleado.antiguedad = `${years} años ${months} meses`
    })

    function limpiarErroresFrontend() {
        empleadoFields.forEach(field => {
            frontendErrors[field] = ''
        })
    }

    function guardar() {
        const esCreacion = ['crear', 'create'].includes(props.modo)

        esCreacion
            ? empleado.post(route('rh.empleados.store'), config)
            : empleado.put(route('rh.empleados.update', props.empleadoEditar.id), config)
        if (!validarFormularioCompleto()) {
            WarningAlert({
                title: 'Formulario incompleto',
                message: 'Debes corregir los campos marcados antes de continuar'
            })
            return

        }

        const config = {
            onSuccess: () => {
                ToastAlert({
                    icon: 'success',
                    title: props.modo === 'crear'
                        ? 'Empleado registrado correctamente'
                        : 'Empleado actualizado correctamente'
                })

                emit('close')
                empleado.reset()
                limpiarErroresFrontend()
            },

            onError: () => {
                ErrorAlert({
                    title: 'Error en la operación',
                    message: props.modo === 'crear'
                        ? 'No fue posible registrar el empleado'
                        : 'No fue posible actualizar el empleado'
                })
            }
        }

        props.modo === 'crear'
            ? empleado.post(route('rh.empleados.store'), config)
            : empleado.put(route('rh.empleados.update', props.empleadoEditar.id), config)
    }

    return {
        empleado,
        frontendErrors,
        departamentos,
        resumenErrores,
        validarCampo,
        guardar,
        cargarDatosEdicion
    }
}