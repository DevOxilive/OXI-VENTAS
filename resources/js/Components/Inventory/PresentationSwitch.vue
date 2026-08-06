<script setup>
const props = defineProps({
  modelValue: {
    type: String,
    default: 'piece',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  boxDisabled: {
    type: Boolean,
    default: false,
  },
  pieceLabel: {
    type: String,
    default: 'Piezas',
  },
  boxLabel: {
    type: String,
    default: 'Cajas',
  },
})

const emit = defineEmits(['update:modelValue', 'change'])

function select(value) {
  if (props.disabled || (value === 'box' && props.boxDisabled)) return

  emit('update:modelValue', value)
  emit('change', value)
}
</script>

<template>
  <div class="inline-flex rounded-lg border border-secondary bg-background p-1">
    <button
      type="button"
      class="rounded-md px-2 py-1 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-40"
      :class="modelValue === 'piece' ? 'bg-primary text-white' : 'text-text hover:text-primary'"
      :disabled="disabled"
      @click="select('piece')"
    >
      {{ pieceLabel }}
    </button>

    <button
      type="button"
      class="rounded-md px-2 py-1 text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-40"
      :class="modelValue === 'box' ? 'bg-primary text-white' : 'text-text hover:text-primary'"
      :disabled="disabled || boxDisabled"
      @click="select('box')"
    >
      {{ boxLabel }}
    </button>
  </div>
</template>
