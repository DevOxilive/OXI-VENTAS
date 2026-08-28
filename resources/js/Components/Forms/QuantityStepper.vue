<script setup>
import { computed, nextTick, ref } from 'vue'
import { fieldRegistry } from '@/Validation/fieldRegistry'
import { sanitizeFieldWithCursor } from '@/Validation/sanitizers'

const props = defineProps({
  value: {
    type: [String, Number],
    required: true,
  },
  decreaseDisabled: {
    type: Boolean,
    default: false,
  },
  increaseDisabled: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  ariaLabel: {
    type: String,
    default: 'Cantidad',
  },
  allowDecimal: {
    type: Boolean,
    default: false,
  },
  maxIntegerDigits: {
    type: Number,
    default: null,
  },
  maxDecimalDigits: {
    type: Number,
    default: 3,
  },
  validationField: {
    type: String,
    default: '',
  },
  autoDecimalAfterIntegerDigits: {
    type: Boolean,
    default: undefined,
  },
  buttonsTabbable: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(["decrease", "increase", "update", "update:value"])
const inputEl = ref(null)
const isDecimalQuantity = computed(() => (
  fieldRegistry[props.validationField]?.type === 'decimal' || props.allowDecimal
))

const quantityConfig = computed(() => {
  const registeredConfig = fieldRegistry[props.validationField] ?? {}

  return {
    ...registeredConfig,
    type: isDecimalQuantity.value ? 'decimal' : 'numeric',
    max: isDecimalQuantity.value
      ? registeredConfig.max
      : (props.maxIntegerDigits ?? registeredConfig.max),
    maxIntegerDigits: props.maxIntegerDigits ?? registeredConfig.maxIntegerDigits,
    maxDecimalDigits: props.maxDecimalDigits ?? registeredConfig.maxDecimalDigits,
    autoDecimalAfterIntegerDigits:
      props.autoDecimalAfterIntegerDigits
      ?? registeredConfig.autoDecimalAfterIntegerDigits
      ?? false,
    enforceMaxOnInput: true,
  }
})

function handleInput(event) {
  const sanitized = sanitizeFieldWithCursor(
    event.target.value,
    quantityConfig.value,
    event.target.selectionStart ?? 0,
    event.target.selectionEnd ?? 0,
  )

  event.target.value = sanitized.value
  emit('update', sanitized.value)
  emit('update:value', sanitized.value)

  nextTick(() => {
    event.target.setSelectionRange?.(sanitized.selectionStart, sanitized.selectionEnd)
  })
}

function preventWheel(event) {
  event.preventDefault()
  event.currentTarget.blur()
}

function handleArrowUp() {
  if (props.disabled || props.increaseDisabled) return
  emit('increase')
}

function handleArrowDown() {
  if (props.disabled || props.decreaseDisabled) return
  emit('decrease')
}

defineExpose({
  focus: () => inputEl.value?.focus(),
})
</script>

<template>
  <div class="mt-1 flex items-center rounded-xl border border-secondary bg-background md:mt-0">
    <button
      type="button"
      class="h-9 w-9 text-lg font-bold text-text disabled:cursor-not-allowed disabled:opacity-35"
      :disabled="disabled || decreaseDisabled"
      :tabindex="buttonsTabbable ? 0 : -1"
      @click="$emit('decrease')"
    >
      -
    </button>

    <input
      ref="inputEl"
      :value="value"
      type="text"
      :inputmode="isDecimalQuantity ? 'decimal' : 'numeric'"
      autocomplete="off"
      :aria-label="ariaLabel"
      :disabled="disabled"
      class="h-9 min-w-10 max-w-16 bg-transparent px-2 text-center text-sm font-semibold text-text outline-none disabled:cursor-not-allowed disabled:opacity-60"
      @input="handleInput"
      @wheel="preventWheel"
      @keydown.up.prevent="handleArrowUp"
      @keydown.down.prevent="handleArrowDown"
    >

    <button
      type="button"
      class="h-9 w-9 text-lg font-bold text-text disabled:cursor-not-allowed disabled:opacity-35"
      :disabled="disabled || increaseDisabled"
      :tabindex="buttonsTabbable ? 0 : -1"
      @click="$emit('increase')"
    >
      +
    </button>
  </div>
</template>
