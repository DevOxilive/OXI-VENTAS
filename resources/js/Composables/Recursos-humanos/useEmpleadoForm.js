import { useForm } from '@inertiajs/vue3'
import { reactive, computed, watch } from 'vue'
import {
    WarningAlert,
    ToastAlert,
    ErrorAlert
} from '@/Components/Modales/UniversalActionModal'

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
        if (props.modo !== 'edit' || !props.empleadoEditar) return

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

    const validators = {
        onlyLetters: value => /^[A-Za-zÁÉÍÓÚáéíóúÑñ\s]+$/.test(value),
        validEmail: value => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
        validPhone: value => /^\d{10}$/.test(value),
        validCuenta: value => /^\d{10,18}$/.test(value),
        validNSS: value => /^\d{11}$/.test(value),
        validRFC: value => /^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/.test(value),
        validDate: value => {
            if (!value) return false
            return value <= new Date().toISOString().split('T')[0]
        }
    }

    function detectSpamText(value) {
        if (!value) return false

        const texto = value.toLowerCase().trim()

        if (/^(.)\1{4,}$/.test(texto)) return true
        if (/^[0-9]{5,}$/.test(texto)) return true
        if (/^[^a-záéíóúñ0-9]+$/i.test(texto)) return true
        if (/(\w{2,4})\1{2,}/.test(texto)) return true
        if (/[bcdfghjklmnñpqrstvwxyz]{6,}/i.test(texto)) return true
        if (/asd|qwe|zxc|wer|sdf|xcv/i.test(texto)) return true

        const vocales = (texto.match(/[aeiouáéíóú]/gi) || []).length
        if (texto.length >= 8 && vocales <= 1) return true

        return false
    }

    function validarCampo(campo) {
        frontendErrors[campo] = ''
        const valor = empleado[campo]

        if (!valor || valor.toString().trim() === '') {
            frontendErrors[campo] = 'Este campo es obligatorio.'
            return
        }

        if (['nombre', 'apellido', 'puesto', 'banco', 'grado', 'especialidad'].includes(campo)) {
            if (!validators.onlyLetters(valor)) {
                frontendErrors[campo] = 'Solo se permiten letras.'
                return
            }

            if (detectSpamText(valor)) {
                frontendErrors[campo] = 'Texto no válido.'
                return
            }
        }

        if (campo === 'domicilio' && detectSpamText(valor)) {
            frontendErrors[campo] = 'Domicilio no válido.'
            return
        }

        if (campo === 'correo' && !validators.validEmail(valor)) {
            frontendErrors[campo] = 'Correo inválido.'
            return
        }

        if (campo === 'telefono' && !validators.validPhone(valor)) {
            frontendErrors[campo] = 'Debe contener 10 dígitos.'
            return
        }

        if (campo === 'fechaInicio' && !validators.validDate(valor)) {
            frontendErrors[campo] = 'Fecha no válida.'
            return
        }

        if (campo === 'cuenta' && !validators.validCuenta(valor)) {
            frontendErrors[campo] = 'Cuenta inválida.'
            return
        }

        if (campo === 'nss' && !validators.validNSS(valor)) {
            frontendErrors[campo] = 'NSS inválido.'
            return
        }

        if (campo === 'rfc' && !validators.validRFC(valor)) {
            frontendErrors[campo] = 'RFC inválido.'
            return
        }
    }

    function validarFormularioCompleto() {
        const campos = [
            'nombre', 'apellido', 'puesto', 'departamento', 'estado',
            'correo', 'telefono', 'domicilio', 'fechaInicio',
            'banco', 'cuenta', 'grado', 'especialidad',
            'tipoContrato', 'nss', 'rfc'
        ]

        campos.forEach(validarCampo)

        return !Object.values(frontendErrors).some(e => e !== '')
    }

    const resumenErrores = computed(() =>
        Object.values(frontendErrors).filter(e => e !== '')
    )

    watch(() => empleado.rfc, nuevo => empleado.rfc = nuevo.toUpperCase())

    watch(() => empleado.fechaInicio, nuevo => {
        if (!nuevo) return empleado.antiguedad = ''

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

    function guardar() {
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
                    title: props.modo === 'create'
                        ? 'Empleado registrado correctamente'
                        : 'Empleado actualizado correctamente'
                })

                emit('close')
                empleado.reset()
                Object.keys(frontendErrors).forEach(key => frontendErrors[key] = '')
            },

            onError: () => {
                ErrorAlert({
                    title: 'Error en la operación',
                    message: props.modo === 'create'
                        ? 'No fue posible registrar el empleado'
                        : 'No fue posible actualizar el empleado'
                })
            }
        }

        props.modo === 'create'
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