<script setup>
import { computed } from 'vue'

const props = defineProps({
    label: String,
    modelValue: [String, Number],
    error: String,
    type: {
        type: String,
        default: 'text'
    },
    readonly: Boolean
})

const emit = defineEmits(['update:modelValue', 'validate'])

const fieldRules = computed(() => ({
    nombre: { max: 40, regex: /[^A-Za-zÁÉÍÓÚáéíóúÑñ\s]/g, numeric: false },
    apellido: { max: 40, regex: /[^A-Za-zÁÉÍÓÚáéíóúÑñ\s]/g, numeric: false },
    puesto: { max: 50, regex: /[^A-Za-zÁÉÍÓÚáéíóúÑñ\s]/g, numeric: false },
    correo: { max: 80, regex: /[\s]/g, numeric: false },
    telefono: { max: 10, regex: /[^0-9]/g, numeric: true },
    domicilio: { max: 120, regex: /[^A-Za-zÁÉÍÓÚáéíóúÑñ0-9\s#.,\-]/g, numeric: false },
    banco: { max: 50, regex: /[^A-Za-zÁÉÍÓÚáéíóúÑñ\s]/g, numeric: false },
    cuenta: { max: 18, regex: /[^0-9]/g, numeric: true },
    grado: { max: 40, regex: /[^A-Za-zÁÉÍÓÚáéíóúÑñ\s]/g, numeric: false },
    especialidad: { max: 50, regex: /[^A-Za-zÁÉÍÓÚáéíóúÑñ\s]/g, numeric: false },
    nss: { max: 11, regex: /[^0-9]/g, numeric: true },
    rfc: { max: 13, regex: /[^A-Za-z0-9Ññ&]/g, numeric: false }
}))

const labelMap = {
    'nombre': 'nombre',
    'apellido': 'apellido',
    'puesto': 'puesto',
    'correo electronico': 'correo',
    'telefono': 'telefono',
    'domicilio': 'domicilio',
    'fecha de ingreso': 'fechaInicio',
    'banco': 'banco',
    'cuenta bancaria': 'cuenta',
    'grado academico': 'grado',
    'especialidad': 'especialidad',
    'nss': 'nss',
    'rfc': 'rfc'
}

function getFieldKey() {
    const normalized = props.label
        ?.toLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")

    return labelMap[normalized] || normalized
}

function sanitizeValue(value) {
    const key = getFieldKey()
    const rule = fieldRules.value[key]

    if (!rule) return value

    let limpio = value.toString()
    limpio = limpio.replace(rule.regex, '')
    limpio = limpio.replace(/\s+/g, ' ')
    limpio = limpio.trimStart()

    if (key === 'rfc') limpio = limpio.toUpperCase()

    return limpio
}

function handleInput(e) {
    const key = getFieldKey()
    const rule = fieldRules.value[key]

    let sanitized = sanitizeValue(e.target.value)

    if (rule && sanitized.length > rule.max) {
        sanitized = sanitized.slice(0, rule.max)
    }

    emit('update:modelValue', sanitized)
    emit('validate', key)
}

function blockExtraInput(e) {
    const key = getFieldKey()
    const rule = fieldRules.value[key]

    if (!rule) return

    const allowedKeys = [
        'Backspace', 'Delete', 'ArrowLeft', 'ArrowRight',
        'ArrowUp', 'ArrowDown', 'Tab'
    ]

    if (e.ctrlKey || e.metaKey) return
    if (allowedKeys.includes(e.key)) return

    const currentLength = (props.modelValue || '').toString().length

    if (rule.numeric && !/[0-9]/.test(e.key)) {
        e.preventDefault()
        return
    }

    if (currentLength >= rule.max) {
        e.preventDefault()
    }
}
</script>

<template>
    <div>
        <label class="block text-sm font-semibold mb-1 text-slate-700">{{ label }}</label>

        <input :type="type" :value="modelValue" :readonly="readonly" @keydown="blockExtraInput" @input="handleInput"
            @blur="emit('validate', getFieldKey())" :class="[
                'w-full px-4 py-3 rounded-xl border outline-none transition text-sm',
                readonly ? 'bg-slate-100 cursor-not-allowed' : 'bg-white',
                error ? 'border-red-500 bg-red-50' : 'border-slate-300 focus:border-[#1f1d2b]'
            ]">

        <div class="flex justify-between items-center mt-1">
            <p v-if="error" class="text-red-500 text-xs">{{ error }}</p>

            <p v-if="fieldRules[getFieldKey()]" class="text-[11px] text-slate-400 ml-auto">
                {{ (modelValue || '').toString().length }}/{{ fieldRules[getFieldKey()].max }}
            </p>
        </div>
    </div>
</template>