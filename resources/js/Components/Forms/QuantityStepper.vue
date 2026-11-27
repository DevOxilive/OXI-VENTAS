<script setup>
import { computed, nextTick, ref, watch } from 'vue'
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
const editing = ref(false)
const direction = ref('up')
watch(() => props.value, (value, previous) => {
  direction.value = Number(value) >= Number(previous) ? 'up' : 'down'
})

function step(action) {
  editing.value = false
  inputEl.value?.blur()
  if (props.disabled || (action === 'increase' ? props.increaseDisabled : props.decreaseDisabled)) return
  emit(action)
}
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
  step('increase')
}

function handleArrowDown() {
  if (props.disabled || props.decreaseDisabled) return
  step('decrease')
}

defineExpose({
  focus: () => inputEl.value?.focus(),
})
</script>

<template>
  <div
    class="quantity-stepper"
    :class="{ 'quantity-stepper--disabled': disabled }"
  >
    <button
      type="button"
      class="quantity-stepper__button"
      :disabled="disabled || decreaseDisabled"
      :tabindex="buttonsTabbable ? 0 : -1"
      :aria-label="`Disminuir ${ariaLabel}`"
      @click="step('decrease')"
    >
      <svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10" stroke="currentColor" stroke-width="2" /></svg>
    </button>

    <div class="quantity-stepper__value">
    <Transition :name="`quantity-${direction}`">
      <span v-if="!editing" :key="String(value)" class="quantity-stepper__number" aria-hidden="true">{{ value }}</span>
    </Transition>
    <input
      ref="inputEl"
      :value="value"
      type="text"
      :inputmode="isDecimalQuantity ? 'decimal' : 'numeric'"
      autocomplete="off"
      :aria-label="ariaLabel"
      :disabled="disabled"
      class="quantity-stepper__input"
      :class="{ 'quantity-stepper__input--resting': !editing }"
      @focus="editing = true"
      @blur="editing = false"
      @keydown.enter="inputEl?.blur()"
      @input="handleInput"
      @wheel="preventWheel"
      @keydown.up.prevent="handleArrowUp"
      @keydown.down.prevent="handleArrowDown"
    >
    </div>

    <button
      type="button"
      class="quantity-stepper__button"
      :disabled="disabled || increaseDisabled"
      :tabindex="buttonsTabbable ? 0 : -1"
      :aria-label="`Aumentar ${ariaLabel}`"
      @click="step('increase')"
    >
      <svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M3 8h10M8 3v10" stroke="currentColor" stroke-width="2" /></svg>
    </button>
  </div>
</template>

<style scoped>
.quantity-stepper {
  display: inline-flex;
  align-items: stretch;
  width: 100%;
  min-width: min(120px, 100%);
  max-width: 240px;
  height: 48px;
  flex: 1 1 140px;
  container-type: inline-size;
  border: 1px solid color-mix(in srgb, currentColor 45%, transparent);
  border-radius: 8px;
  color: inherit;
  background: transparent;
  transition: border-color 160ms ease;
}
.quantity-stepper:focus-within { outline: 2px solid currentColor; outline-offset: 2px; }
.quantity-stepper__button {
  display: grid;
  place-items: center;
  width: clamp(36px, 22%, 46px);
  flex: 0 1 46px;
  border: 0;
  border-radius: 7px;
  background: transparent;
  color: inherit;
  transition: background-color 160ms ease, opacity 160ms ease;
}
.quantity-stepper__button:hover:not(:disabled) { background: color-mix(in srgb, currentColor 6%, transparent); }
.quantity-stepper__button:active:not(:disabled) { background: color-mix(in srgb, currentColor 12%, transparent); }
.quantity-stepper__button:disabled { opacity: .3; cursor: not-allowed; }
.quantity-stepper__value { position: relative; flex: 1; min-width: 0; overflow: hidden; }
.quantity-stepper__input,
.quantity-stepper__number {
  width: 100%;
  height: 100%;
  font: inherit;
  font-size: 16px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  text-align: center;
}
.quantity-stepper__number { position: absolute; inset: 0; display: grid; place-items: center; pointer-events: none; }
.quantity-stepper__input { position: relative; border: 0; border-radius: 0; padding: 0 2px; background: transparent; color: inherit; outline: none; box-shadow: none; }
.quantity-stepper__input--resting { color: transparent; caret-color: transparent; }
.quantity-stepper--disabled { opacity: .5; }
.quantity-up-enter-active, .quantity-down-enter-active { transition: transform 280ms cubic-bezier(.2,.9,.25,1.15), opacity 200ms ease; }
.quantity-up-leave-active, .quantity-down-leave-active { transition: transform 200ms ease, opacity 160ms ease; }
.quantity-up-enter-from, .quantity-down-leave-to { transform: translateY(75%); opacity: 0; }
.quantity-up-leave-to, .quantity-down-enter-from { transform: translateY(-75%); opacity: 0; }
@media (prefers-reduced-motion: reduce) {
  .quantity-stepper *, .quantity-stepper { transition: none !important; }
}

@media (max-width: 480px) {
  .quantity-stepper { height: 40px; }
  .quantity-stepper__button { flex-basis: 40px; }
}

@container (max-width: 160px) {
  .quantity-stepper__button {
    width: 34px;
    flex-basis: 34px;
  }

  .quantity-stepper__input,
  .quantity-stepper__number {
    font-size: 13px;
  }
}

@container (max-width: 130px) {
  .quantity-stepper__button {
    width: 30px;
    flex-basis: 30px;
  }

  .quantity-stepper__input,
  .quantity-stepper__number {
    font-size: 12px;
  }
}
</style>
