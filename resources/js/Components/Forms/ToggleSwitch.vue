<script setup>
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(["update:modelValue", "change"])

function toggle() {
  if (props.disabled) {
    return
  }

  const nextValue = !props.modelValue
  emit("update:modelValue", nextValue)
  emit("change", nextValue)
}
</script>

<template>
  <button
    type="button"
    class="flex h-6 w-11 items-center rounded-full p-1 transition"
    :class="modelValue ? 'bg-primary' : 'bg-secondary'"
    :disabled="disabled"
    @click="toggle"
  >
    <span
      class="h-4 w-4 rounded-full bg-white shadow transition"
      :class="modelValue ? 'translate-x-5' : 'translate-x-0'"
    />
  </button>
</template>
