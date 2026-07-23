<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, required: true },
  field: { type: String, required: true },
  error: { type: String, default: '' },
  readonly: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])
const hours = Array.from({ length: 24 }, (_, value) => String(value).padStart(2, '0'))
const minutes = Array.from({ length: 60 }, (_, value) => String(value).padStart(2, '0'))

function parts() {
  const [hour = '08', minute = '00'] = String(props.modelValue || '08:00').split(':')
  return { hour: hour.padStart(2, '0'), minute: minute.padStart(2, '0') }
}

function update(part, value) {
  const current = parts()
  current[part] = value
  emit('update:modelValue', `${current.hour}:${current.minute}`)
}

const hour = computed({ get: () => parts().hour, set: (value) => update('hour', value) })
const minute = computed({ get: () => parts().minute, set: (value) => update('minute', value) })
</script>

<template>
  <div>
    <label :for="`${field}-hour`" class="mb-1 block text-sm font-semibold text-text">{{ label }}</label>
    <div class="flex items-center gap-2">
      <select
        :id="`${field}-hour`"
        v-model="hour"
        :disabled="readonly"
        aria-label="Hora"
        class="min-w-0 flex-1 rounded-xl border border-secondary bg-background px-3 py-3 text-center text-sm text-text outline-none transition focus:border-primary focus:ring-2 focus:ring-primary disabled:cursor-not-allowed disabled:bg-secondary disabled:opacity-60"
      >
        <option v-for="value in hours" :key="value" :value="value">{{ value }}</option>
      </select>
      <span class="text-lg font-bold text-text opacity-65">:</span>
      <select
        :id="`${field}-minute`"
        v-model="minute"
        :disabled="readonly"
        aria-label="Minutos"
        class="min-w-0 flex-1 rounded-xl border border-secondary bg-background px-3 py-3 text-center text-sm text-text outline-none transition focus:border-primary focus:ring-2 focus:ring-primary disabled:cursor-not-allowed disabled:bg-secondary disabled:opacity-60"
      >
        <option v-for="value in minutes" :key="value" :value="value">{{ value }}</option>
      </select>
    </div>
    <p v-if="error" class="mt-1 text-xs text-primary">{{ error }}</p>
    <p v-else-if="!compact" class="mt-1 text-xs text-text opacity-55">Selecciona hora y minutos en formato de 24 horas.</p>
  </div>
</template>
