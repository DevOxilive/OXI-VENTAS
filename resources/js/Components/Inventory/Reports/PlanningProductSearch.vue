<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])
const query = ref(props.modelValue)

watch(() => props.modelValue, (value) => {
    const receivedValue = String(value ?? '')
    const typedValue = String(query.value ?? '')

    // El reporte normaliza el criterio para consultar. Si únicamente cambió
    // espacios de inicio o final, se conserva exactamente lo que sigue viendo
    // y escribiendo la persona en el campo.
    if (receivedValue.trim() !== typedValue.trim()) {
        query.value = receivedValue
    }
}, { immediate: true })

function search() {
    emit('update:modelValue', query.value)
}

</script>

<template>
    <div>
        <div class="flex gap-2">
            <input
                v-model="query"
                type="search"
                aria-label="Buscar producto por nombre o código de barras"
                placeholder="Código, nombre o coincidencia"
                autocomplete="off"
                autofocus
                class="h-11 min-w-0 w-full rounded-xl border border-secondary bg-background px-3 text-sm text-text"
                @input="search"
            />
        </div>
    </div>
</template>
