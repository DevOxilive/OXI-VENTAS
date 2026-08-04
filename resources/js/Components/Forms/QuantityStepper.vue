<script setup>
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
})

const emit = defineEmits(["decrease", "increase", "update", "update:value"])

function cleanQuantity(value) {
  const rawValue = String(value ?? '').replace(',', '.')

  if (!props.allowDecimal) {
    const integer = rawValue.replace(/[^\d]/g, '')

    return props.maxIntegerDigits
      ? integer.slice(0, props.maxIntegerDigits)
      : integer
  }

  const clean = rawValue.replace(/[^\d.]/g, '')
  const [integerPart = '', ...decimalParts] = clean.split('.')
  const integer = props.maxIntegerDigits
    ? integerPart.slice(0, props.maxIntegerDigits)
    : integerPart
  const hasDecimalPoint = clean.includes('.')
  const decimal = decimalParts.join('').slice(0, props.maxDecimalDigits)

  return hasDecimalPoint ? `${integer || '0'}.${decimal}` : integer
}

function handleInput(event) {
  const value = cleanQuantity(event.target.value)
  event.target.value = value
  emit('update', value)
  emit('update:value', value)
}

function preventWheel(event) {
  event.preventDefault()
  event.currentTarget.blur()
}
</script>

<template>
  <div class="mt-1 flex items-center rounded-xl border border-secondary bg-background md:mt-0">
    <button
      type="button"
      class="h-9 w-9 text-lg font-bold text-text disabled:cursor-not-allowed disabled:opacity-35"
      :disabled="disabled || decreaseDisabled"
      @click="$emit('decrease')"
    >
      -
    </button>

    <input
      :value="value"
      type="text"
      :inputmode="allowDecimal ? 'decimal' : 'numeric'"
      autocomplete="off"
      :aria-label="ariaLabel"
      :disabled="disabled"
      class="h-9 min-w-10 max-w-16 bg-transparent px-2 text-center text-sm font-semibold text-text outline-none disabled:cursor-not-allowed disabled:opacity-60"
      @input="handleInput"
      @wheel="preventWheel"
      @keydown.up.prevent
      @keydown.down.prevent
    >

    <button
      type="button"
      class="h-9 w-9 text-lg font-bold text-text disabled:cursor-not-allowed disabled:opacity-35"
      :disabled="disabled || increaseDisabled"
      @click="$emit('increase')"
    >
      +
    </button>
  </div>
</template>
