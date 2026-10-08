<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { fieldRegistry } from '@/Validation/fieldRegistry'

const props = defineProps({
  label: { type: String, default: '' },
  field: { type: String, default: '' },
  modelValue: { type: [String, Number], default: '' },
  error: { type: String, default: '' },
  options: { type: Array, default: () => [] },
  optionLabel: { type: String, default: 'label' },
  optionValue: { type: String, default: 'value' },
  placeholder: { type: String, default: 'Buscar una opción' },
  emptyMessage: { type: String, default: 'No se encontraron coincidencias.' },
  disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'validate', 'change', 'keydown'])
const root = ref(null)
const input = ref(null)
const open = ref(false)
const search = ref('')
const highlightedIndex = ref(0)

const fieldConfig = computed(() => fieldRegistry[props.field])

function getOptionLabel(option) {
  return String(option?.[props.optionLabel] ?? option?.label ?? option?.name ?? option ?? '')
}

function getOptionValue(option) {
  return option?.[props.optionValue] ?? option?.value ?? option?.id ?? option ?? ''
}

function normalize(value) {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim()
}

const selectedOption = computed(() => props.options.find(
  (option) => String(getOptionValue(option)) === String(props.modelValue ?? ''),
))

const filteredOptions = computed(() => {
  const query = normalize(search.value)
  if (!query || (selectedOption.value && query === normalize(getOptionLabel(selectedOption.value)))) {
    return props.options
  }

  return props.options.filter((option) => normalize(getOptionLabel(option)).includes(query))
})

function syncSelectedLabel() {
  search.value = selectedOption.value ? getOptionLabel(selectedOption.value) : ''
}

function openOptions() {
  if (props.disabled) return
  open.value = true
  highlightedIndex.value = 0
  nextTick(() => input.value?.select?.())
}

function handleInput(event) {
  search.value = event.target.value
  open.value = true
  highlightedIndex.value = 0

  if (props.modelValue !== '' && props.modelValue !== null) {
    emit('update:modelValue', '')
    emit('change', '')
  }
}

function selectOption(option) {
  const value = getOptionValue(option)
  search.value = getOptionLabel(option)
  open.value = false
  emit('update:modelValue', value)
  emit('change', value)
  emit('validate', props.field)
}

function clearSelection() {
  search.value = ''
  open.value = true
  highlightedIndex.value = 0
  emit('update:modelValue', '')
  emit('change', '')
  nextTick(() => input.value?.focus?.())
}

function closeOptions() {
  open.value = false
  syncSelectedLabel()
  emit('validate', props.field)
}

function handleKeydown(event) {
  if (event.key === 'ArrowDown') {
    event.preventDefault()
    open.value = true
    highlightedIndex.value = Math.min(highlightedIndex.value + 1, filteredOptions.value.length - 1)
  }

  if (event.key === 'ArrowUp') {
    event.preventDefault()
    highlightedIndex.value = Math.max(highlightedIndex.value - 1, 0)
  }

  if (event.key === 'Enter' && open.value && filteredOptions.value[highlightedIndex.value]) {
    event.preventDefault()
    selectOption(filteredOptions.value[highlightedIndex.value])
    return
  }

  if (event.key === 'Escape') {
    event.stopPropagation()
    closeOptions()
  }

  emit('keydown', event)
}

function handleOutsideClick(event) {
  if (!open.value || root.value?.contains(event.target)) return
  closeOptions()
}

watch(() => props.modelValue, syncSelectedLabel, { immediate: true })
watch(() => props.options, syncSelectedLabel)

onMounted(() => document.addEventListener('pointerdown', handleOutsideClick))
onBeforeUnmount(() => document.removeEventListener('pointerdown', handleOutsideClick))

defineExpose({
  focus: () => input.value?.focus(),
})
</script>

<template>
  <div ref="root" class="relative">
    <label v-if="label" :for="field" class="mb-1 block text-sm font-semibold text-text">
      {{ label }}
    </label>

    <div class="relative">
      <span class="material-symbols-outlined pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-[20px] text-text opacity-50">
        search
      </span>
      <input
        :id="field"
        ref="input"
        :name="field"
        :value="search"
        type="text"
        autocomplete="off"
        :disabled="disabled"
        :placeholder="placeholder"
        class="w-full rounded-xl border bg-background py-3 pl-11 pr-11 text-sm text-text outline-none transition placeholder:text-text placeholder:opacity-50 focus:border-primary focus:ring-2 focus:ring-primary disabled:cursor-not-allowed disabled:bg-secondary disabled:opacity-60"
        :class="error ? 'border-primary bg-secondary' : 'border-secondary'"
        role="combobox"
        aria-autocomplete="list"
        :aria-expanded="open"
        @focus="openOptions"
        @input="handleInput"
        @keydown="handleKeydown"
      />

      <button
        v-if="search && !disabled"
        type="button"
        class="absolute right-3 top-1/2 flex h-7 w-7 -translate-y-1/2 items-center justify-center rounded-full text-text opacity-55 transition hover:bg-secondary hover:opacity-100"
        aria-label="Limpiar selección"
        @click="clearSelection"
      >
        <span class="material-symbols-outlined text-[18px]">close</span>
      </button>
    </div>

    <div
      v-if="open"
      class="absolute z-50 mt-2 max-h-64 w-full overflow-y-auto rounded-xl border border-secondary bg-background p-2 shadow-2xl"
      role="listbox"
    >
      <button
        v-for="(option, index) in filteredOptions"
        :key="getOptionValue(option)"
        type="button"
        class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm text-text transition"
        :class="index === highlightedIndex ? 'bg-secondary' : 'hover:bg-secondary'"
        role="option"
        :aria-selected="String(getOptionValue(option)) === String(modelValue)"
        @mouseenter="highlightedIndex = index"
        @click="selectOption(option)"
      >
        <span class="material-symbols-outlined text-[20px] text-primary">
          {{ String(getOptionValue(option)).startsWith('customer:') ? 'person' : 'badge' }}
        </span>
        <span class="min-w-0 flex-1 truncate font-semibold" :title="getOptionLabel(option)">
          {{ getOptionLabel(option) }}
        </span>
        <span
          v-if="String(getOptionValue(option)) === String(modelValue)"
          class="material-symbols-outlined text-[18px] text-primary"
        >
          check_circle
        </span>
      </button>

      <p v-if="!filteredOptions.length" class="px-3 py-5 text-center text-sm font-semibold text-text opacity-60">
        {{ emptyMessage }}
      </p>
    </div>

    <div class="mt-1 flex items-center justify-between gap-3">
      <p v-if="error" class="text-xs text-primary">{{ error }}</p>
      <p v-if="fieldConfig?.required" class="ml-auto text-[11px] text-text opacity-50">Obligatorio</p>
    </div>
  </div>
</template>
